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
 *
 * ============================================================================
 * ESTRUTURA DA TABELA ORDERS - CAMPOS PRINCIPAIS PARA A TELA DE PEDIDOS
 * ============================================================================
 *
 * Campos principais exibidos na tela master/pedidos.php:
 *
 * - id: ID único do pedido
 * - tenant_id: ID do tenant/cliente vinculado (NULL se ainda não foi criado via webhook)
 * - plan_code: Código do plano (ex: "BASIC", "PREMIUM")
 * - payment_method: Método de pagamento ('credit_card', 'pix', 'boleto')
 * - status: Status do pedido ('pending', 'paid', 'canceled', 'expired')
 * - total_amount: Valor total do pedido (DECIMAL 10,2)
 * - payment_url: URL da página de pagamento no Asaas (quando disponível)
 * - provider_payment_id: ID do pagamento no Asaas (asaas_payment_id)
 * - provider_subscription_id: ID da assinatura no Asaas (se for assinatura recorrente)
 * - created_at: Data/hora de criação do pedido
 * - paid_at: Data/hora em que o pagamento foi confirmado (NULL se ainda não pago)
 *
 * Campos adicionais úteis:
 * - customer_name: Nome do cliente
 * - customer_email: E-mail do cliente
 * - customer_cpf_cnpj: CPF/CNPJ do cliente
 * - billing_cycle: Ciclo de cobrança ('mensal', 'trimestral', etc.)
 * - subscription_status: Status da assinatura (se aplicável)
 *
 * JOIN com tenants:
 * - Para exibir o nome do cliente quando tenant_id não for NULL, fazemos LEFT JOIN
 *   na tabela tenants usando orders.tenant_id = tenants.id
 *
 * ============================================================================
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

if (!function_exists('listOrders')) {
    /**
     * Lista pedidos com filtros e paginação.
     *
     * Filtros suportados:
     * - status: 'pending', 'paid', 'canceled', 'expired'
     * - payment_method: 'credit_card', 'pix', 'boleto'
     * - plan_code: código do plano (ex: 'BASIC', 'PREMIUM')
     * - q: busca textual (ID do pedido, nome do cliente, e-mail)
     * - date_from: data inicial (formato Y-m-d)
     * - date_to: data final (formato Y-m-d)
     *
     * @param array<string,mixed> $filters
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Itens por página (padrão: 20)
     * @return array<string,mixed> ['items' => array, 'pagination' => array]
     */
    function listOrders(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < 1) {
            $perPage = 20;
        }

        $offset = ($page - 1) * $perPage;

        // Monta as condições WHERE (reutilizável para contagem e listagem)
        $whereConditions = [];
        $params = [];

        // Filtro por status
        if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'paid', 'canceled', 'expired'], true)) {
            $whereConditions[] = "o.status = ?";
            $params[] = $filters['status'];
        }

        // Filtro por método de pagamento
        if (!empty($filters['payment_method']) && in_array($filters['payment_method'], ['credit_card', 'pix', 'boleto'], true)) {
            $whereConditions[] = "o.payment_method = ?";
            $params[] = $filters['payment_method'];
        }

        // Filtro por código do plano
        if (!empty($filters['plan_code'])) {
            $whereConditions[] = "o.plan_code = ?";
            $params[] = strtoupper(trim($filters['plan_code']));
        }

        // Busca textual (ID, nome do cliente, e-mail)
        if (!empty($filters['q'])) {
            $searchTerm = '%' . trim($filters['q']) . '%';
            $exactId = filter_var(trim($filters['q']), FILTER_VALIDATE_INT);
            if ($exactId !== false) {
                $whereConditions[] = "(o.id LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.id = ?)";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $exactId;
            } else {
                $whereConditions[] = "(o.id LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
        }

        // Filtro por intervalo de datas (created_at)
        if (!empty($filters['date_from'])) {
            $dateFrom = date('Y-m-d 00:00:00', strtotime($filters['date_from']));
            if ($dateFrom !== false) {
                $whereConditions[] = "o.created_at >= ?";
                $params[] = $dateFrom;
            }
        }

        if (!empty($filters['date_to'])) {
            $dateTo = date('Y-m-d 23:59:59', strtotime($filters['date_to']));
            if ($dateTo !== false) {
                $whereConditions[] = "o.created_at <= ?";
                $params[] = $dateTo;
            }
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Query para contar total
        $countSql = "
            SELECT COUNT(*) as total
            FROM orders o
            $whereClause
        ";
        $totalResult = fetch($countSql, $params);
        $totalItems = (int)($totalResult['total'] ?? 0);

        // Garante que perPage e offset são inteiros seguros
        $perPageInt = (int)$perPage;
        $offsetInt = (int)$offset;
        
        // Query para listar com JOIN em tenants e plans
        // LIMIT e OFFSET não podem ser binded como parâmetros no MySQL/MariaDB,
        // então usamos valores literais garantindo que sejam inteiros
        $sql = "
            SELECT 
                o.*,
                t.id as tenant_id,
                t.name as tenant_name,
                t.slug as tenant_slug,
                t.contact_email as tenant_email,
                p.name as plan_name
            FROM orders o
            LEFT JOIN tenants t ON o.tenant_id = t.id
            LEFT JOIN plans p ON o.plan_code = p.code
            $whereClause
            ORDER BY o.created_at DESC
            LIMIT $perPageInt OFFSET $offsetInt
        ";

        $items = fetchAll($sql, $params);

        $totalPages = $totalItems > 0 ? (int)ceil($totalItems / $perPage) : 0;

        return [
            'items' => $items ?: [],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
        ];
    }
}

if (!function_exists('cancelOrder')) {
    /**
     * Marca um pedido como cancelado internamente (não cancela no Asaas).
     * 
     * @param int $orderId ID do pedido
     * @param int|null $userId ID do usuário que está cancelando (opcional, para log)
     * @param string|null $reason Motivo do cancelamento (opcional)
     * @return bool True se cancelado com sucesso, false caso contrário
     */
    function cancelOrder(int $orderId, ?int $userId = null, ?string $reason = null): bool
    {
        // Buscar o pedido
        $order = fetch('SELECT id, status FROM orders WHERE id = ?', [$orderId]);
        
        if (!$order) {
            error_log(sprintf('[orders.cancel] Order não encontrado: ID=%d', $orderId));
            return false;
        }
        
        $currentStatus = $order['status'] ?? null;
        
        // Não permitir cancelar pedidos já pagos
        if ($currentStatus === 'paid') {
            error_log(sprintf('[orders.cancel] Tentativa de cancelar pedido pago: ID=%d | status=%s', $orderId, $currentStatus));
            return false;
        }
        
        // Se já estiver cancelado ou failed, considerar no-op
        if (in_array($currentStatus, ['canceled', 'expired'], true)) {
            error_log(sprintf('[orders.cancel] Pedido já está cancelado/expirado: ID=%d | status=%s', $orderId, $currentStatus));
            return true; // Retorna true pois já está no estado desejado
        }
        
        // Só permite cancelar pedidos pending ou outros status "abertos"
        if ($currentStatus !== 'pending') {
            error_log(sprintf('[orders.cancel] Status inválido para cancelamento: ID=%d | status=%s', $orderId, $currentStatus));
            return false;
        }
        
        // Preparar dados para atualização (campos obrigatórios)
        // Nota: updated_at é atualizado automaticamente pelo MySQL (ON UPDATE CURRENT_TIMESTAMP)
        $updateFields = [
            'status' => 'canceled',
        ];
        
        // Tentar atualizar com campos opcionais primeiro
        // Se os campos canceled_at e cancel_reason não existirem, tentar sem eles
        try {
            // Verificar se os campos opcionais existem tentando uma query de verificação
            global $pdo;
            $checkColumns = $pdo->query("SHOW COLUMNS FROM orders LIKE 'canceled_at'")->fetch();
            $hasCanceledAt = !empty($checkColumns);
            
            if ($hasCanceledAt) {
                $updateFields['canceled_at'] = date('Y-m-d H:i:s');
            }
            
            $checkColumns = $pdo->query("SHOW COLUMNS FROM orders LIKE 'cancel_reason'")->fetch();
            $hasCancelReason = !empty($checkColumns);
            
            if ($hasCancelReason && $reason !== null && trim($reason) !== '') {
                $updateFields['cancel_reason'] = trim($reason);
            }
        } catch (Exception $e) {
            // Se der erro ao verificar colunas, continua sem os campos opcionais
            error_log(sprintf('[orders.cancel] Aviso: não foi possível verificar colunas opcionais: %s', $e->getMessage()));
        }
        
        // Atualizar o pedido
        try {
            $updated = update('orders', $updateFields, 'id = ?', [$orderId]);
            
            if ($updated) {
                $logMessage = sprintf(
                    '[orders.cancel] Pedido %d cancelado internamente%s%s',
                    $orderId,
                    $userId ? sprintf(' por user %d', $userId) : '',
                    $reason ? sprintf(' - motivo: %s', $reason) : ''
                );
                error_log($logMessage);
                return true;
            }
            
            error_log(sprintf('[orders.cancel] Falha ao atualizar pedido: ID=%d | Nenhuma linha foi afetada', $orderId));
            return false;
        } catch (Exception $e) {
            error_log(sprintf('[orders.cancel] Erro ao atualizar pedido: ID=%d | Erro: %s', $orderId, $e->getMessage()));
            
            // Se deu erro e tinha campos opcionais, tenta novamente sem eles
            if (isset($updateFields['canceled_at']) || isset($updateFields['cancel_reason'])) {
                error_log(sprintf('[orders.cancel] Tentando novamente sem campos opcionais: ID=%d', $orderId));
                $basicFields = [
                    'status' => 'canceled',
                ];
                $updated = update('orders', $basicFields, 'id = ?', [$orderId]);
                
                if ($updated) {
                    error_log(sprintf('[orders.cancel] Pedido %d cancelado internamente (sem campos opcionais)', $orderId));
                    return true;
                }
            }
            
            return false;
        }
    }
}

