<?php
/**
 * Configuração centralizada da integração com o Asaas.
 *
 * Responsável por carregar, validar e expor as variáveis de ambiente
 * necessárias para realizar chamadas autenticadas à API.
 */

declare(strict_types=1);

if (!function_exists('asaasGetEnv')) {
    /**
     * Obtém o valor de uma variável de ambiente considerando $_ENV/$_SERVER.
     *
     * @param string $key
     * @return string|null
     */
    function asaasGetEnv(string $key): ?string
    {
        $value = getenv($key);

        if ($value === false && array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
        }

        if ($value === false && array_key_exists($key, $_SERVER)) {
            $value = $_SERVER[$key];
        }

        if ($value === false) {
            return null;
        }

        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}

if (!function_exists('getAsaasConfig')) {
    /**
     * Retorna as configurações validadas do Asaas.
     *
     * @return array{
     *     api_key:string,
     *     env:string,
     *     base_url:string,
     *     webhook_token: string|null
     * }
     */
    function getAsaasConfig(): array
    {
        static $config;

        if ($config !== null) {
            return $config;
        }

        $apiKey = asaasGetEnv('ASAAS_API_KEY');
        if ($apiKey === null) {
            error_log('[asaas.config] Variável ASAAS_API_KEY ausente.');
            throw new RuntimeException('Configuração do Asaas ausente: ASAAS_API_KEY.');
        }

        $env = strtolower(asaasGetEnv('ASAAS_ENV') ?? 'sandbox');
        if (!in_array($env, ['sandbox', 'production'], true)) {
            error_log('[asaas.config] Valor inválido para ASAAS_ENV: ' . $env);
            throw new RuntimeException('Configuração do Asaas inválida: ASAAS_ENV.');
        }

        $baseUrl = asaasGetEnv('ASAAS_API_BASE_URL');

        if ($baseUrl === null) {
            $baseUrl = $env === 'production'
                ? 'https://api.asaas.com/v3'
                : 'https://api-sandbox.asaas.com/v3';
        }

        $baseUrl = rtrim($baseUrl, '/');

        if (!preg_match('#^https://#', $baseUrl)) {
            error_log('[asaas.config] URL base inválida: ' . $baseUrl);
            throw new RuntimeException('Configuração do Asaas inválida: ASAAS_API_BASE_URL deve usar HTTPS.');
        }

        $config = [
            'api_key' => $apiKey,
            'env' => $env,
            'base_url' => $baseUrl,
            'webhook_token' => asaasGetEnv('ASAAS_WEBHOOK_TOKEN'),
        ];

        return $config;
    }
}

if (!function_exists('getAsaasWebhookToken')) {
    /**
     * Retorna o token configurado para validação dos webhooks.
     *
     * @return string
     */
    function getAsaasWebhookToken(): string
    {
        $config = getAsaasConfig();

        if (empty($config['webhook_token'])) {
            error_log('[asaas.config] Variável ASAAS_WEBHOOK_TOKEN ausente.');
            throw new RuntimeException('Configuração do Asaas ausente: ASAAS_WEBHOOK_TOKEN.');
        }

        return $config['webhook_token'];
    }
}


