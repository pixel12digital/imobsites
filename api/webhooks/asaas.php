<?php
/**
 * Webhook Asaas - processa notificações de pagamento.
 *
 * Valida o token configurado no header `asaas-access-token` para garantir
 * que apenas o Asaas acione este endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../master/utils.php';
require_once __DIR__ . '/../../master/includes/OrderService.php';
require_once __DIR__ . '/../../master/includes/TenantOnboardingService.php';
require_once __DIR__ . '/../../master/includes/AsaasPaymentService.php';
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
    $expectedToken = getAsaasWebhookToken();
    $providedToken = getHeaderValue('asaas-access-token');

    if (!$providedToken || !hash_equals($expectedToken, $providedToken)) {
        error_log('[webhook.asaas] token inválido ou ausente.');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token inválido.']);
        exit;
    }

    $payload = readWebhookPayload();

    if (empty($payload)) {
        error_log('[webhook.asaas] Payload vazio recebido.');
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;
    }

    error_log('[webhook.asaas.payload] ' . substr(json_encode($payload), 0, 2000));

    $eventType = (string)($payload['event'] ?? '');
    $paymentData = is_array($payload['payment'] ?? null) ? $payload['payment'] : $payload;

    $providerPaymentId = $paymentData['id'] ?? $paymentData['provider_payment_id'] ?? null;
    $status = (string)($paymentData['status'] ?? '');
    $externalReference = $paymentData['externalReference'] ?? null;

    $order = null;
    $parsedOrderId = parseOrderIdFromAsaasReference(is_string($externalReference) ? $externalReference : null);

    if ($parsedOrderId !== null) {
        $order = fetch('SELECT * FROM orders WHERE id = ? LIMIT 1', [$parsedOrderId]);
    }

    if (!$order && $providerPaymentId) {
        $order = findOrderByProviderId((string)$providerPaymentId);
    }

    if (!$order) {
        error_log('[webhook.asaas] Pedido não encontrado para externalReference=' . ($externalReference ?? 'null') . ' providerPaymentId=' . ($providerPaymentId ?? 'null'));
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;
    }

    if (($order['status'] ?? '') === 'paid') {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Pedido já processado.']);
        exit;
    }

    if (!isPaidNotification($eventType, $status)) {
        error_log(sprintf('[webhook.asaas] Evento %s / status %s ignorado para pedido #%d.', $eventType, $status, $order['id']));
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;
    }

    $paidAt = $paymentData['confirmedDate'] ?? $paymentData['paymentDate'] ?? date('Y-m-d H:i:s');

    $updatedOrder = markOrderAsPaid((int)$order['id'], [
        'provider_payment_id' => $providerPaymentId ? (string)$providerPaymentId : null,
        'paid_at' => $paidAt,
    ]);

    onPaidOrderCreateTenantAndSendActivation((int)$updatedOrder['id']);

    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[webhook.asaas] Erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false]);
}

