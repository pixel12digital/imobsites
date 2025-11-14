<?php
/**
 * Script de teste rápido para verificar se CPF está sendo passado
 */

$url = 'http://localhost/imobsites/api/orders/create.php';
$data = [
    'plan_code' => 'P02_ANUAL',
    'customer_name' => 'Teste PIX Sandbox',
    'customer_email' => 'teste.pix@example.com',
    'customer_whatsapp' => '47991234567',
    'customer_cpf_cnpj' => '12345678900',
    'payment_method' => 'pix'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

