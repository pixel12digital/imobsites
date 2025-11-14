<?php
/**
 * API - Criação de pedidos vindos da página de vendas.
 *
 * Suporta dois modelos de billing:
 * - recurring_monthly: Assinatura mensal recorrente (cartão de crédito)
 * - prepaid_parceled: Cobrança pré-paga parcelada (cartão, Pix, boleto)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../master/utils.php';
require_once __DIR__ . '/../../master/includes/OrderService.php';
require_once __DIR__ . '/../../master/includes/PlanService.php';
require_once __DIR__ . '/../../master/includes/AsaasBillingService.php';
require_once __DIR__ . '/../../master/includes/NotificationService.php';

// CORS para permitir o checkout do domínio público
$allowedOrigin = 'https://imobsites.com.br';

if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
} else {
    // Se preferir liberar tudo durante desenvolvimento, usar:
    // header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Resposta imediata para preflight CORS
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

error_log('[orders.create] endpoint chamado - method=' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não suportado. Utilize POST.',
    ]);
    exit;
}

/**
 * Lê o corpo da requisição aceitando JSON ou form-data.
 *
 * @return array<string,mixed>
 */
function readRequestPayload(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        return $decoded;
    }

    if (stripos($contentType, 'application/json') !== false) {
        error_log('[orders.create] payload inválido: JSON malformado ou vazio');
        if (!empty($_POST)) {
            return $_POST;
        }
        return [];
    }

    return $_POST;
}

/**
 * Valida e normaliza os campos de entrada.
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>
 */
function validateOrderInput(array $input): array
{
    $errors = [];

    $name = trim((string)($input['customer_name'] ?? ''));
    if ($name === '') {
        $errors[] = 'Informe o nome do cliente.';
    }

    $email = strtolower(trim((string)($input['customer_email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido.';
    }

    $planCode = strtoupper(trim((string)($input['plan_code'] ?? '')));
    if ($planCode === '') {
        $errors[] = 'Informe o código do plano.';
    }

    $paymentMethod = strtolower(trim((string)($input['payment_method'] ?? '')));
    $validPaymentMethods = ['credit_card', 'pix', 'boleto'];
    if ($paymentMethod === '' || !in_array($paymentMethod, $validPaymentMethods, true)) {
        $errors[] = 'Informe um método de pagamento válido (credit_card, pix ou boleto).';
    }

    // Validação de dados do cartão (quando necessário)
    if ($paymentMethod === 'credit_card') {
        // Aceita tanto 'card' quanto 'card_data' para compatibilidade
        $cardData = $input['card'] ?? $input['card_data'] ?? [];
        
        // Normaliza campos do cartão (aceita tanto 'number' quanto 'cardNumber')
        if (isset($cardData['number']) && !isset($cardData['cardNumber'])) {
            $cardData['cardNumber'] = $cardData['number'];
        }
        
        if (empty($cardData['cardNumber']) || empty($cardData['holderName']) || 
            empty($cardData['expiryMonth']) || empty($cardData['expiryYear']) || 
            empty($cardData['ccv'])) {
            $errors[] = 'Dados do cartão de crédito incompletos.';
        }
    }

    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }

    $whatsapp = isset($input['customer_whatsapp'])
        ? preg_replace('/\D+/', '', (string)$input['customer_whatsapp'])
        : null;

    $cpfCnpj = isset($input['customer_cpf_cnpj'])
        ? preg_replace('/\D+/', '', (string)$input['customer_cpf_cnpj'])
        : null;

    $maxInstallments = (int)($input['max_installments'] ?? 1);
    if ($maxInstallments <= 0) {
        $maxInstallments = 1;
    }

    $paymentInstallments = (int)($input['payment_installments'] ?? 1);
    if ($paymentInstallments <= 0) {
        $paymentInstallments = 1;
    }

    return [
        'customer_name' => $name,
        'customer_email' => $email,
        'customer_whatsapp' => $whatsapp !== '' ? $whatsapp : null,
        'customer_cpf_cnpj' => $cpfCnpj !== '' ? $cpfCnpj : null,
        'plan_code' => $planCode,
        'payment_method' => $paymentMethod,
        'payment_installments' => $paymentInstallments,
        'max_installments' => $maxInstallments,
        'card_data' => $paymentMethod === 'credit_card' ? ($input['card'] ?? $input['card_data'] ?? []) : null,
        'payment_provider' => $input['payment_provider'] ?? 'asaas',
    ];
}

try {
    $payload = readRequestPayload();

    error_log('[orders.create] payload: plan_code=' . ($payload['plan_code'] ?? 'null') . ' email=' . ($payload['customer_email'] ?? 'null') . ' method=' . ($payload['payment_method'] ?? 'null'));

    if (!is_array($payload) || $payload === []) {
        error_log('[orders.create] payload inválido: estrutura não é um array');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Dados incompletos para criar o pedido.',
        ]);
        exit;
    }

    $validated = validateOrderInput($payload);

    $plan = getPlanByCode($validated['plan_code']);

    if (!$plan || (int)$plan['is_active'] !== 1) {
        error_log('[orders.create] plano não encontrado: ' . $validated['plan_code']);
        throw new InvalidArgumentException('Plano selecionado não foi encontrado. Atualize a página e tente novamente.');
    }

    // Determina billing_mode do plano (padrão: prepaid_parceled)
    $billingMode = strtolower((string)($plan['billing_mode'] ?? 'prepaid_parceled'));
    if (!in_array($billingMode, ['recurring_monthly', 'prepaid_parceled'], true)) {
        $billingMode = 'prepaid_parceled';
    }

    // Valida método de pagamento conforme billing_mode
    if ($billingMode === 'recurring_monthly' && $validated['payment_method'] !== 'credit_card') {
        throw new InvalidArgumentException('Assinaturas recorrentes suportam apenas cartão de crédito.');
    }

    // Valida parcelas conforme plano
    $planMaxInstallments = (int)($plan['max_installments'] ?? 1);
    if ($validated['payment_installments'] > $planMaxInstallments) {
        $validated['payment_installments'] = $planMaxInstallments;
    }

    $validated['billing_cycle'] = $plan['billing_cycle'];
    $validated['total_amount'] = (float)$plan['total_amount'];
    $validated['price_per_month'] = (float)$plan['price_per_month'];
    $validated['months'] = (int)$plan['months'];

    // Cria o pedido no banco
    $order = createOrderFromCheckout($validated);
    $orderId = (int)($order['id'] ?? 0);

    if ($orderId === 0) {
        throw new RuntimeException('Falha ao criar o pedido no banco de dados.');
    }

    error_log('[orders.create] pedido criado ID=' . $orderId . ' para plan_code=' . $validated['plan_code'] . ' billing_mode=' . $billingMode);
    error_log('[orders.create] DEBUG order após criação: customer_cpf_cnpj=' . ($order['customer_cpf_cnpj'] ?? 'NULL'));

    try {
        // Garante que existe customer no Asaas
        $customerId = ensureCustomerForOrder($order);

        // Cria cobrança ou assinatura conforme billing_mode
        if ($billingMode === 'recurring_monthly') {
            // Modelo A: Assinatura recorrente
            $gatewayResponse = createRecurringSubscription(
                $order,
                $plan,
                $customerId,
                $validated['payment_method'],
                $validated['card_data']
            );

            // Atualiza pedido com dados da assinatura
            updateOrderPaymentData($orderId, [
                'provider_subscription_id' => $gatewayResponse['provider_subscription_id'] ?? null,
                'subscription_status' => $gatewayResponse['subscription_status'] ?? null,
                'asaas_customer_id' => $customerId,
                'status' => 'pending',
            ]);

            // Envia notificação de pedido criado (não quebra o fluxo em caso de falha)
            try {
                $notificationService = new NotificationService($pdo);
                $notificationService->sendOrderCreatedNotifications($orderId);
            } catch (Throwable $e) {
                error_log('[notification.order_created.error] Falha ao enviar notificação para pedido ' . $orderId . ': ' . $e->getMessage());
            }

            $responseData = [
                'success' => true,
                'order_id' => $orderId,
                'type' => 'subscription',
                'subscription_id' => $gatewayResponse['provider_subscription_id'] ?? null,
                'status' => $gatewayResponse['subscription_status'] ?? 'pending',
                'next_due_date' => $gatewayResponse['next_due_date'] ?? null,
                'message' => 'Assinatura criada com sucesso. O pagamento será processado automaticamente.',
            ];

            // Se o status já for 'paid' ou 'confirmed', marca como pago
            if (in_array(strtolower($responseData['status']), ['paid', 'confirmed', 'active'], true)) {
                markOrderAsPaid($orderId, [
                    'provider_subscription_id' => $gatewayResponse['provider_subscription_id'] ?? null,
                ]);
                $responseData['status'] = 'paid';
            }

        } else {
            // Modelo B: Cobrança pré-paga
            $gatewayResponse = createPrepaidPayment(
                $order,
                $plan,
                $customerId,
                $validated['payment_method'],
                $validated['card_data']
            );

            // Atualiza pedido com dados do pagamento
            $updateData = [
                'provider_payment_id' => $gatewayResponse['provider_payment_id'] ?? null,
                'asaas_customer_id' => $customerId,
                'status' => $gatewayResponse['status'] ?? 'pending',
                'payment_installments' => $validated['payment_installments'],
            ];

            if (isset($gatewayResponse['payment_url'])) {
                $updateData['payment_url'] = $gatewayResponse['payment_url'];
            }

            if (isset($gatewayResponse['pix_payload'])) {
                $updateData['pix_payload'] = $gatewayResponse['pix_payload'];
            }

            if (isset($gatewayResponse['pix_qr_code_image'])) {
                $updateData['pix_qr_code_image'] = $gatewayResponse['pix_qr_code_image'];
            }

            if (isset($gatewayResponse['boleto_url'])) {
                $updateData['boleto_url'] = $gatewayResponse['boleto_url'];
            }

            if (isset($gatewayResponse['boleto_barcode'])) {
                $updateData['boleto_barcode'] = $gatewayResponse['boleto_barcode'];
            }

            if (isset($gatewayResponse['boleto_line'])) {
                $updateData['boleto_line'] = $gatewayResponse['boleto_line'];
            }

            updateOrderPaymentData($orderId, $updateData);

            // Envia notificação de pedido criado (não quebra o fluxo em caso de falha)
            try {
                $notificationService = new NotificationService($pdo);
                $notificationService->sendOrderCreatedNotifications($orderId);
            } catch (Throwable $e) {
                error_log('[notification.order_created.error] Falha ao enviar notificação para pedido ' . $orderId . ': ' . $e->getMessage());
            }

            $responseData = [
                'success' => true,
                'order_id' => $orderId,
                'type' => 'payment',
                'payment_method' => $validated['payment_method'],
                'status' => $gatewayResponse['status'] ?? 'pending',
            ];

            // Busca dados atualizados do pedido para garantir que todos os campos estão na resposta
            $updatedOrder = fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
            
            // Adiciona URL de pagamento (sempre disponível)
            if (isset($gatewayResponse['payment_url'])) {
                $responseData['payment_url'] = $gatewayResponse['payment_url'];
            } elseif ($updatedOrder && isset($updatedOrder['payment_url'])) {
                $responseData['payment_url'] = $updatedOrder['payment_url'];
            }

            // Adiciona dados específicos conforme método de pagamento
            if ($validated['payment_method'] === 'pix') {
                $responseData['pix_payload'] = $gatewayResponse['pix_payload'] ?? $updatedOrder['pix_payload'] ?? null;
                $responseData['pix_qr_code_image'] = $gatewayResponse['pix_qr_code_image'] ?? $updatedOrder['pix_qr_code_image'] ?? null;
                
                // Ajusta mensagem conforme disponibilidade dos dados
                if ($responseData['pix_payload'] !== null) {
                    $responseData['message'] = 'Pagamento Pix gerado. Escaneie o QR code ou copie o código Pix.';
                } else {
                    $responseData['message'] = 'Pagamento Pix gerado. Acesse a URL de pagamento para visualizar o QR code.';
                }
            } elseif ($validated['payment_method'] === 'boleto') {
                $responseData['boleto_url'] = $gatewayResponse['boleto_url'] ?? $updatedOrder['boleto_url'] ?? null;
                $responseData['boleto_barcode'] = $gatewayResponse['boleto_barcode'] ?? $updatedOrder['boleto_barcode'] ?? null;
                $responseData['boleto_line'] = $gatewayResponse['boleto_line'] ?? $updatedOrder['boleto_line'] ?? null;
                
                // Ajusta mensagem conforme disponibilidade dos dados
                if ($responseData['boleto_line'] !== null) {
                    $responseData['message'] = 'Boleto gerado. Copie a linha digitável ou acesse o link para visualizar.';
                } else {
                    $responseData['message'] = 'Boleto gerado. Acesse o link para visualizar e pagar.';
                }
            } elseif ($validated['payment_method'] === 'credit_card') {
                if (in_array(strtolower($responseData['status']), ['paid', 'confirmed', 'received'], true)) {
                    markOrderAsPaid($orderId, [
                        'provider_payment_id' => $gatewayResponse['provider_payment_id'] ?? null,
                    ]);
                    $responseData['status'] = 'paid';
                    $responseData['message'] = 'Pagamento aprovado! Sua conta será ativada em breve.';
                } else {
                    $responseData['message'] = 'Pagamento processado. Aguardando confirmação.';
                }
            }
        }

        echo json_encode($responseData);
        exit;

    } catch (Throwable $asaasError) {
        $errorMessage = $asaasError->getMessage();
        error_log(sprintf(
            '[orders.create.error] Falha ao processar pagamento no Asaas: %s | orderId=%d | Trace: %s',
            $errorMessage,
            $orderId,
            substr($asaasError->getTraceAsString(), 0, 500)
        ));

        // Propaga a mensagem de erro do Asaas se for útil, senão usa genérica
        if (empty($errorMessage) || strlen(trim($errorMessage)) < 5) {
            $errorMessage = 'Não foi possível processar o pagamento. Tente novamente em alguns instantes.';
        }

        throw new RuntimeException($errorMessage, 0, $asaasError);
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    error_log('[orders.create] validação falhou: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    exit;
} catch (RuntimeException $e) {
    http_response_code(400);
    $errorMessage = $e->getMessage();
    error_log('[orders.create.error] ' . $errorMessage);

    // Garante que a mensagem não está vazia
    if (empty($errorMessage) || strlen(trim($errorMessage)) < 5) {
        $errorMessage = 'Não foi possível processar o pagamento. Tente novamente em alguns instantes.';
    }

    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    $errorMessage = $e->getMessage();
    error_log('[orders.create.error] Erro interno: ' . $errorMessage);

    // Para erros inesperados, usa mensagem genérica
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível criar o pedido. Tente novamente em alguns instantes.',
    ]);
    exit;
}
