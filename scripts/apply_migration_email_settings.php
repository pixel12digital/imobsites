<?php
/**
 * Script para aplicar a migration de configurações de e-mail (email_settings)
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Aplicando Migration: create_email_settings_table ===\n\n";

// Verificar se a tabela já existe
try {
    global $pdo;
    
    $tableExists = false;
    $result = $pdo->query("SHOW TABLES LIKE 'email_settings'");
    if ($result && $result->rowCount() > 0) {
        $tableExists = true;
    }
    
    if ($tableExists) {
        // Verificar se tem as colunas necessárias
        $columns = fetchAll("SHOW COLUMNS FROM email_settings");
        $existingColumns = array_column($columns, 'Field');
        
        $requiredColumns = [
            'id', 'transport', 'host', 'port', 'encryption', 
            'username', 'password', 'from_name', 'from_email', 
            'reply_to_email', 'bcc_email', 'created_at', 'updated_at'
        ];
        
        $missingColumns = [];
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $existingColumns)) {
                $missingColumns[] = $col;
            }
        }
        
        if (empty($missingColumns)) {
            echo "✅ Tabela email_settings já existe com todas as colunas necessárias! Migration já foi aplicada.\n";
            
            // Verificar se tem registro
            $record = fetch("SELECT COUNT(*) as cnt FROM email_settings");
            if ($record && (int)$record['cnt'] > 0) {
                echo "✅ Tabela já possui " . (int)$record['cnt'] . " registro(s).\n";
            } else {
                echo "ℹ️  Tabela está vazia (o sistema criará um registro padrão na primeira utilização).\n";
            }
            exit(0);
        } else {
            echo "⚠️  Tabela existe mas faltam colunas: " . implode(', ', $missingColumns) . "\n";
            echo "Aplicando migration para adicionar as colunas faltantes...\n\n";
        }
    } else {
        echo "Tabela email_settings não existe. Criando tabela...\n\n";
    }
} catch (PDOException $e) {
    echo "⚠️  Erro ao verificar tabela: " . $e->getMessage() . "\n";
    echo "Tentando criar tabela...\n\n";
}

// Ler a migration
$migrationFile = __DIR__ . '/../database/migrations/20251117_create_email_settings_table.sql';
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
    
    // Se sobrou algo sem ponto e vírgula, adicionar
    if (!empty(trim($currentStatement))) {
        $statements[] = trim($currentStatement);
    }
    
    echo "Aplicando " . count($statements) . " comando(s) SQL...\n\n";
    
    foreach ($statements as $index => $statement) {
        $stmtType = 'SQL';
        if (stripos($statement, 'CREATE TABLE') !== false) {
            $stmtType = 'CREATE TABLE';
        } elseif (stripos($statement, 'ALTER TABLE') !== false) {
            $stmtType = 'ALTER TABLE';
        }
        
        echo "Comando " . ($index + 1) . " ({$stmtType})...\n";
        echo "SQL: " . substr($statement, 0, 150) . "...\n";
        
        try {
            $pdo->exec($statement);
            echo "✅ Comando executado com sucesso!\n\n";
        } catch (PDOException $e) {
            // Se a tabela/coluna já existe, ignorar o erro
            if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
                strpos($e->getMessage(), 'Duplicate key name') !== false ||
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠️  Já existe, ignorando...\n\n";
            } else {
                echo "❌ ERRO: " . $e->getMessage() . "\n\n";
                // Para CREATE TABLE IF NOT EXISTS, se der erro mas a tabela já existe, pode ignorar
                if (stripos($statement, 'CREATE TABLE IF NOT EXISTS') !== false) {
                    echo "⚠️  Continuando mesmo assim (tabela pode já existir)...\n\n";
                } else {
                    throw $e;
                }
            }
        }
    }
    
    // Verificar novamente
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'email_settings'");
        if ($result && $result->rowCount() > 0) {
            $columns = fetchAll("SHOW COLUMNS FROM email_settings");
            $existingColumns = array_column($columns, 'Field');
            
            $requiredColumns = [
                'id', 'transport', 'host', 'port', 'encryption', 
                'username', 'password', 'from_name', 'from_email', 
                'reply_to_email', 'bcc_email', 'created_at', 'updated_at'
            ];
            
            $stillMissing = [];
            foreach ($requiredColumns as $col) {
                if (!in_array($col, $existingColumns)) {
                    $stillMissing[] = $col;
                }
            }
            
            if (empty($stillMissing)) {
                echo "✅ Migration aplicada com sucesso! Tabela email_settings criada com todas as colunas necessárias.\n";
                
                // Verificar se tem registro
                $record = fetch("SELECT COUNT(*) as cnt FROM email_settings");
                if ($record && (int)$record['cnt'] > 0) {
                    echo "✅ Tabela possui " . (int)$record['cnt'] . " registro(s).\n";
                } else {
                    echo "ℹ️  Tabela está vazia. O sistema criará um registro padrão na primeira utilização do MailService.\n";
                }
            } else {
                echo "⚠️  Algumas colunas ainda estão faltando: " . implode(', ', $stillMissing) . "\n";
                echo "Verifique os erros acima.\n";
                exit(1);
            }
        } else {
            echo "❌ ERRO: Tabela email_settings não foi criada. Verifique os erros acima.\n";
            exit(1);
        }
    } catch (PDOException $e) {
        echo "⚠️  Erro ao verificar tabela após migration: " . $e->getMessage() . "\n";
        echo "A migration pode ter sido aplicada parcialmente. Verifique manualmente.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ ERRO ao aplicar migration: " . $e->getMessage() . "\n";
    exit(1);
}

