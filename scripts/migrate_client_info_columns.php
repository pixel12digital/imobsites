<?php
/**
 * Migração idempotente para criar as colunas da aba "Informações do Cliente" na tabela tenants.
 *
 * Uso recomendado (CLI):
 *   php scripts/migrate_client_info_columns.php
 *
 * O script pode ser executado quantas vezes forem necessárias; colunas já existentes são ignoradas.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('Conexão PDO não disponível. Verifique config/database.php.');
}

$tableName = 'tenants';

$columns = [
    'client_type' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'status',
        'description' => 'Tipo de cliente (pf/pj)'
    ],
    'person_full_name' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'client_type',
        'description' => 'Nome completo (PF)'
    ],
    'person_cpf' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'person_full_name',
        'description' => 'CPF (PF)'
    ],
    'person_creci' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'person_cpf',
        'description' => 'CRECI corretor (PF)'
    ],
    'company_legal_name' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'person_creci',
        'description' => 'Razão social (PJ)'
    ],
    'company_trade_name' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'company_legal_name',
        'description' => 'Nome fantasia (PJ)'
    ],
    'company_cnpj' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'company_trade_name',
        'description' => 'CNPJ (PJ)'
    ],
    'company_creci' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'company_cnpj',
        'description' => 'CRECI jurídico (PJ)'
    ],
    'company_responsible_name' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'company_creci',
        'description' => 'Responsável legal (PJ)'
    ],
    'company_responsible_cpf' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'company_responsible_name',
        'description' => 'CPF do responsável (PJ)'
    ],
    'contact_email' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'company_responsible_cpf',
        'description' => 'E-mail principal'
    ],
    'contact_whatsapp' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'contact_email',
        'description' => 'WhatsApp principal'
    ],
    'cep' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'contact_whatsapp',
        'description' => 'CEP'
    ],
    'address_street' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'cep',
        'description' => 'Logradouro'
    ],
    'address_number' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'address_street',
        'description' => 'Número'
    ],
    'address_neighborhood' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'address_number',
        'description' => 'Bairro'
    ],
    'state' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'address_neighborhood',
        'description' => 'UF'
    ],
    'city' => [
        'definition' => 'VARCHAR(191) NULL DEFAULT NULL',
        'after' => 'state',
        'description' => 'Cidade'
    ],
    'notes' => [
        'definition' => 'TEXT NULL',
        'after' => 'city',
        'description' => 'Observações internas'
    ],
];

/**
 * Verifica se a coluna já existe na tabela alvo.
 */
function columnExists(PDO $pdo, string $table, string $column): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :column
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'table' => $table,
        'column' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

$results = [];

foreach ($columns as $column => $data) {
    if (columnExists($pdo, $tableName, $column)) {
        $results[] = sprintf('[OK] Coluna "%s" já existe. Nenhuma alteração necessária.', $column);
        continue;
    }

    $alterSql = sprintf(
        'ALTER TABLE %s ADD COLUMN %s %s%s',
        $tableName,
        $column,
        $data['definition'],
        isset($data['after']) ? ' AFTER ' . $data['after'] : ''
    );

    try {
        $pdo->exec($alterSql);
        $results[] = sprintf('[ADD] Coluna "%s" criada (%s).', $column, $data['description']);
    } catch (Throwable $e) {
        $results[] = sprintf('[ERRO] Falha ao criar coluna "%s": %s', $column, $e->getMessage());
    }
}

$output = implode(PHP_EOL, $results);

if (php_sapi_name() === 'cli') {
    echo $output . PHP_EOL;
} else {
    echo nl2br($output);
}


