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
            'plan_code' => $data['plan_code'],
            'billing_cycle' => $data['billing_cycle'],
            'total_amount' => $data['total_amount'],
            'max_installments' => $data['max_installments'] ?? 1,
            'status' => $data['status'] ?? 'pending',
            'payment_provider' => $data['payment_provider'],
            'provider_payment_id' => $data['provider_payment_id'] ?? null,
            'payment_url' => $data['payment_url'] ?? null,
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

        if (isset($gatewayData['payment_url'])) {
            $fields['payment_url'] = $gatewayData['payment_url'];
        }

        if (isset($gatewayData['status'])) {
            $fields['status'] = $gatewayData['status'];
        }

        if (isset($gatewayData['max_installments'])) {
            $fields['max_installments'] = (int)$gatewayData['max_installments'];
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

