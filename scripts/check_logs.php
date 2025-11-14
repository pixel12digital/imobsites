<?php
/**
 * Script para verificar logs recentes
 */

$logFile = 'C:\xampp\php\logs\php_error_log';

if (file_exists($logFile)) {
    $lines = file($logFile);
    $recent = array_slice($lines, -50);
    
    echo "=== Últimas 50 linhas do log PHP ===\n\n";
    foreach ($recent as $line) {
        if (stripos($line, 'asaas') !== false || 
            stripos($line, 'orders') !== false || 
            stripos($line, 'cpf') !== false ||
            stripos($line, 'customer') !== false) {
            echo trim($line) . "\n";
        }
    }
} else {
    echo "Arquivo de log não encontrado: $logFile\n";
    echo "Verificando alternativas...\n";
    
    $altLogs = [
        'C:\xampp\apache\logs\error.log',
        'C:\xampp\apache\logs\localhost-error.log',
    ];
    
    foreach ($altLogs as $alt) {
        if (file_exists($alt)) {
            echo "\nEncontrado: $alt\n";
            $lines = file($alt);
            $recent = array_slice($lines, -30);
            foreach ($recent as $line) {
                if (stripos($line, 'asaas') !== false || 
                    stripos($line, 'orders') !== false || 
                    stripos($line, 'cpf') !== false) {
                    echo trim($line) . "\n";
                }
            }
        }
    }
}

