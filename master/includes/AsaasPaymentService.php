<?php
/**
 * Serviço responsável por orquestrar a criação de clientes e cobranças no Asaas.
 *
 * Nesta implementação usamos o endpoint POST /v3/payments que gera uma cobrança
 * com URL hospedada pelo Asaas. O cliente é redirecionado para esta URL para
 * concluir o pagamento (cartão, boleto, Pix, conforme configurado na conta).
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

if (!function_exists('parseOrderIdFromAsaasReference')) {
    /**
     * Recupera o ID do pedido a partir da referência externa do Asaas.
     *
     * @param string|null $externalReference
     * @return int|null
     */
    function parseOrderIdFromAsaasReference(?string $externalReference): ?int
    {
        if (!is_string($externalReference) || $externalReference === '') {
            return null;
        }

        if (preg_match('/^order:(\d+)$/', $externalReference, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }
}

if (!function_exists('resolvePaymentUrlFromAsaasResponse')) {
    /**
     * Extrai a URL de pagamento disponibilizada pelo Asaas.
     *
     * @param array<string,mixed> $paymentResponse
     * @return string|null
     */
    function resolvePaymentUrlFromAsaasResponse(array $paymentResponse): ?string
    {
        $candidates = [
            $paymentResponse['invoiceUrl'] ?? null,
            $paymentResponse['paymentUrl'] ?? null,
            $paymentResponse['checkoutUrl'] ?? null,
            $paymentResponse['bankSlipUrl'] ?? null,
            $paymentResponse['boletoUrl'] ?? null,
        ];

        foreach ($candidates as $url) {
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }
}

if (!function_exists('createPaymentOnAsaas')) {
    /**
     * Cria (ou reutiliza) um cliente no Asaas e gera a cobrança do pedido.
     *
     * @param array<string,mixed> $orderData
     * @param array<string,mixed> $planData
     * @param array<string,mixed> $customerData
     * @return array<string,mixed>
     */
    function createPaymentOnAsaas(array $orderData, array $planData, array $customerData): array
    {
        if (empty($orderData['id'])) {
            throw new InvalidArgumentException('orderData sem ID do pedido.');
        }

        $orderId = (int)$orderData['id'];
        $externalReference = buildAsaasExternalReference($orderId);

        $customerName = (string)($customerData['name'] ?? $orderData['customer_name'] ?? '');
        $customerEmail = (string)($customerData['email'] ?? $orderData['customer_email'] ?? '');

        if ($customerName === '' || $customerEmail === '') {
            throw new InvalidArgumentException('Dados do cliente incompletos para integração com Asaas.');
        }

        $existingCustomer = asaasFindCustomerByEmail($customerEmail);

        if (is_array($existingCustomer) && isset($existingCustomer['id'])) {
            $customerId = (string)$existingCustomer['id'];
        } else {
            $customerPayload = [
                'name' => $customerName,
                'email' => $customerEmail,
                'mobilePhone' => $customerData['mobile_phone'] ?? $orderData['customer_whatsapp'] ?? null,
                'externalReference' => $externalReference,
                'notificationsDisabled' => false,
            ];

            if (!empty($customerData['cpf_cnpj'])) {
                $customerPayload['cpfCnpj'] = preg_replace('/\D+/', '', (string)$customerData['cpf_cnpj']);
            }

            $customerResponse = asaasCreateCustomer($customerPayload);
            $customerId = (string)($customerResponse['id'] ?? '');

            if ($customerId === '') {
                throw new RuntimeException('Não foi possível criar o cliente no Asaas.');
            }
        }

        $planName = (string)($planData['name'] ?? $orderData['plan_code'] ?? 'Plano ImobSites');
        $orderTotal = (float)($orderData['total_amount'] ?? 0.0);

        if ($orderTotal <= 0) {
            throw new InvalidArgumentException('Valor do pedido inválido para cobrança Asaas.');
        }

        $paymentPayload = [
            'customer' => $customerId,
            'billingType' => $customerData['billing_type'] ?? 'UNDEFINED',
            'value' => $orderTotal,
            'description' => sprintf('ImobSites - %s - Pedido #%d', $planName, $orderId),
            'externalReference' => $externalReference,
        ];

        if (!empty($customerData['max_installments']) && (int)$customerData['max_installments'] > 1) {
            $paymentPayload['installmentCount'] = (int)$customerData['max_installments'];
        }

        $paymentResponse = asaasCreatePayment($paymentPayload);

        $providerPaymentId = (string)($paymentResponse['id'] ?? '');
        if ($providerPaymentId === '') {
            throw new RuntimeException('Cobrança criada no Asaas sem identificador.');
        }

        $paymentUrl = resolvePaymentUrlFromAsaasResponse($paymentResponse);

        if ($paymentUrl === null) {
            error_log('[asaas.payment] Cobrança sem URL de pagamento retornada.');
        }

        return [
            'provider' => 'asaas',
            'provider_payment_id' => $providerPaymentId,
            'payment_url' => $paymentUrl,
            'status' => strtolower((string)($paymentResponse['status'] ?? 'PENDING')),
            'max_installments' => $customerData['max_installments'] ?? null,
            'raw_response' => $paymentResponse,
        ];
    }
}


