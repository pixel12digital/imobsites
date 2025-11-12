<?php
/**
 * Configurações do painel master (revenda)
 * Ajuste as credenciais conforme necessário.
 */

define('MASTER_PANEL_NAME', 'Painel Master - Imobsites');

// Detectar domínios permitidos para o painel master
$defaultMasterDomains = [
    'painel.imobsites.com.br',
    'painel.imobsites.com',
    'painel.imobsites.local',
    'painel.localhost',
    'master.imobsites.local',
    'master.localhost',
];

$envMasterDomains = getenv('MASTER_DOMAINS');
if ($envMasterDomains) {
    $parsed = array_filter(array_map('trim', explode(',', $envMasterDomains)));
    if (!empty($parsed)) {
        $defaultMasterDomains = $parsed;
    }
}

if (!defined('MASTER_ALLOWED_DOMAINS')) {
    define('MASTER_ALLOWED_DOMAINS', $defaultMasterDomains);
}

// Credenciais de acesso ao painel master
define('MASTER_EMAIL', 'admin@imobsites.com.br');
// Gere o hash com password_hash('sua_senha', PASSWORD_DEFAULT)
define('MASTER_PASSWORD_HASH', '$2y$10$xAUIDEOA9VqYm7hWMqYTpeZkQfOis0b5TYe0kqtDzaPg8kxSFn/Wm'); // senha padrão: Los@ngo#081081

if (!function_exists('normalizeDomain')) {
    function normalizeDomain(string $host): string
    {
        $host = strtolower(trim($host));
        return preg_replace('/:\d+$/', '', $host);
    }
}

if (!function_exists('getMasterDomains')) {
    function getMasterDomains(): array
    {
        $domains = MASTER_ALLOWED_DOMAINS;
        if (is_string($domains)) {
            $decoded = @json_decode($domains, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $domains = $decoded;
            } else {
                $domains = array_filter(array_map('trim', explode(',', $domains)));
            }
        }

        return array_values(array_unique(array_map('normalizeDomain', (array)$domains)));
    }
}

if (!function_exists('isMasterDomain')) {
    function isMasterDomain(?string $host = null): bool
    {
        if ($host === null) {
            if (php_sapi_name() === 'cli') {
                return false;
            }
            $host = $_SERVER['HTTP_HOST'] ?? '';
        }

        if (empty($host)) {
            return false;
        }

        $host = normalizeDomain($host);

        foreach (getMasterDomains() as $domain) {
            if ($host === $domain) {
                return true;
            }
        }

        return false;
    }
}

// Função auxiliar para verificar senha master
if (!function_exists('verifyMasterPassword')) {
    function verifyMasterPassword(string $password): bool
    {
        return password_verify($password, MASTER_PASSWORD_HASH);
    }
}

