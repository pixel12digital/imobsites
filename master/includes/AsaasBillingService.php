<?php
/**
 * AsaasBillingService
 *
 * Serviço centralizado para gerenciar billing no Asaas:
 * - Garantir existência de customer
 * - Criar cobranças pré-pagas (prepaid_parceled)
 * - Criar assinaturas recorrentes (recurring_monthly)
 *
 * Suporta:
 * - Cartão de crédito (com parcelamento)
 * - Pix
 * - Boleto
 */

declare(strict_types=1);

require_once __DIR__ . '/AsaasClient.php';

if (!function_exists('buildAsaasExternalReference')) {
    /**
     * Monta a referência externa usada para amarrar pedido ↔ cobrança no Asaas.
     *
     * @param int $orderId
     * @return string
     */
    function buildAsaasExternalReference(int $orderId): string
    {
        return 'order:' . $orderId;
    }
}

if (!function_exists('ensureCustomerForOrder')) {
    /**
     * Garante que existe um customer no Asaas para o pedido.
     * Busca por e-mail/CPF ou cria um novo.
     *
     * @param array<string,mixed> $orderData
     * @return string ID do customer no Asaas
     */
    function ensureCustomerForOrder(array $orderData): string
    {
        global $pdo;

        $customerEmail = (string)($orderData['customer_email'] ?? '');
        $customerCpfCnpj = isset($orderData['customer_cpf_cnpj']) ? preg_replace('/\D+/', '', (string)$orderData['customer_cpf_cnpj']) : null;

        if ($customerEmail === '') {
            throw new InvalidArgumentException('E-mail do cliente é obrigatório para criar customer no Asaas.');
        }

        // Verifica se já existe asaas_customer_id no pedido
        if (!empty($orderData['asaas_customer_id'])) {
            return (string)$orderData['asaas_customer_id'];
        }

        // Tenta buscar customer existente no Asaas por e-mail
        $existingCustomer = asaasFindCustomerByEmail($customerEmail);

        if (is_array($existingCustomer) && isset($existingCustomer['id'])) {
            $customerId = (string)$existingCustomer['id'];

            // Atualiza o pedido com o customer_id encontrado
            if (isset($orderData['id'])) {
                update('orders', [
                    'asaas_customer_id' => $customerId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int)$orderData['id']]);
            }

            return $customerId;
        }

        // Cria novo customer no Asaas
        $orderId = (int)($orderData['id'] ?? 0);
        $externalReference = $orderId > 0 ? buildAsaasExternalReference($orderId) : null;

        $customerPayload = [
            'name' => (string)($orderData['customer_name'] ?? ''),
            'email' => $customerEmail,
            'mobilePhone' => isset($orderData['customer_whatsapp']) ? preg_replace('/\D+/', '', (string)$orderData['customer_whatsapp']) : null,
            'notificationsDisabled' => false,
        ];

        if ($customerCpfCnpj !== null && $customerCpfCnpj !== '') {
            $customerPayload['cpfCnpj'] = $customerCpfCnpj;
        }

        if ($externalReference !== null) {
            $customerPayload['externalReference'] = $externalReference;
        }

        try {
            $customerResponse = asaasCreateCustomer($customerPayload);
            $customerId = (string)($customerResponse['id'] ?? '');

            if ($customerId === '') {
                throw new RuntimeException('Não foi possível criar o cliente no Asaas.');
            }

            // Atualiza o pedido com o customer_id criado
            if ($orderId > 0) {
                update('orders', [
                    'asaas_customer_id' => $customerId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$orderId]);
            }

            error_log('[asaas.billing] Customer criado no Asaas: ' . $customerId . ' para orderId=' . $orderId);

            return $customerId;
        } catch (Throwable $e) {
            error_log('[asaas.billing.error] Falha ao criar customer: ' . $e->getMessage());
            throw new RuntimeException('Não foi possível criar o cliente no Asaas: ' . $e->getMessage(), 0, $e);
        }
    }
}

if (!function_exists('createPrepaidPayment')) {
    /**
     * Cria uma cobrança pré-paga no Asaas (modelo B: planos parcelados).
     *
     * @param array<string,mixed> $orderData
     * @param array<string,mixed> $planData
     * @param string $customerId ID do customer no Asaas
     * @param string $paymentMethod 'credit_card', 'pix' ou 'boleto'
     * @param array<string,mixed>|null $cardData Dados do cartão (quando paymentMethod = 'credit_card')
     * @return array<string,mixed>
     */
    function createPrepaidPayment(
        array $orderData,
        array $planData,
        string $customerId,
        string $paymentMethod,
        ?array $cardData = null
    ): array {
        $orderId = (int)($orderData['id'] ?? 0);
        $externalReference = buildAsaasExternalReference($orderId);
        $planName = (string)($planData['name'] ?? $orderData['plan_code'] ?? 'Plano ImobSites');
        $totalAmount = (float)($orderData['total_amount'] ?? 0.0);

        if ($totalAmount <= 0) {
            throw new InvalidArgumentException('Valor do pedido inválido para cobrança Asaas.');
        }

        // Determina billingType conforme método de pagamento
        $billingTypeMap = [
            'credit_card' => 'CREDIT_CARD',
            'pix' => 'PIX',
            'boleto' => 'BOLETO',
        ];

        $billingType = $billingTypeMap[$paymentMethod] ?? 'UNDEFINED';

        if ($billingType === 'UNDEFINED') {
            throw new InvalidArgumentException('Método de pagamento inválido: ' . $paymentMethod);
        }

        // Monta payload base
        $paymentPayload = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => $totalAmount,
            'description' => sprintf('ImobSites - %s - Pedido #%d', $planName, $orderId),
            'externalReference' => $externalReference,
        ];

        // Para cartão de crédito, adiciona dados do cartão e parcelamento
        if ($paymentMethod === 'credit_card' && is_array($cardData)) {
            $installments = isset($orderData['payment_installments']) ? (int)$orderData['payment_installments'] : 1;
            if ($installments > 1) {
                $paymentPayload['installmentCount'] = $installments;
                $paymentPayload['installmentValue'] = round($totalAmount / $installments, 2);
            }

            // Normaliza campo do número do cartão (aceita 'number' ou 'cardNumber')
            $cardNumber = $cardData['cardNumber'] ?? $cardData['number'] ?? null;

            // Dados do cartão
            $creditCard = [];
            if ($cardNumber !== null) {
                $creditCard['number'] = preg_replace('/\D+/', '', (string)$cardNumber);
            }
            if (isset($cardData['expiryMonth'])) {
                $creditCard['expiryMonth'] = str_pad((string)$cardData['expiryMonth'], 2, '0', STR_PAD_LEFT);
            }
            if (isset($cardData['expiryYear'])) {
                $creditCard['expiryYear'] = (string)$cardData['expiryYear'];
            }
            if (isset($cardData['ccv'])) {
                $creditCard['ccv'] = (string)$cardData['ccv'];
            }

            // Dados do titular
            $holderInfo = [];
            if (isset($cardData['holderName'])) {
                $holderInfo['name'] = (string)$cardData['holderName'];
            }
            if (isset($cardData['cpfCnpj']) || isset($orderData['customer_cpf_cnpj'])) {
                $holderInfo['cpfCnpj'] = preg_replace('/\D+/', '', (string)($cardData['cpfCnpj'] ?? $orderData['customer_cpf_cnpj'] ?? ''));
            }
            if (isset($cardData['postalCode'])) {
                $holderInfo['postalCode'] = preg_replace('/\D+/', '', (string)$cardData['postalCode']);
            }
            if (isset($cardData['addressNumber'])) {
                $holderInfo['addressNumber'] = (string)$cardData['addressNumber'];
            }
            if (isset($cardData['address']) || isset($cardData['street'])) {
                $holderInfo['address'] = (string)($cardData['address'] ?? $cardData['street'] ?? '');
            }
            if (isset($cardData['addressComplement'])) {
                $holderInfo['addressComplement'] = (string)$cardData['addressComplement'];
            }
            if (isset($cardData['province']) || isset($cardData['neighborhood'])) {
                $holderInfo['province'] = (string)($cardData['province'] ?? $cardData['neighborhood'] ?? '');
            }
            if (isset($cardData['city'])) {
                $holderInfo['city'] = (string)$cardData['city'];
            }
            if (isset($cardData['state'])) {
                $holderInfo['state'] = strtoupper(substr((string)$cardData['state'], 0, 2));
            }
            if (isset($cardData['phone'])) {
                $holderInfo['phone'] = preg_replace('/\D+/', '', (string)$cardData['phone']);
            }

            if (!empty($creditCard)) {
                $paymentPayload['creditCard'] = $creditCard;
            }
            if (!empty($holderInfo)) {
                $paymentPayload['creditCardHolderInfo'] = $holderInfo;
            }
        }

        error_log(sprintf(
            '[asaas.billing] Criando cobrança pré-paga: orderId=%d method=%s amount=%.2f installments=%d',
            $orderId,
            $paymentMethod,
            $totalAmount,
            $orderData['payment_installments'] ?? 1
        ));

        try {
            $paymentResponse = asaasCreatePayment($paymentPayload);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[asaas.billing.error] Falha ao criar cobrança orderId=%d: %s',
                $orderId,
                $e->getMessage()
            ));
            throw $e;
        }

        $providerPaymentId = (string)($paymentResponse['id'] ?? '');
        if ($providerPaymentId === '') {
            throw new RuntimeException('Cobrança criada no Asaas sem identificador.');
        }

        // Extrai dados de resposta conforme método de pagamento
        $result = [
            'provider' => 'asaas',
            'provider_payment_id' => $providerPaymentId,
            'payment_method' => $paymentMethod,
            'status' => strtolower((string)($paymentResponse['status'] ?? 'PENDING')),
            'raw_response' => $paymentResponse,
        ];

        // URL de pagamento (boleto)
        $paymentUrl = $paymentResponse['invoiceUrl'] ?? $paymentResponse['paymentUrl'] ?? $paymentResponse['bankSlipUrl'] ?? $paymentResponse['boletoUrl'] ?? null;
        if ($paymentUrl) {
            $result['payment_url'] = $paymentUrl;
        }

        // Dados do Pix
        if ($paymentMethod === 'pix') {
            $result['pix_payload'] = $paymentResponse['pixPayload'] ?? $paymentResponse['pixCopiaECola'] ?? null;
            $result['pix_qr_code_image'] = $paymentResponse['pixQrCodeImage'] ?? null;
        }

        // Dados do boleto
        if ($paymentMethod === 'boleto') {
            $result['boleto_url'] = $paymentUrl;
            $result['boleto_barcode'] = $paymentResponse['barcode'] ?? $paymentResponse['nossoNumero'] ?? null;
        }

        error_log('[asaas.billing] Cobrança pré-paga criada: paymentId=' . $providerPaymentId . ' orderId=' . $orderId);

        return $result;
    }
}

if (!function_exists('createRecurringSubscription')) {
    /**
     * Cria uma assinatura recorrente no Asaas (modelo A: plano mensal).
     *
     * @param array<string,mixed> $orderData
     * @param array<string,mixed> $planData
     * @param string $customerId ID do customer no Asaas
     * @param string $paymentMethod 'credit_card' (outros métodos podem ser adicionados depois)
     * @param array<string,mixed>|null $cardData Dados do cartão
     * @return array<string,mixed>
     */
    function createRecurringSubscription(
        array $orderData,
        array $planData,
        string $customerId,
        string $paymentMethod,
        ?array $cardData = null
    ): array {
        $orderId = (int)($orderData['id'] ?? 0);
        $externalReference = buildAsaasExternalReference($orderId);
        $planName = (string)($planData['name'] ?? $orderData['plan_code'] ?? 'Plano ImobSites');
        $monthlyValue = (float)($planData['price_per_month'] ?? $orderData['total_amount'] ?? 0.0);

        if ($monthlyValue <= 0) {
            throw new InvalidArgumentException('Valor mensal do plano inválido para assinatura Asaas.');
        }

        if ($paymentMethod !== 'credit_card') {
            throw new InvalidArgumentException('Assinaturas recorrentes atualmente suportam apenas cartão de crédito.');
        }

        if (!is_array($cardData) || empty($cardData)) {
            throw new InvalidArgumentException('Dados do cartão são obrigatórios para assinatura recorrente.');
        }

        // Calcula próxima data de vencimento (hoje + 1 dia)
        $nextDueDate = date('Y-m-d', strtotime('+1 day'));

        // Monta payload da assinatura
        $subscriptionPayload = [
            'customer' => $customerId,
            'billingType' => 'CREDIT_CARD',
            'value' => $monthlyValue,
            'nextDueDate' => $nextDueDate,
            'cycle' => 'MONTHLY',
            'description' => sprintf('ImobSites - %s - Assinatura Mensal', $planName),
            'externalReference' => $externalReference,
        ];

        // Normaliza campo do número do cartão (aceita 'number' ou 'cardNumber')
        $cardNumber = $cardData['cardNumber'] ?? $cardData['number'] ?? null;

        // Dados do cartão
        $creditCard = [];
        if ($cardNumber !== null) {
            $creditCard['number'] = preg_replace('/\D+/', '', (string)$cardNumber);
        }
        if (isset($cardData['expiryMonth'])) {
            $creditCard['expiryMonth'] = str_pad((string)$cardData['expiryMonth'], 2, '0', STR_PAD_LEFT);
        }
        if (isset($cardData['expiryYear'])) {
            $creditCard['expiryYear'] = (string)$cardData['expiryYear'];
        }
        if (isset($cardData['ccv'])) {
            $creditCard['ccv'] = (string)$cardData['ccv'];
        }

        // Dados do titular
        $holderInfo = [];
        if (isset($cardData['holderName'])) {
            $holderInfo['name'] = (string)$cardData['holderName'];
        }
        if (isset($cardData['cpfCnpj']) || isset($orderData['customer_cpf_cnpj'])) {
            $holderInfo['cpfCnpj'] = preg_replace('/\D+/', '', (string)($cardData['cpfCnpj'] ?? $orderData['customer_cpf_cnpj'] ?? ''));
        }
        if (isset($cardData['postalCode'])) {
            $holderInfo['postalCode'] = preg_replace('/\D+/', '', (string)$cardData['postalCode']);
        }
        if (isset($cardData['addressNumber'])) {
            $holderInfo['addressNumber'] = (string)$cardData['addressNumber'];
        }
        if (isset($cardData['address']) || isset($cardData['street'])) {
            $holderInfo['address'] = (string)($cardData['address'] ?? $cardData['street'] ?? '');
        }
        if (isset($cardData['addressComplement'])) {
            $holderInfo['addressComplement'] = (string)$cardData['addressComplement'];
        }
        if (isset($cardData['province']) || isset($cardData['neighborhood'])) {
            $holderInfo['province'] = (string)($cardData['province'] ?? $cardData['neighborhood'] ?? '');
        }
        if (isset($cardData['city'])) {
            $holderInfo['city'] = (string)$cardData['city'];
        }
        if (isset($cardData['state'])) {
            $holderInfo['state'] = strtoupper(substr((string)$cardData['state'], 0, 2));
        }
        if (isset($cardData['phone'])) {
            $holderInfo['phone'] = preg_replace('/\D+/', '', (string)$cardData['phone']);
        }

        if (!empty($creditCard)) {
            $subscriptionPayload['creditCard'] = $creditCard;
        }
        if (!empty($holderInfo)) {
            $subscriptionPayload['creditCardHolderInfo'] = $holderInfo;
        }

        error_log(sprintf(
            '[asaas.billing] Criando assinatura recorrente: orderId=%d value=%.2f nextDueDate=%s',
            $orderId,
            $monthlyValue,
            $nextDueDate
        ));

        try {
            $subscriptionResponse = asaasCreateSubscription($subscriptionPayload);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[asaas.billing.error] Falha ao criar assinatura orderId=%d: %s',
                $orderId,
                $e->getMessage()
            ));
            throw $e;
        }

        $providerSubscriptionId = (string)($subscriptionResponse['id'] ?? '');
        if ($providerSubscriptionId === '') {
            throw new RuntimeException('Assinatura criada no Asaas sem identificador.');
        }

        $result = [
            'provider' => 'asaas',
            'provider_subscription_id' => $providerSubscriptionId,
            'payment_method' => $paymentMethod,
            'subscription_status' => strtolower((string)($subscriptionResponse['status'] ?? 'ACTIVE')),
            'next_due_date' => $subscriptionResponse['nextDueDate'] ?? $nextDueDate,
            'raw_response' => $subscriptionResponse,
        ];

        error_log('[asaas.billing] Assinatura recorrente criada: subscriptionId=' . $providerSubscriptionId . ' orderId=' . $orderId);

        return $result;
    }
}

