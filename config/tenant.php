<?php
/**
 * Tenant detection and settings loader
 */

if (!function_exists('query')) {
    throw new Exception('Database functions must be loaded before tenant detection.');
}

require_once __DIR__ . '/master.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function detectCurrentDomain(): string {
    if (php_sapi_name() === 'cli') {
        return 'localhost';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = strtolower($host);
    // Remove port if present
    return preg_replace('/:\d+$/', '', $host);
}

$overrideTenantId = $_SESSION['tenant_override_id'] ?? null;
$currentDomain = detectCurrentDomain();

if (!$overrideTenantId && function_exists('isMasterDomain') && isMasterDomain($currentDomain)) {
    if (php_sapi_name() !== 'cli') {
        header('Location: /master/login.php');
        exit;
    }

    throw new Exception('Master domain accessed without override in CLI context.');
}

if ($overrideTenantId) {
    $tenant = fetch("SELECT * FROM tenants WHERE id = ? AND status = 'active'", [$overrideTenantId]);
} else {
    $tenant = fetch("
        SELECT t.*
        FROM tenant_domains td
        INNER JOIN tenants t ON t.id = td.tenant_id
        WHERE td.domain = ? AND t.status = 'active'
        LIMIT 1
    ", [$currentDomain]);
}

if (!$tenant) {
    // fallback tenant id 1
    $tenant = fetch("SELECT * FROM tenants WHERE id = 1 LIMIT 1");
}

if (!$tenant) {
    die('Nenhum tenant ativo configurado para este domínio.');
}

define('TENANT_ID', (int)$tenant['id']);
define('TENANT_NAME', $tenant['name']);

$GLOBALS['current_tenant'] = $tenant;

$tenantSettings = fetch("SELECT * FROM tenant_settings WHERE tenant_id = ? LIMIT 1", [TENANT_ID]);

if (!$tenantSettings) {
    $tenantSettings = [
        'primary_color' => '#023A8D',
        'secondary_color' => '#F7931E',
    ];
}

$GLOBALS['tenant_settings'] = $tenantSettings;

function tenantSetting(string $key, $default = null) {
    return $GLOBALS['tenant_settings'][$key] ?? $default;
}

if (!function_exists('currentTenant')) {
    /**
     * Retorna o array completo do tenant atual detectado.
     *
     * @return array<string, mixed>
     */
    function currentTenant(): array
    {
        return $GLOBALS['current_tenant'] ?? [];
    }
}

