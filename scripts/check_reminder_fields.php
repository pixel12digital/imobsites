<?php
/**
 * Script para verificar se os campos de lembretes foram criados corretamente
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Verificando Campos de Lembretes na Tabela Orders ===\n\n";

$requiredColumns = [
    'first_reminder_sent_at',
    'last_reminder_sent_at',
    'reminder_count'
];

$columns = fetchAll("SHOW COLUMNS FROM orders");
$existingColumns = [];

foreach ($columns as $col) {
    $existingColumns[$col['Field']] = $col;
}

echo "Status das colunas:\n";
foreach ($requiredColumns as $colName) {
    if (isset($existingColumns[$colName])) {
        $col = $existingColumns[$colName];
        echo "✅ {$colName}\n";
        echo "   Tipo: {$col['Type']}\n";
        echo "   Null: {$col['Null']}\n";
        echo "   Default: " . ($col['Default'] ?? 'NULL') . "\n";
        if (isset($col['Comment']) && $col['Comment'] !== '') {
            echo "   Comentário: {$col['Comment']}\n";
        }
        echo "\n";
    } else {
        echo "❌ {$colName} - NÃO ENCONTRADA\n\n";
    }
}

// Verificar índice
echo "Verificando índice:\n";
$indexes = fetchAll("SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_reminder'");
if (!empty($indexes)) {
    echo "✅ Índice idx_orders_reminder existe\n";
    echo "   Colunas: " . implode(', ', array_column($indexes, 'Column_name')) . "\n";
} else {
    echo "❌ Índice idx_orders_reminder NÃO ENCONTRADO\n";
}

