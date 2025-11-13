<?php
/**
 * API - Criação de pedidos vindos da página de vendas.
 *
 * TODO: ao integrar com o gateway definitivo (ex.: Asaas), substituir o stub
 * createPaymentOnAsaas() por uma chamada real reaproveitando helpers globais.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../master/utils.php';
require_once __DIR__ . '/../../master/includes/OrderService.php';
require_once __DIR__ . '/../../master/includes/PlanService.php';
require_once __DIR__ . '/../../master/includes/AsaasPaymentService.php';

header('Content-Type: application/json; charset=utf-8');

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

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '', true);
        return is_array($decoded) ? $decoded : [];
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

    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }

    $whatsapp = isset($input['customer_whatsapp'])
        ? preg_replace('/\D+/', '', (string)$input['customer_whatsapp'])
        : null;

    $maxInstallments = (int)($input['max_installments'] ?? 1);
    if ($maxInstallments <= 0) {
        $maxInstallments = 1;
    }

    return [
        'customer_name' => $name,
        'customer_email' => $email,
        'customer_whatsapp' => $whatsapp !== '' ? $whatsapp : null,
        'plan_code' => $planCode,
        'max_installments' => $maxInstallments,
        'payment_provider' => $input['payment_provider'] ?? 'asaas',
    ];
}

try {
    $payload = readRequestPayload();
    $validated = validateOrderInput($payload);

    $plan = getPlanByCode($validated['plan_code']);

    if (!$plan || (int)$plan['is_active'] !== 1) {
        throw new InvalidArgumentException('Plano informado não está disponível.');
    }

    $validated['billing_cycle'] = $plan['billing_cycle'];
    $validated['total_amount'] = (float)$plan['total_amount'];
    $validated['price_per_month'] = (float)$plan['price_per_month'];
    $validated['months'] = (int)$plan['months'];

    $order = createOrderFromCheckout($validated);

    $customerPayload = [
        'name' => $validated['customer_name'],
        'email' => $validated['customer_email'],
        'mobile_phone' => $validated['customer_whatsapp'],
        'max_installments' => $validated['max_installments'],
    ];

    try {
        $gatewayResponse = createPaymentOnAsaas($order, $plan, $customerPayload);
    } catch (Throwable $asaasError) {
        error_log('[asaas.payment.error] Falha ao criar cobrança: ' . $asaasError->getMessage());
        throw new RuntimeException('Não foi possível gerar o link de pagamento. Tente novamente em instantes.');
    }

    updateOrderPaymentData((int)$order['id'], [
        'provider_payment_id' => $gatewayResponse['provider_payment_id'] ?? null,
        'payment_url' => $gatewayResponse['payment_url'] ?? null,
        'status' => 'pending',
        'max_installments' => $validated['max_installments'],
    ]);

    $order = fetch('SELECT * FROM orders WHERE id = ?', [$order['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Pedido criado com sucesso.',
        'order_id' => $order['id'] ?? null,
        'payment_url' => $order['payment_url'] ?? null,
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
} catch (RuntimeException $e) {
    http_response_code(502);
    error_log('[orders.create] Falha na integração Asaas: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[orders.create] Erro interno: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível criar o pedido.',
    ]);
}

