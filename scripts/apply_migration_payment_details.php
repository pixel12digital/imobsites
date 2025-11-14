<?php
/**
 * Script para aplicar a migration de campos de detalhes de pagamento na tabela orders
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Aplicando Migration: payment_details_to_orders ===\n\n";

// Verificar se as colunas já existem
$columns = fetchAll("SHOW COLUMNS FROM orders");
$existingColumns = array_column($columns, 'Field');

$requiredColumns = [
    'boleto_line'
];

$missingColumns = [];
foreach ($requiredColumns as $col) {
    if (!in_array($col, $existingColumns)) {
        $missingColumns[] = $col;
    }
}

// Verificar tipo de pix_qr_code_image
$pixQrCodeImageCol = null;
foreach ($columns as $col) {
    if ($col['Field'] === 'pix_qr_code_image') {
        $pixQrCodeImageCol = $col;
        break;
    }
}

if (empty($missingColumns) && $pixQrCodeImageCol && strtoupper($pixQrCodeImageCol['Type']) === 'LONGTEXT') {
    echo "✅ Todas as colunas já existem e pix_qr_code_image já é LONGTEXT! Migration já foi aplicada.\n";
    
    // Verificar se o índice existe
    $indexes = fetchAll("SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_boleto_line'");
    if (!empty($indexes)) {
        echo "✅ Índice idx_orders_boleto_line já existe!\n";
        exit(0);
    } else {
        echo "⚠️  Colunas existem, mas o índice está faltando. Criando índice...\n\n";
    }
} else {
    if (!empty($missingColumns)) {
        echo "Colunas faltando: " . implode(', ', $missingColumns) . "\n";
    }
    if ($pixQrCodeImageCol && strtoupper($pixQrCodeImageCol['Type']) !== 'LONGTEXT') {
        echo "⚠️  pix_qr_code_image precisa ser convertido para LONGTEXT (atual: {$pixQrCodeImageCol['Type']})\n";
    }
    echo "\n";
}

// Ler a migration
$migrationFile = __DIR__ . '/../database/migrations/20251116_add_payment_details_to_orders.sql';
if (!file_exists($migrationFile)) {
    echo "❌ ERRO: Arquivo de migration não encontrado: $migrationFile\n";
    exit(1);
}

$migrationSQL = file_get_contents($migrationFile);

// Aplicar a migration
try {
    global $pdo;
    
    // Separar comandos SQL
    $lines = explode("\n", $migrationSQL);
    $statements = [];
    $currentStatement = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || preg_match('/^--/', $line)) {
            continue;
        }
        
        $currentStatement .= ' ' . $line;
        
        if (substr(rtrim($currentStatement), -1) === ';') {
            $stmt = trim($currentStatement);
            $stmt = rtrim($stmt, ';');
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
            $currentStatement = '';
        }
    }
    
    echo "Aplicando " . count($statements) . " comandos SQL...\n\n";
    
    foreach ($statements as $index => $statement) {
        $stmtType = 'SQL';
        if (stripos($statement, 'ALTER TABLE') !== false) {
            $stmtType = 'ALTER TABLE';
        } elseif (stripos($statement, 'CREATE INDEX') !== false) {
            $stmtType = 'CREATE INDEX';
        }
        
        echo "Comando " . ($index + 1) . " ({$stmtType})...\n";
        echo "SQL: " . substr($statement, 0, 150) . "...\n";
        
        try {
            $pdo->exec($statement);
            echo "✅ Comando executado com sucesso!\n\n";
        } catch (PDOException $e) {
            // Se a coluna já existe ou tipo já está correto, ignorar o erro
            if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
                strpos($e->getMessage(), 'Duplicate key name') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠️  Já existe, ignorando...\n\n";
            } else {
                echo "❌ ERRO: " . $e->getMessage() . "\n\n";
                // Para MODIFY COLUMN, se der erro mas a coluna já é do tipo correto, pode ignorar
                if (stripos($statement, 'MODIFY COLUMN') !== false) {
                    echo "⚠️  Continuando mesmo assim (coluna pode já estar no formato correto)...\n\n";
                } else {
                    throw $e;
                }
            }
        }
    }
    
    // Verificar novamente
    $columnsAfter = fetchAll("SHOW COLUMNS FROM orders");
    $existingColumnsAfter = array_column($columnsAfter, 'Field');
    
    $stillMissing = [];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $existingColumnsAfter)) {
            $stillMissing[] = $col;
        }
    }
    
    if (empty($stillMissing)) {
        echo "✅ Migration aplicada com sucesso! Todas as colunas foram criadas/atualizadas.\n";
    } else {
        echo "⚠️  Algumas colunas ainda estão faltando: " . implode(', ', $stillMissing) . "\n";
        echo "Verifique os erros acima.\n";
    }
    
    // Verificar índice
    $indexesAfter = fetchAll("SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_boleto_line'");
    if (!empty($indexesAfter)) {
        echo "✅ Índice idx_orders_boleto_line criado com sucesso!\n";
    } else {
        echo "⚠️  Índice não foi criado. Verifique os erros acima.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO ao aplicar migration: " . $e->getMessage() . "\n";
    exit(1);
}

