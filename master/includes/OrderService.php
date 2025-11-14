<?php
/**
 * OrderService
 *
 * NOTE: Reutiliza helpers globais definidos em config/database.php:
 * - insert()
 * - update()
 * - fetch()
 * - fetchAll()
 * Certifique-se de incluir config/database.php antes de carregar este arquivo.
 */

if (!function_exists('createOrderFromCheckout')) {
    /**
     * Persiste um novo pedido gerado pelo checkout da página de vendas.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    function createOrderFromCheckout(array $data): array
    {
        $now = date('Y-m-d H:i:s');

        // Validação de campos obrigatórios
        $requiredFields = ['customer_name', 'customer_email', 'plan_code', 'billing_cycle', 'total_amount', 'payment_provider'];
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            error_log(sprintf(
                '[orders.create.error] Campos obrigatórios ausentes: %s | plan_code=%s | customer_email=%s',
                implode(', ', $missingFields),
                $data['plan_code'] ?? 'NULL',
                $data['customer_email'] ?? 'NULL'
            ));
            throw new InvalidArgumentException('Campos obrigatórios ausentes: ' . implode(', ', $missingFields));
        }

        // Garante que total_amount seja um número válido
        $totalAmount = isset($data['total_amount']) ? (float)$data['total_amount'] : 0.0;
        if ($totalAmount < 0) {
            $totalAmount = 0.0;
        }

        $payload = [
            'customer_name' => trim((string)$data['customer_name']),
            'customer_email' => strtolower(trim((string)$data['customer_email'])),
            'customer_whatsapp' => $data['customer_whatsapp'] ?? null,
            'customer_cpf_cnpj' => isset($data['customer_cpf_cnpj']) ? preg_replace('/\D+/', '', (string)$data['customer_cpf_cnpj']) : null,
            'plan_code' => strtoupper(trim((string)$data['plan_code'])),
            'billing_cycle' => strtolower(trim((string)$data['billing_cycle'])),
            'total_amount' => $totalAmount,
            'max_installments' => isset($data['max_installments']) ? max(1, (int)$data['max_installments']) : 1,
            'payment_method' => $data['payment_method'] ?? null,
            'payment_installments' => isset($data['payment_installments']) ? max(1, (int)$data['payment_installments']) : 1,
            'status' => $data['status'] ?? 'pending',
            'payment_provider' => strtolower(trim((string)($data['payment_provider'] ?? 'asaas'))),
            'provider_payment_id' => $data['provider_payment_id'] ?? null,
            'provider_subscription_id' => $data['provider_subscription_id'] ?? null,
            'subscription_status' => $data['subscription_status'] ?? null,
            'payment_url' => $data['payment_url'] ?? null,
            'pix_payload' => $data['pix_payload'] ?? null,
            'pix_qr_code_image' => $data['pix_qr_code_image'] ?? null,
            'boleto_url' => $data['boleto_url'] ?? null,
            'boleto_barcode' => $data['boleto_barcode'] ?? null,
            'asaas_customer_id' => $data['asaas_customer_id'] ?? null,
            'paid_at' => null,
            'tenant_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Log dos dados principais antes de inserir (sem dados sensíveis)
        error_log(sprintf(
            '[orders.create] Tentando inserir pedido: plan_code=%s | customer_email=%s | total_amount=%s | billing_cycle=%s | payment_method=%s',
            $payload['plan_code'],
            $payload['customer_email'],
            $payload['total_amount'],
            $payload['billing_cycle'],
            $payload['payment_method'] ?? 'NULL'
        ));

        $orderId = insert('orders', $payload);

        if ($orderId === false || $orderId === 0) {
            error_log(sprintf(
                '[orders.create.error] Falha ao inserir pedido no banco | plan_code=%s | customer_email=%s | total_amount=%s | billing_cycle=%s | Verifique logs anteriores para detalhes SQL',
                $payload['plan_code'],
                $payload['customer_email'],
                $payload['total_amount'],
                $payload['billing_cycle']
            ));
            throw new RuntimeException('Falha ao criar o pedido no banco de dados.');
        }

        $order = fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        
        if (!$order) {
            error_log(sprintf(
                '[orders.create.error] Pedido inserido (ID=%d) mas não encontrado ao buscar | plan_code=%s',
                $orderId,
                $payload['plan_code']
            ));
            throw new RuntimeException('Pedido criado mas não foi possível recuperar os dados.');
        }

        return $order;
    }
}

if (!function_exists('updateOrderPaymentData')) {
    /**
     * Atualiza informações retornadas pelo gateway após criar a cobrança.
     *
     * @param int $orderId
     * @param array<string,mixed> $gatewayData
     * @return void
     */
    function updateOrderPaymentData(int $orderId, array $gatewayData): void
    {
        $fields = [];

        if (isset($gatewayData['provider_payment_id'])) {
            $fields['provider_payment_id'] = $gatewayData['provider_payment_id'];
        }

        if (isset($gatewayData['provider_subscription_id'])) {
            $fields['provider_subscription_id'] = $gatewayData['provider_subscription_id'];
        }

        if (isset($gatewayData['subscription_status'])) {
            $fields['subscription_status'] = $gatewayData['subscription_status'];
        }

        if (isset($gatewayData['payment_url'])) {
            $fields['payment_url'] = $gatewayData['payment_url'];
        }

        if (isset($gatewayData['pix_payload'])) {
            $fields['pix_payload'] = $gatewayData['pix_payload'];
        }

        if (isset($gatewayData['pix_qr_code_image'])) {
            $fields['pix_qr_code_image'] = $gatewayData['pix_qr_code_image'];
        }

        if (isset($gatewayData['boleto_url'])) {
            $fields['boleto_url'] = $gatewayData['boleto_url'];
        }

        if (isset($gatewayData['boleto_barcode'])) {
            $fields['boleto_barcode'] = $gatewayData['boleto_barcode'];
        }

        if (isset($gatewayData['asaas_customer_id'])) {
            $fields['asaas_customer_id'] = $gatewayData['asaas_customer_id'];
        }

        if (isset($gatewayData['status'])) {
            $fields['status'] = $gatewayData['status'];
        }

        if (isset($gatewayData['max_installments'])) {
            $fields['max_installments'] = (int)$gatewayData['max_installments'];
        }

        if (isset($gatewayData['payment_installments'])) {
            $fields['payment_installments'] = (int)$gatewayData['payment_installments'];
        }

        if (empty($fields)) {
            return;
        }

        $fields['updated_at'] = date('Y-m-d H:i:s');

        update('orders', $fields, 'id = ?', [$orderId]);
    }
}

if (!function_exists('markOrderAsPaid')) {
    /**
     * Marca o pedido como pago e retorna o registro atualizado.
     *
     * @param int $orderId
     * @param array<string,mixed> $gatewayData
     * @return array<string,mixed>
     */
    function markOrderAsPaid(int $orderId, array $gatewayData = []): array
    {
        $fields = [
            'status' => 'paid',
            'paid_at' => isset($gatewayData['paid_at']) ? $gatewayData['paid_at'] : date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($gatewayData['provider_payment_id'])) {
            $fields['provider_payment_id'] = $gatewayData['provider_payment_id'];
        }

        update('orders', $fields, 'id = ?', [$orderId]);

        return fetch('SELECT * FROM orders WHERE id = ?', [$orderId]) ?: [];
    }
}

if (!function_exists('attachTenantToOrder')) {
    /**
     * Relaciona o tenant recém-criado ao pedido pago.
     *
     * @param int $orderId
     * @param int $tenantId
     * @return void
     */
    function attachTenantToOrder(int $orderId, int $tenantId): void
    {
        update('orders', [
            'tenant_id' => $tenantId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$orderId]);
    }
}

if (!function_exists('findOrderByProviderId')) {
    /**
     * Recupera um pedido a partir do identificador retornado pelo gateway.
     *
     * @param string $providerPaymentId
     * @return array<string,mixed>|null
     */
    function findOrderByProviderId(string $providerPaymentId): ?array
    {
        if ($providerPaymentId === '') {
            return null;
        }

        $order = fetch('SELECT * FROM orders WHERE provider_payment_id = ? LIMIT 1', [$providerPaymentId]);

        return $order ?: null;
    }
}

if (!function_exists('findOrderBySubscriptionId')) {
    /**
     * Recupera um pedido a partir do ID de assinatura do gateway.
     *
     * @param string $providerSubscriptionId
     * @return array<string,mixed>|null
     */
    function findOrderBySubscriptionId(string $providerSubscriptionId): ?array
    {
        if ($providerSubscriptionId === '') {
            return null;
        }

        $order = fetch('SELECT * FROM orders WHERE provider_subscription_id = ? LIMIT 1', [$providerSubscriptionId]);

        return $order ?: null;
    }
}

