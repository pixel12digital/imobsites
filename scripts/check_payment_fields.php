<?php
/**
 * Script para verificar campos de pagamento na tabela orders
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Verificando Campos de Pagamento na Tabela Orders ===\n\n";

$paymentFields = [
    'pix_payload',
    'pix_qr_code_image',
    'boleto_url',
    'boleto_barcode',
    'boleto_line'
];

$columns = fetchAll("SHOW COLUMNS FROM orders");
$existingColumns = [];

foreach ($columns as $col) {
    $existingColumns[$col['Field']] = $col;
}

echo "Status dos campos:\n";
foreach ($paymentFields as $fieldName) {
    if (isset($existingColumns[$fieldName])) {
        $col = $existingColumns[$fieldName];
        echo "✅ {$fieldName}\n";
        echo "   Tipo: {$col['Type']}\n";
        echo "   Null: {$col['Null']}\n";
        echo "   Default: " . ($col['Default'] ?? 'NULL') . "\n\n";
    } else {
        echo "❌ {$fieldName} - NÃO ENCONTRADO\n\n";
    }
}

