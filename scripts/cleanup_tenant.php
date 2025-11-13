<?php
/**
 * Limpa todos os dados relacionados a um tenant específico.
 *
 * Uso via CLI:
 *   php scripts/cleanup_tenant.php --tenant=1
 *
 * Uso via navegador (apenas se realmente necessário):
 *   /scripts/cleanup_tenant.php?tenant=1&token=SUA_CHAVE_UNICA
 *
 * ATENÇÃO:
 * - Esta é uma operação destrutiva. Faça backup antes.
 * - Ajuste a constante CLEANUP_HTTP_TOKEN abaixo se quiser habilitar execução pela web.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const CLEANUP_HTTP_TOKEN = ''; // defina um token forte se quiser permitir execução via HTTP

$isCli = (php_sapi_name() === 'cli');

function getTenantIdFromInput(bool $isCli): ?int
{
    if ($isCli) {
        $options = getopt('', ['tenant:', 'dry-run::']);
        if (isset($options['tenant']) && is_numeric($options['tenant'])) {
            return (int) $options['tenant'];
        }
        return null;
    }

    if (!empty(CLEANUP_HTTP_TOKEN)) {
        $providedToken = $_GET['token'] ?? '';
        if (!hash_equals(CLEANUP_HTTP_TOKEN, $providedToken)) {
            http_response_code(403);
            echo 'Token inválido.';
            exit;
        }
    } else {
        http_response_code(403);
        echo 'Execução via navegador desabilitada. Defina CLEANUP_HTTP_TOKEN para habilitar.';
        exit;
    }

    if (isset($_GET['tenant']) && is_numeric($_GET['tenant'])) {
        return (int) $_GET['tenant'];
    }

    return null;
}

$tenantId = getTenantIdFromInput($isCli);

if ($tenantId === null || $tenantId <= 0) {
    $message = <<<TXT
Uso correto:
  php scripts/cleanup_tenant.php --tenant=ID_DO_TENANT

Exemplo:
  php scripts/cleanup_tenant.php --tenant=1

TXT;
    if ($isCli) {
        fwrite(STDERR, $message);
        exit(1);
    }
    echo nl2br($message);
    exit;
}

$steps = [
    'imovel_caracteristicas',
    'fotos_imovel',
    'historico_precos',
    'interesses',
    'imoveis',
    'clientes',
    'contatos',
    'localizacoes',
    'tipos_imovel',
];

$results = [];

try {
    $pdo->beginTransaction();

    foreach ($steps as $table) {
        $sql = "DELETE FROM {$table} WHERE tenant_id = :tenant_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        $results[$table] = $stmt->rowCount();
    }

    $pdo->commit();

    $output = "Limpeza concluída para tenant_id {$tenantId}.\n";
    foreach ($results as $table => $count) {
        $output .= sprintf(" - %s: %d registros removidos\n", $table, $count);
    }

    if ($isCli) {
        echo $output;
    } else {
        echo nl2br($output);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMsg = "Erro ao limpar tenant {$tenantId}: " . $e->getMessage();
    if ($isCli) {
        fwrite(STDERR, $errorMsg . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo $errorMsg;
}

