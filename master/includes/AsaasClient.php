<?php
/**
 * Cliente HTTP para comunicação com a API do Asaas.
 *
 * Implementa uma função central asaasRequest() que é reutilizada pelas
 * integrações de checkout, webhooks e demais pontos que dependem do Asaas.
 */

declare(strict_types=1);

require_once __DIR__ . '/AsaasConfig.php';

if (!function_exists('asaasRequest')) {
    /**
     * Realiza uma requisição autenticada à API do Asaas.
     *
     * @param string $method
     * @param string $path
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>
     */
    function asaasRequest(string $method, string $path, ?array $payload = null): array
    {
        $config = getAsaasConfig();

        $method = strtoupper($method);
        $baseUrl = $config['base_url'];
        $path = '/' . ltrim($path, '/');
        $url = $baseUrl . $path;

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'access_token: ' . $config['api_key'],
                'User-Agent: imobsites-panel/1.0 (checkout-integration)',
            ],
        ];

        if ($method === 'GET') {
            if (!empty($payload)) {
                $separator = strpos($url, '?') === false ? '?' : '&';
                $url .= $separator . http_build_query($payload);
            }
        } elseif ($payload !== null) {
            $body = json_encode($payload);
            if ($body === false) {
                throw new RuntimeException('Falha ao codificar payload para Asaas.');
            }
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        switch ($method) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
                break;
        }

        $curlOptions[CURLOPT_URL] = $url;

        // Log antes da requisição (sem dados sensíveis)
        if ($method === 'POST' && $path === '/payments' && is_array($payload)) {
            error_log(sprintf(
                '[asaas.payment] Enviando cobrança: value=%.2f customer=%s description=%s',
                (float)($payload['value'] ?? 0),
                substr((string)($payload['customer'] ?? ''), 0, 20),
                substr((string)($payload['description'] ?? ''), 0, 50)
            ));
        }

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            error_log(sprintf('[asaas.http.error] Erro de cURL (%d): %s | URL=%s', $curlErrno, $curlError, $url));
            throw new RuntimeException('Falha de comunicação com o Asaas: ' . ($curlError ?: 'Erro desconhecido'));
        }

        $decoded = null;

        if ($responseBody !== '' && $statusCode !== 204) {
            $decoded = json_decode($responseBody, true);

            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log('[asaas.http.error] Resposta JSON inválida: ' . substr($responseBody, 0, 1000));
                throw new RuntimeException('Resposta inválida recebida do Asaas.');
            }
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            // Log detalhado do erro
            $logBody = is_array($decoded) ? json_encode($decoded) : substr((string)$responseBody, 0, 1000);
            error_log(sprintf(
                '[asaas.http.error] %s %s -> HTTP %d | body=%s',
                $method,
                $path,
                $statusCode,
                $logBody
            ));

            // Extrair mensagem de erro do Asaas
            $asaasMessage = null;
            if (is_array($decoded)) {
                // Tenta múltiplos formatos de erro do Asaas
                if (isset($decoded['errors']) && is_array($decoded['errors']) && isset($decoded['errors'][0]['description'])) {
                    $asaasMessage = (string)$decoded['errors'][0]['description'];
                } elseif (isset($decoded['errors']) && is_array($decoded['errors']) && isset($decoded['errors'][0]['message'])) {
                    $asaasMessage = (string)$decoded['errors'][0]['message'];
                } elseif (isset($decoded['message'])) {
                    $asaasMessage = (string)$decoded['message'];
                } elseif (isset($decoded['error'])) {
                    $asaasMessage = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
                }
            }

            // Se não encontrou mensagem específica, usa genérica com status
            if (!$asaasMessage || trim($asaasMessage) === '') {
                $asaasMessage = 'Erro ao processar requisição no Asaas (HTTP ' . $statusCode . ')';
            }

            throw new RuntimeException($asaasMessage);
        }

        // Log de sucesso para pagamentos
        if ($method === 'POST' && $path === '/payments' && is_array($decoded)) {
            error_log('[asaas.payment] Cobrança criada com sucesso. paymentId=' . ($decoded['id'] ?? 'sem-id'));
        }

        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('asaasFindCustomerByEmail')) {
    /**
     * Tenta localizar um cliente existente no Asaas pelo e-mail.
     *
     * @param string $email
     * @return array<string,mixed>|null
     */
    function asaasFindCustomerByEmail(string $email): ?array
    {
        if ($email === '') {
            return null;
        }

        try {
            $response = asaasRequest('GET', '/customers', ['email' => $email]);
        } catch (Throwable $th) {
            // Busca falhou, segue para criação
            error_log('[asaas.client] Falha ao buscar cliente por e-mail: ' . $th->getMessage());
            return null;
        }

        $data = $response['data'] ?? null;

        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        return null;
    }
}

if (!function_exists('asaasCreateCustomer')) {
    /**
     * Cria um cliente no Asaas.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    function asaasCreateCustomer(array $payload): array
    {
        return asaasRequest('POST', '/customers', $payload);
    }
}

if (!function_exists('asaasUpdateCustomer')) {
    /**
     * Atualiza um cliente existente no Asaas.
     *
     * @param string $customerId
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    function asaasUpdateCustomer(string $customerId, array $payload): array
    {
        return asaasRequest('PUT', '/customers/' . $customerId, $payload);
    }
}

if (!function_exists('asaasCreatePayment')) {
    /**
     * Cria uma cobrança simples no Asaas.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    function asaasCreatePayment(array $payload): array
    {
        return asaasRequest('POST', '/payments', $payload);
    }
}

if (!function_exists('asaasGetPayment')) {
    /**
     * Busca os dados de um pagamento no Asaas.
     *
     * @param string $paymentId
     * @return array<string,mixed>
     */
    function asaasGetPayment(string $paymentId): array
    {
        return asaasRequest('GET', '/payments/' . $paymentId);
    }
}

if (!function_exists('asaasCreateSubscription')) {
    /**
     * Cria uma assinatura recorrente no Asaas.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    function asaasCreateSubscription(array $payload): array
    {
        return asaasRequest('POST', '/subscriptions', $payload);
    }
}

if (!function_exists('asaasGetSubscription')) {
    /**
     * Busca uma assinatura no Asaas pelo ID.
     *
     * @param string $subscriptionId
     * @return array<string,mixed>
     */
    function asaasGetSubscription(string $subscriptionId): array
    {
        return asaasRequest('GET', '/subscriptions/' . $subscriptionId);
    }
}

if (!function_exists('asaasCancelSubscription')) {
    /**
     * Cancela uma assinatura no Asaas.
     *
     * @param string $subscriptionId
     * @return array<string,mixed>
     */
    function asaasCancelSubscription(string $subscriptionId): array
    {
        return asaasRequest('DELETE', '/subscriptions/' . $subscriptionId);
    }
}

if (!function_exists('asaasGetPixQrCode')) {
    /**
     * Busca os dados do QR Code PIX para um pagamento.
     * 
     * Endpoint: GET /v3/payments/{id}/pixQrCode
     *
     * @param string $paymentId ID do pagamento no Asaas
     * @return array<string,mixed> Retorna payload e encodedImage
     */
    function asaasGetPixQrCode(string $paymentId): array
    {
        return asaasRequest('GET', '/payments/' . $paymentId . '/pixQrCode');
    }
}

if (!function_exists('asaasGetBoletoIdentificationField')) {
    /**
     * Busca a linha digitável (identificationField) de um boleto.
     * 
     * Endpoint: GET /v3/payments/{id}/identificationField
     *
     * @param string $paymentId ID do pagamento no Asaas
     * @return array<string,mixed> Retorna identificationField
     */
    function asaasGetBoletoIdentificationField(string $paymentId): array
    {
        return asaasRequest('GET', '/payments/' . $paymentId . '/identificationField');
    }
}


