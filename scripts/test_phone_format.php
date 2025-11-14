<?php
/**
 * Script para testar formatação de telefone para Asaas
 */

require_once __DIR__ . '/../master/includes/AsaasBillingService.php';

$testPhones = [
    '47999999999',
    '(47) 99999-9999',
    '47 99999-9999',
    '+55 47 99999-9999',
    '5547999999999',
    '+5547999999999',
];

echo "=== Teste de Formatação de Telefone para Asaas ===\n\n";

foreach ($testPhones as $phone) {
    $formatted = formatPhoneForAsaas($phone);
    echo "Original: " . str_pad($phone, 25) . " → Formatado: " . ($formatted ?? 'NULL') . "\n";
}

echo "\n=== Teste com número do exemplo ===\n";
$examplePhone = '47999999999';
$formatted = formatPhoneForAsaas($examplePhone);
echo "Original: $examplePhone\n";
echo "Formatado: " . ($formatted ?? 'NULL') . "\n";
echo "Esperado: +5547999999999\n";
echo "Match: " . ($formatted === '+5547999999999' ? '✅ SIM' : '❌ NÃO') . "\n";

