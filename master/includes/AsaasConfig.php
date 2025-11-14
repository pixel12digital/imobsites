<?php
/**
 * Configuração centralizada da integração com o Asaas.
 *
 * Responsável por carregar, validar e expor as variáveis de ambiente
 * necessárias para realizar chamadas autenticadas à API.
 *
 * ORDEM DE PRECEDÊNCIA (do mais prioritário ao menos):
 * 1. Variáveis de ambiente (getenv, $_ENV, $_SERVER)
 * 2. Arquivo config/asaas.php (fallback para desenvolvimento local)
 *
 * CONFIGURAÇÃO LOCAL (Desenvolvimento):
 * - Copie config/asaas.example.php para config/asaas.php
 * - Preencha os valores com suas credenciais do Asaas (sandbox)
 * - NUNCA faça commit do arquivo config/asaas.php (adicione ao .gitignore)
 *
 * CONFIGURAÇÃO PRODUÇÃO:
 * - Defina as variáveis de ambiente via .htaccess ou configuração do servidor:
 *   SetEnv ASAAS_API_KEY "sua_chave_producao"
 *   SetEnv ASAAS_ENV "production"
 *   SetEnv ASAAS_API_BASE_URL "https://api.asaas.com/v3"
 *   SetEnv ASAAS_WEBHOOK_TOKEN "seu_token_webhook"
 * - Se as variáveis de ambiente estiverem definidas, o arquivo config/asaas.php será ignorado
 */

declare(strict_types=1);

if (!function_exists('loadAsaasConfigFile')) {
    /**
     * Carrega o arquivo de configuração do Asaas (se existir).
     * Este arquivo serve como fallback quando variáveis de ambiente não estão definidas.
     *
     * @return array<string,mixed>|null
     */
    function loadAsaasConfigFile(): ?array
    {
        static $fileConfig = null;

        if ($fileConfig !== null) {
            return $fileConfig;
        }

        // Tenta carregar de config/asaas.php (relativo ao diretório raiz do projeto)
        $configPath = __DIR__ . '/../../config/asaas.php';

        if (file_exists($configPath)) {
            $loaded = require $configPath;
            if (is_array($loaded)) {
                $fileConfig = $loaded;
                return $fileConfig;
            }
        }

        $fileConfig = [];
        return null;
    }
}

if (!function_exists('asaasGetEnv')) {
    /**
     * Obtém o valor de uma configuração do Asaas.
     * Tenta primeiro variáveis de ambiente, depois o arquivo de config.
     *
     * @param string $key Nome da variável (ex: 'ASAAS_API_KEY')
     * @return string|null
     */
    function asaasGetEnv(string $key): ?string
    {
        // 1. Tenta ler de variáveis de ambiente (prioridade máxima)
        $value = getenv($key);

        if ($value === false && array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
        }

        if ($value === false && array_key_exists($key, $_SERVER)) {
            $value = $_SERVER[$key];
        }

        if ($value !== false && $value !== null) {
            $value = is_string($value) ? trim($value) : null;
            if ($value !== '' && $value !== null) {
                return $value;
            }
        }

        // 2. Fallback: tenta ler do arquivo de config
        $fileConfig = loadAsaasConfigFile();
        if ($fileConfig !== null) {
            // Mapeia nomes de variáveis de ambiente para chaves do array de config
            $configKeyMap = [
                'ASAAS_API_KEY' => 'api_key',
                'ASAAS_ENV' => 'env',
                'ASAAS_API_BASE_URL' => 'base_url',
                'ASAAS_WEBHOOK_TOKEN' => 'webhook_token',
            ];

            $configKey = $configKeyMap[$key] ?? null;
            if ($configKey !== null && isset($fileConfig[$configKey])) {
                $fileValue = $fileConfig[$configKey];
                if ($fileValue !== null && $fileValue !== '') {
                    return is_string($fileValue) ? trim($fileValue) : (string)$fileValue;
                }
            }
        }

        return null;
    }
}

if (!function_exists('getAsaasConfig')) {
    /**
     * Retorna as configurações validadas do Asaas.
     *
     * Ordem de precedência:
     * 1. Variáveis de ambiente (ASAAS_API_KEY, ASAAS_ENV, etc.)
     * 2. Arquivo config/asaas.php (fallback para desenvolvimento local)
     *
     * @return array{
     *     api_key:string,
     *     env:string,
     *     base_url:string,
     *     webhook_token: string|null
     * }
     * @throws RuntimeException Se a configuração obrigatória estiver ausente
     */
    function getAsaasConfig(): array
    {
        static $config;

        if ($config !== null) {
            return $config;
        }

        // Tenta ler API Key (obrigatória)
        $apiKey = asaasGetEnv('ASAAS_API_KEY');
        if ($apiKey === null || $apiKey === '') {
            error_log('[asaas.config] Variável ASAAS_API_KEY ausente. Verifique variáveis de ambiente ou config/asaas.php');
            throw new RuntimeException('Configuração do Asaas ausente: ASAAS_API_KEY.');
        }

        // Tenta ler ambiente (padrão: sandbox)
        $env = strtolower(asaasGetEnv('ASAAS_ENV') ?? 'sandbox');
        if (!in_array($env, ['sandbox', 'production'], true)) {
            error_log('[asaas.config] Valor inválido para ASAAS_ENV: ' . $env . ' (deve ser "sandbox" ou "production")');
            throw new RuntimeException('Configuração do Asaas inválida: ASAAS_ENV.');
        }

        // Tenta ler URL base (padrão: conforme ambiente)
        $baseUrl = asaasGetEnv('ASAAS_API_BASE_URL');

        if ($baseUrl === null || $baseUrl === '') {
            $baseUrl = $env === 'production'
                ? 'https://api.asaas.com/v3'
                : 'https://api-sandbox.asaas.com/v3';
        }

        $baseUrl = rtrim($baseUrl, '/');

        if (!preg_match('#^https://#', $baseUrl)) {
            error_log('[asaas.config] URL base inválida: ' . $baseUrl . ' (deve usar HTTPS)');
            throw new RuntimeException('Configuração do Asaas inválida: ASAAS_API_BASE_URL deve usar HTTPS.');
        }

        // Webhook token é opcional
        $webhookToken = asaasGetEnv('ASAAS_WEBHOOK_TOKEN');

        $config = [
            'api_key' => $apiKey,
            'env' => $env,
            'base_url' => $baseUrl,
            'webhook_token' => $webhookToken,
        ];

        // Log de debug (sem expor dados sensíveis)
        $configSource = getenv('ASAAS_API_KEY') !== false ? 'variáveis de ambiente' : 'arquivo config/asaas.php';
        error_log('[asaas.config] Configuração carregada de: ' . $configSource . ' | env=' . $env);

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


