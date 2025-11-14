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

    if (!empty($errors)) {
        throw new InvalidArgumentException('Dados incompletos para criar o pedido.');
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

    error_log('[orders.create] payload: plan_code=' . ($payload['plan_code'] ?? 'null') . ' email=' . ($payload['customer_email'] ?? 'null'));

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

    $validated['billing_cycle'] = $plan['billing_cycle'];
    $validated['total_amount'] = (float)$plan['total_amount'];
    $validated['price_per_month'] = (float)$plan['price_per_month'];
    $validated['months'] = (int)$plan['months'];

    $order = createOrderFromCheckout($validated);
    $orderId = (int)($order['id'] ?? 0);
    $planCode = $validated['plan_code'];

    error_log('[orders.create] pedido criado ID=' . $orderId . ' para plan_code=' . $planCode);

    $customerPayload = [
        'name' => $validated['customer_name'],
        'email' => $validated['customer_email'],
        'mobile_phone' => $validated['customer_whatsapp'],
        'max_installments' => $validated['max_installments'],
    ];

    try {
        $gatewayResponse = createPaymentOnAsaas($order, $plan, $customerPayload);
    } catch (Throwable $asaasError) {
        $errorMessage = $asaasError->getMessage();
        error_log(sprintf(
            '[orders.create.error] Falha ao criar cobrança no Asaas: %s | orderId=%d | Trace: %s',
            $errorMessage,
            $orderId,
            substr($asaasError->getTraceAsString(), 0, 500)
        ));
        
        // Propaga a mensagem de erro do Asaas se for útil, senão usa genérica
        if (empty($errorMessage) || strlen(trim($errorMessage)) < 5) {
            $errorMessage = 'Não foi possível gerar o link de pagamento. Tente novamente em alguns instantes.';
        }
        
        throw new RuntimeException($errorMessage, 0, $asaasError);
    }

    updateOrderPaymentData($orderId, [
        'provider_payment_id' => $gatewayResponse['provider_payment_id'] ?? null,
        'payment_url' => $gatewayResponse['payment_url'] ?? null,
        'status' => 'pending',
        'max_installments' => $validated['max_installments'],
    ]);

    $order = fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
    $paymentUrl = $order['payment_url'] ?? ($gatewayResponse['payment_url'] ?? null);

    echo json_encode([
        'success' => true,
        'order_id' => $order['id'] ?? null,
        'payment_url' => $paymentUrl,
    ]);
    exit;
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
        $errorMessage = 'Não foi possível gerar o link de pagamento. Tente novamente em alguns instantes.';
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

