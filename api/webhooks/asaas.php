<?php
/**
 * ============================================================================
 * WEBHOOK ASAAS - Processamento de Notificações de Pagamento
 * ============================================================================
 *
 * URL para configurar no Asaas (produção):
 * https://painel.imobsites.com.br/api/webhooks/asaas.php
 *
 * Token de validação:
 * - Lido de ASAAS_WEBHOOK_TOKEN (definido no .htaccess/variáveis de ambiente)
 * - Fallback: config/asaas.php['webhook_token'] (apenas desenvolvimento local)
 * - O Asaas envia este token no header HTTP: asaas-access-token
 *
 * ============================================================================
 * VÍNCULO ENTRE ASAAS E ORDERS (Tabela orders)
 * ============================================================================
 *
 * O sistema identifica qual pedido interno corresponde a um pagamento do Asaas
 * usando a seguinte ordem de prioridade:
 *
 * 1. externalReference (PRIORIDADE MÁXIMA)
 *    - Formato enviado: "order:123" (onde 123 é o ID do pedido)
 *    - Campo no payload Asaas: payment.externalReference ou subscription.externalReference
 *    - Função de parsing: parseOrderIdFromAsaasReference()
 *    - Busca: SELECT * FROM orders WHERE id = ? (ID extraído do externalReference)
 *
 * 2. provider_payment_id (FALLBACK 1)
 *    - Campo no payload Asaas: payment.id
 *    - Campo na tabela orders: provider_payment_id
 *    - Função de busca: findOrderByProviderId()
 *    - Busca: SELECT * FROM orders WHERE provider_payment_id = ?
 *
 * 3. provider_subscription_id (FALLBACK 2 - apenas para assinaturas)
 *    - Campo no payload Asaas: subscription.id
 *    - Campo na tabela orders: provider_subscription_id
 *    - Função de busca: findOrderBySubscriptionId()
 *    - Busca: SELECT * FROM orders WHERE provider_subscription_id = ?
 *
 * IMPORTANTE: O externalReference é definido na criação do pedido via
 * buildAsaasExternalReference($orderId) que retorna "order:" . $orderId
 *
 * ============================================================================
 * FLUXO FINAL: PAGAMENTO CONFIRMADO → WEBHOOK → ORDER PAGO → TENANT CRIADO
 * ============================================================================
 *
 * 1. Cliente paga no Asaas (PIX, cartão, boleto)
 * 2. Asaas envia webhook para este endpoint com evento de pagamento confirmado
 * 3. Webhook valida token e identifica o pedido (via externalReference/provider_payment_id)
 * 4. Webhook atualiza order:
 *    - orders.status = 'paid'
 *    - orders.paid_at = data/hora do pagamento
 *    - orders.provider_payment_id = ID do pagamento no Asaas (se ainda não estava)
 * 5. Webhook dispara onboarding (se order.tenant_id estiver vazio):
 *    - TenantOnboardingService::onPaidOrderCreateTenantAndSendActivation()
 *    - Cria registro em tenants
 *    - Cria usuário admin em usuarios
 *    - Cria tenant_settings e tenant_domains
 *    - Vincula tenant_id ao pedido
 *    - Envia/loga e-mail de ativação com link de ativação
 *
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../master/utils.php';
require_once __DIR__ . '/../../master/includes/OrderService.php';
require_once __DIR__ . '/../../master/includes/TenantOnboardingService.php';
require_once __DIR__ . '/../../master/includes/AsaasPaymentService.php';
require_once __DIR__ . '/../../master/includes/AsaasBillingService.php';
require_once __DIR__ . '/../../master/includes/AsaasConfig.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Normaliza o payload recebido do Asaas.
 *
 * @return array<string,mixed>
 */
function readWebhookPayload(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Recupera o valor de um header HTTP sem considerar case.
 *
 * @param string $name
 * @return string|null
 */
function getHeaderValue(string $name): ?string
{
    $target = strtolower($name);
    $headers = [];

    if (function_exists('getallheaders')) {
        $headers = getallheaders() ?: [];
    }

    if (empty($headers)) {
        foreach ($_SERVER as $key => $value) {
            if (stripos($key, 'HTTP_') === 0) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }
    } else {
        $normalized = [];
        foreach ($headers as $headerName => $value) {
            $normalized[strtolower((string)$headerName)] = $value;
        }
        $headers = $normalized;
    }

    return isset($headers[$target]) ? trim((string)$headers[$target]) : null;
}

/**
 * Determina se o evento/estado recebido indica pagamento confirmado.
 *
 * @param string $event
 * @param string $status
 * @return bool
 */
function isPaidNotification(string $event, string $status): bool
{
    $paidEvents = [
        'PAYMENT_CONFIRMED',
        'PAYMENT_RECEIVED',
        'PAYMENT_RECEIVED_IN_CASH',
        'PAYMENT_RECEIVED_AFTER_DUE_DATE',
    ];

    $paidStatuses = [
        'RECEIVED',
        'CONFIRMED',
        'PAID',
    ];

    if ($event !== '' && in_array(strtoupper($event), $paidEvents, true)) {
        return true;
    }

    if ($status !== '' && in_array(strtoupper($status), $paidStatuses, true)) {
        return true;
    }

    return false;
}

try {
    // ========================================================================
    // VALIDAÇÃO DO TOKEN DO WEBHOOK
    // ========================================================================
    // Lê o token esperado: primeiro de ASAAS_WEBHOOK_TOKEN (variável de ambiente),
    // depois de config/asaas.php['webhook_token'] (fallback para desenvolvimento local)
    $expectedToken = getAsaasWebhookToken();
    $providedToken = getHeaderValue('asaas-access-token');

    if (!$providedToken || !hash_equals($expectedToken, $providedToken)) {
        error_log('[webhook.asaas.invalid_token] Token inválido ou ausente. Header recebido: ' . ($providedToken ? 'presente' : 'ausente'));
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token inválido.']);
        exit;
    }

    // ========================================================================
    // LEITURA DO PAYLOAD
    // ========================================================================
    $payload = readWebhookPayload();

    if (empty($payload)) {
        error_log('[webhook.asaas.received] Payload vazio recebido.');
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;
    }

    // ========================================================================
    // EXTRAÇÃO DE DADOS DO PAYLOAD
    // ========================================================================
    // Pode ser notificação de payment ou subscription
    $paymentData = is_array($payload['payment'] ?? null) ? $payload['payment'] : null;
    $subscriptionData = is_array($payload['subscription'] ?? null) ? $payload['subscription'] : null;
    
    // Se não tem payment nem subscription, tenta usar o payload direto
    if (!$paymentData && !$subscriptionData) {
        $paymentData = $payload;
    }

    $eventType = (string)($payload['event'] ?? '');
    $providerPaymentId = $paymentData['id'] ?? $paymentData['provider_payment_id'] ?? null;
    $providerSubscriptionId = $subscriptionData['id'] ?? $subscriptionData['provider_subscription_id'] ?? null;
    $status = (string)($paymentData['status'] ?? $subscriptionData['status'] ?? '');
    $externalReference = $paymentData['externalReference'] ?? $subscriptionData['externalReference'] ?? null;

    // Log do evento recebido (sem dados sensíveis)
    $paymentId = $providerPaymentId ?? $providerSubscriptionId ?? 'N/A';
    error_log(sprintf(
        '[webhook.asaas.received] Evento: %s | Payment/Subscription ID: %s',
        $eventType ?: 'UNKNOWN',
        is_string($paymentId) ? substr($paymentId, 0, 50) : 'N/A'
    ));

    // ========================================================================
    // IDENTIFICAÇÃO DO PEDIDO (ORDEM DE PRIORIDADE)
    // ========================================================================
    // 1. Via externalReference (formato: "order:123")
    $order = null;
    $parsedOrderId = parseOrderIdFromAsaasReference(is_string($externalReference) ? $externalReference : null);

    if ($parsedOrderId !== null) {
        $order = fetch('SELECT * FROM orders WHERE id = ? LIMIT 1', [$parsedOrderId]);
        if ($order) {
            error_log(sprintf('[webhook.asaas] Pedido encontrado via externalReference: order_id=%d', $parsedOrderId));
        }
    }

    // 2. Via provider_payment_id (fallback)
    if (!$order && $providerPaymentId) {
        $order = findOrderByProviderId((string)$providerPaymentId);
        if ($order) {
            error_log(sprintf('[webhook.asaas] Pedido encontrado via provider_payment_id: order_id=%d', $order['id'] ?? 0));
        }
    }

    // 3. Via provider_subscription_id (fallback para assinaturas)
    if (!$order && $providerSubscriptionId) {
        $order = findOrderBySubscriptionId((string)$providerSubscriptionId);
        if ($order) {
            error_log(sprintf('[webhook.asaas] Pedido encontrado via provider_subscription_id: order_id=%d', $order['id'] ?? 0));
        }
    }

    if (!$order) {
        error_log(sprintf(
            '[webhook.asaas] Pedido não encontrado. externalReference=%s | providerPaymentId=%s | providerSubscriptionId=%s',
            $externalReference ?? 'null',
            $providerPaymentId ?? 'null',
            $providerSubscriptionId ?? 'null'
        ));
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;
    }

    $orderId = (int)($order['id'] ?? 0);

    // ========================================================================
    // VERIFICAÇÃO DE STATUS (EVITAR PROCESSAMENTO DUPLICADO)
    // ========================================================================
    if (($order['status'] ?? '') === 'paid') {
        error_log(sprintf('[webhook.asaas] Pedido #%d já está pago. Ignorando webhook.', $orderId));
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Pedido já processado.']);
        exit;
    }

    // ========================================================================
    // VERIFICAÇÃO SE É NOTIFICAÇÃO DE PAGAMENTO CONFIRMADO
    // ========================================================================
    if (!isPaidNotification($eventType, $status)) {
        error_log(sprintf(
            '[webhook.asaas] Evento ignorado para pedido #%d. event=%s | status=%s',
            $orderId,
            $eventType,
            $status
        ));
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;
    }

    // ========================================================================
    // ATUALIZAÇÃO DO PEDIDO PARA STATUS "PAID"
    // ========================================================================
    // Determina data de pagamento (prioridade: confirmedDate > paymentDate > agora)
    $paidAt = $paymentData['confirmedDate'] ?? $paymentData['paymentDate'] ?? 
              $subscriptionData['confirmedDate'] ?? $subscriptionData['paymentDate'] ?? 
              date('Y-m-d H:i:s');

    // Normaliza formato de data se necessário
    if (is_string($paidAt) && strpos($paidAt, 'T') !== false) {
        $paidAt = date('Y-m-d H:i:s', strtotime($paidAt));
    }

    // Prepara dados para atualização
    $updateData = [
        'paid_at' => $paidAt,
    ];

    if ($providerPaymentId) {
        $updateData['provider_payment_id'] = (string)$providerPaymentId;
    }

    if ($providerSubscriptionId) {
        $updateData['provider_subscription_id'] = (string)$providerSubscriptionId;
        $updateData['subscription_status'] = strtolower($status);
    }

    // Marca pedido como pago (atualiza: status='paid', paid_at, provider_payment_id)
    $updatedOrder = markOrderAsPaid($orderId, $updateData);

    error_log(sprintf(
        '[webhook.asaas.processed] Pedido #%d marcado como pago. paid_at=%s | provider_payment_id=%s',
        $orderId,
        $paidAt,
        $providerPaymentId ?? 'N/A'
    ));

    // ========================================================================
    // DISPARO DO ONBOARDING (CRIAÇÃO DO TENANT)
    // ========================================================================
    // Dispara onboarding apenas se ainda não foi criado o tenant
    if (empty($updatedOrder['tenant_id'])) {
        try {
            onPaidOrderCreateTenantAndSendActivation($orderId);
            error_log(sprintf(
                '[webhook.asaas.processed] Onboarding executado para pedido #%d. Tenant criado e e-mail de ativação enviado/logado.',
                $orderId
            ));
        } catch (Throwable $onboardingError) {
            // Log do erro mas não interrompe o fluxo (pedido já está marcado como pago)
            error_log(sprintf(
                '[webhook.asaas.error] Falha no onboarding para pedido #%d: %s',
                $orderId,
                $onboardingError->getMessage()
            ));
        }
    } else {
        error_log(sprintf(
            '[webhook.asaas.processed] Pedido #%d já possui tenant_id=%d. Onboarding não será executado novamente.',
            $orderId,
            $updatedOrder['tenant_id']
        ));
    }

    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    // Log de erro sem expor dados sensíveis
    $errorMessage = $e->getMessage();
    $errorTrace = substr($e->getTraceAsString(), 0, 500);
    error_log(sprintf(
        '[webhook.asaas.error] Erro ao processar webhook: %s | Trace: %s',
        $errorMessage,
        $errorTrace
    ));
    http_response_code(500);
    echo json_encode(['success' => false]);
}

