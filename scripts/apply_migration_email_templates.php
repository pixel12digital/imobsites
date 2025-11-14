<?php
/**
 * Script para aplicar a migration de templates de e-mail (email_templates)
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Aplicando Migration: create_email_templates_table ===\n\n";

// Verificar se a tabela já existe
try {
    global $pdo;
    
    $tableExists = false;
    $result = $pdo->query("SHOW TABLES LIKE 'email_templates'");
    if ($result && $result->rowCount() > 0) {
        $tableExists = true;
    }
    
    if ($tableExists) {
        // Verificar se tem as colunas necessárias
        $columns = fetchAll("SHOW COLUMNS FROM email_templates");
        $existingColumns = array_column($columns, 'Field');
        
        $requiredColumns = [
            'id', 'name', 'slug', 'event_type', 'description', 
            'subject', 'html_body', 'text_body', 'is_active', 
            'created_at', 'updated_at'
        ];
        
        $missingColumns = [];
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $existingColumns)) {
                $missingColumns[] = $col;
            }
        }
        
        if (empty($missingColumns)) {
            echo "✅ Tabela email_templates já existe com todas as colunas necessárias!\n";
            
            // Verificar se tem templates padrão
            $templates = fetchAll("SELECT COUNT(*) as cnt FROM email_templates");
            $templateCount = (int)($templates[0]['cnt'] ?? 0);
            
            if ($templateCount > 0) {
                echo "✅ Tabela já possui {$templateCount} template(s).\n";
            } else {
                echo "ℹ️  Tabela está vazia. Os templates padrão serão inseridos.\n";
            }
        } else {
            echo "⚠️  Tabela existe mas faltam colunas: " . implode(', ', $missingColumns) . "\n";
            echo "Aplicando migration para adicionar as colunas faltantes...\n\n";
        }
    } else {
        echo "Tabela email_templates não existe. Criando tabela...\n\n";
    }
} catch (PDOException $e) {
    echo "⚠️  Erro ao verificar tabela: " . $e->getMessage() . "\n";
    echo "Tentando criar tabela...\n\n";
}

// Ler a migration
$migrationFile = __DIR__ . '/../database/migrations/20251117_create_email_templates_table.sql';
if (!file_exists($migrationFile)) {
    echo "❌ ERRO: Arquivo de migration não encontrado: $migrationFile\n";
    exit(1);
}

$migrationSQL = file_get_contents($migrationFile);

// Aplicar a migration
try {
    global $pdo;
    
    // Separar comandos SQL (separando por ; mas considerando INSERT que pode ter múltiplos VALUES)
    $lines = explode("\n", $migrationSQL);
    $statements = [];
    $currentStatement = '';
    $inInsert = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || preg_match('/^--/', $line)) {
            continue;
        }
        
        // Detectar início de INSERT multi-valor
        if (preg_match('/^INSERT INTO/', $line)) {
            $inInsert = true;
        }
        
        $currentStatement .= ' ' . $line;
        
        // Se é um INSERT multi-valor, continuar até encontrar o último ponto e vírgula
        if ($inInsert && substr(rtrim($line), -1) === ';') {
            $inInsert = false;
        }
        
        // Para comandos normais ou INSERT finalizado
        if (!$inInsert && substr(rtrim($currentStatement), -1) === ';') {
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
        } elseif (stripos($statement, 'INSERT INTO') !== false) {
            $stmtType = 'INSERT';
        }
        
        echo "Comando " . ($index + 1) . " ({$stmtType})...\n";
        $preview = substr($statement, 0, 150);
        if (strlen($statement) > 150) {
            $preview .= "...";
        }
        echo "SQL: " . $preview . "\n";
        
        try {
            $pdo->exec($statement);
            echo "✅ Comando executado com sucesso!\n\n";
        } catch (PDOException $e) {
            // Se já existe, ignorar o erro
            if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
                strpos($e->getMessage(), 'Duplicate key name') !== false ||
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⚠️  Já existe, ignorando...\n\n";
            } else {
                echo "❌ ERRO: " . $e->getMessage() . "\n\n";
                // Para CREATE TABLE IF NOT EXISTS, se der erro mas a tabela já existe, pode ignorar
                if (stripos($statement, 'CREATE TABLE IF NOT EXISTS') !== false) {
                    echo "⚠️  Continuando mesmo assim (tabela pode já existir)...\n\n";
                } elseif (stripos($statement, 'INSERT INTO') !== false && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo "⚠️  Template já existe, ignorando...\n\n";
                } else {
                    throw $e;
                }
            }
        }
    }
    
    // Verificar novamente
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'email_templates'");
        if ($result && $result->rowCount() > 0) {
            $columns = fetchAll("SHOW COLUMNS FROM email_templates");
            $existingColumns = array_column($columns, 'Field');
            
            $requiredColumns = [
                'id', 'name', 'slug', 'event_type', 'description', 
                'subject', 'html_body', 'text_body', 'is_active', 
                'created_at', 'updated_at'
            ];
            
            $stillMissing = [];
            foreach ($requiredColumns as $col) {
                if (!in_array($col, $existingColumns)) {
                    $stillMissing[] = $col;
                }
            }
            
            if (empty($stillMissing)) {
                echo "✅ Migration aplicada com sucesso! Tabela email_templates criada com todas as colunas necessárias.\n";
                
                // Verificar templates
                $templates = fetchAll("SELECT COUNT(*) as cnt FROM email_templates");
                $templateCount = (int)($templates[0]['cnt'] ?? 0);
                
                if ($templateCount > 0) {
                    echo "✅ Tabela possui {$templateCount} template(s) cadastrado(s).\n";
                    
                    // Listar templates
                    $templateList = fetchAll("SELECT slug, name, event_type FROM email_templates ORDER BY event_type, id");
                    echo "\nTemplates cadastrados:\n";
                    foreach ($templateList as $tmpl) {
                        echo "  - {$tmpl['slug']} ({$tmpl['name']}) - Evento: {$tmpl['event_type']}\n";
                    }
                } else {
                    echo "ℹ️  Tabela está vazia. Execute a migration novamente ou insira templates manualmente.\n";
                }
            } else {
                echo "⚠️  Algumas colunas ainda estão faltando: " . implode(', ', $stillMissing) . "\n";
                echo "Verifique os erros acima.\n";
                exit(1);
            }
        } else {
            echo "❌ ERRO: Tabela email_templates não foi criada. Verifique os erros acima.\n";
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

