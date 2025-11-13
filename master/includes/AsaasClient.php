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

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);

        $responseBody = curl_exec($ch);

        if ($responseBody === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            error_log(sprintf('[asaas.http] Erro cURL (%d): %s', $errno, $error));
            throw new RuntimeException('Não foi possível comunicar com o Asaas.');
        }

        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = null;

        if ($responseBody !== '' && $statusCode !== 204) {
            $decoded = json_decode($responseBody, true);

            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log('[asaas.http] Resposta inválida: ' . substr($responseBody, 0, 1000));
                throw new RuntimeException('Resposta inválida recebida do Asaas.');
            }
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $logBody = $decoded ?? $responseBody;
            error_log(sprintf(
                '[asaas.http] %s %s -> HTTP %d | body=%s',
                $method,
                $path,
                $statusCode,
                is_scalar($logBody) ? (string)$logBody : json_encode($logBody)
            ));

            $message = 'Erro na requisição ao Asaas.';
            if (is_array($decoded) && isset($decoded['errors'][0]['description'])) {
                $message .= ' ' . $decoded['errors'][0]['description'];
            }

            throw new RuntimeException($message);
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


