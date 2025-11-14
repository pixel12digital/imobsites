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

        $payload = [
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_whatsapp' => $data['customer_whatsapp'] ?? null,
            'customer_cpf_cnpj' => isset($data['customer_cpf_cnpj']) ? preg_replace('/\D+/', '', (string)$data['customer_cpf_cnpj']) : null,
            'plan_code' => $data['plan_code'],
            'billing_cycle' => $data['billing_cycle'],
            'total_amount' => $data['total_amount'],
            'max_installments' => $data['max_installments'] ?? 1,
            'payment_method' => $data['payment_method'] ?? null,
            'payment_installments' => $data['payment_installments'] ?? 1,
            'status' => $data['status'] ?? 'pending',
            'payment_provider' => $data['payment_provider'] ?? 'asaas',
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

        $orderId = insert('orders', $payload);

        return fetch('SELECT * FROM orders WHERE id = ?', [$orderId]) ?: [];
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

