<?php
/**
 * Tenant detection and settings loader
 */

if (!function_exists('query')) {
    throw new Exception('Database functions must be loaded before tenant detection.');
}

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

if ($overrideTenantId) {
    $tenant = fetch("SELECT * FROM tenants WHERE id = ? AND status = 'active'", [$overrideTenantId]);
} else {
    $currentDomain = detectCurrentDomain();

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

