<?php
/**
 * Script de teste para pagamento com cartão de crédito
 */

$url = 'http://localhost/imobsites/api/orders/create.php';
$data = [
    'plan_code' => 'P02_ANUAL',
    'customer_name' => 'Teste Cartão Sandbox',
    'customer_email' => 'teste.card@example.com',
    'customer_whatsapp' => '47991234567',
    'customer_cpf_cnpj' => '11144477735',
    'payment_method' => 'credit_card',
    'card' => [
        'cardNumber' => '4111111111111111',
        'holderName' => 'TESTE CARTAO',
        'expiryMonth' => '12',
        'expiryYear' => '2026',
        'ccv' => '123',
        'postalCode' => '88010000',
        'address' => 'Rua Teste',
        'addressNumber' => '123',
        'neighborhood' => 'Centro',
        'city' => 'Florianopolis',
        'state' => 'SC'
    ]
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
$decoded = json_decode($response, true);
if ($decoded) {
    echo "Response (formatted):\n";
    echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "Response: $response\n";
}

