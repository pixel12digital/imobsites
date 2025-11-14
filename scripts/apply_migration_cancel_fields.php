<?php
/**
 * Script para aplicar a migration de campos de cancelamento na tabela orders
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Aplicando Migration: cancel_fields_to_orders ===\n\n";

// Verificar se as colunas já existem
$columns = fetchAll("SHOW COLUMNS FROM orders");
$existingColumns = array_column($columns, 'Field');

$requiredColumns = [
    'canceled_at',
    'cancel_reason'
];

$missingColumns = [];
foreach ($requiredColumns as $col) {
    if (!in_array($col, $existingColumns)) {
        $missingColumns[] = $col;
    }
}

if (empty($missingColumns)) {
    echo "✅ Todas as colunas já existem! Migration já foi aplicada.\n";
    exit(0);
}

echo "Colunas faltando: " . implode(', ', $missingColumns) . "\n\n";

// Ler a migration
$migrationFile = __DIR__ . '/../database/migrations/20251115_add_cancel_fields_to_orders.sql';
if (!file_exists($migrationFile)) {
    echo "❌ ERRO: Arquivo de migration não encontrado: $migrationFile\n";
    exit(1);
}

$migrationSQL = file_get_contents($migrationFile);

// Aplicar a migration
try {
    global $pdo;
    
    // Separar ALTER TABLE dos CREATE INDEX
    $lines = explode("\n", $migrationSQL);
    $alterStatements = [];
    $indexStatements = [];
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
            
            if (stripos($stmt, 'ALTER TABLE') !== false) {
                $alterStatements[] = $stmt;
            } elseif (stripos($stmt, 'CREATE INDEX') !== false) {
                $indexStatements[] = $stmt;
            }
            
            $currentStatement = '';
        }
    }
    
    echo "Aplicando " . (count($alterStatements) + count($indexStatements)) . " comandos SQL...\n\n";
    
    // Primeiro, executar ALTER TABLE
    echo "=== Executando ALTER TABLE ===\n";
    foreach ($alterStatements as $index => $statement) {
        echo "Comando " . ($index + 1) . " (ALTER TABLE)...\n";
        echo "SQL: " . substr($statement, 0, 150) . "...\n";
        
        try {
            $pdo->exec($statement);
            echo "✅ Comando executado com sucesso!\n\n";
        } catch (PDOException $e) {
            // Se a coluna já existe, ignorar o erro
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "⚠️  Coluna já existe, ignorando...\n\n";
            } else {
                echo "❌ ERRO: " . $e->getMessage() . "\n\n";
                throw $e;
            }
        }
    }
    
    // Depois, executar CREATE INDEX
    echo "=== Executando CREATE INDEX ===\n";
    foreach ($indexStatements as $index => $statement) {
        echo "Comando " . ($index + 1) . " (CREATE INDEX)...\n";
        echo "SQL: " . substr($statement, 0, 100) . "...\n";
        
        try {
            $pdo->exec($statement);
            echo "✅ Comando executado com sucesso!\n\n";
        } catch (PDOException $e) {
            // Se o índice já existe, ignorar o erro
            if (strpos($e->getMessage(), 'Duplicate key name') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠️  Índice já existe, ignorando...\n\n";
            } else {
                echo "❌ ERRO: " . $e->getMessage() . "\n\n";
                throw $e;
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
        echo "✅ Migration aplicada com sucesso! Todas as colunas foram criadas.\n";
    } else {
        echo "⚠️  Algumas colunas ainda estão faltando: " . implode(', ', $stillMissing) . "\n";
        echo "Verifique os erros acima.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO ao aplicar migration: " . $e->getMessage() . "\n";
    exit(1);
}

