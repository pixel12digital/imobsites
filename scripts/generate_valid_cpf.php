<?php
/**
 * Gera um CPF válido para testes
 * Baseado no algoritmo de validação de CPF brasileiro
 */

function generateValidCPF(): string
{
    // Gera 9 primeiros dígitos aleatórios
    $n1 = rand(0, 9);
    $n2 = rand(0, 9);
    $n3 = rand(0, 9);
    $n4 = rand(0, 9);
    $n5 = rand(0, 9);
    $n6 = rand(0, 9);
    $n7 = rand(0, 9);
    $n8 = rand(0, 9);
    $n9 = rand(0, 9);
    
    // Calcula primeiro dígito verificador
    $d1 = $n9*2 + $n8*3 + $n7*4 + $n6*5 + $n5*6 + $n4*7 + $n3*8 + $n2*9 + $n1*10;
    $d1 = 11 - ($d1 % 11);
    if ($d1 >= 10) $d1 = 0;
    
    // Calcula segundo dígito verificador
    $d2 = $d1*2 + $n9*3 + $n8*4 + $n7*5 + $n6*6 + $n5*7 + $n4*8 + $n3*9 + $n2*10 + $n1*11;
    $d2 = 11 - ($d2 % 11);
    if ($d2 >= 10) $d2 = 0;
    
    return sprintf('%d%d%d%d%d%d%d%d%d%d%d', $n1, $n2, $n3, $n4, $n5, $n6, $n7, $n8, $n9, $d1, $d2);
}

// CPFs válidos conhecidos para testes (geralmente aceitos em sandboxes)
$testCPFs = [
    '11144477735', // CPF válido conhecido
    '12345678909', // CPF válido conhecido
    generateValidCPF(), // Gera um novo
    generateValidCPF(), // Gera outro
];

echo "=== CPFs Válidos para Teste ===\n\n";
foreach ($testCPFs as $cpf) {
    echo "$cpf\n";
}

echo "\n=== Use um destes CPFs no teste ===\n";
echo "Exemplo de comando:\n";
echo '$body = \'{"plan_code":"P02_ANUAL","customer_name":"Teste PIX Sandbox","customer_email":"teste.pix@example.com","customer_whatsapp":"47991234567","customer_cpf_cnpj":"' . $testCPFs[0] . '","payment_method":"pix"}\';\n';

