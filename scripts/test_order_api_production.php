<?php
/**
 * Script para testar a API de criação de pedidos em produção
 * e diagnosticar o erro 400
 */

// Permitir acesso via web ou CLI
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../master/includes/AsaasConfig.php';
require_once __DIR__ . '/../master/includes/PlanService.php';

function output($message, $isHtml = false) {
    if (php_sapi_name() === 'cli') {
        echo $message . "\n";
    } else {
        if (!$isHtml) {
            echo "<pre>" . htmlspecialchars($message) . "</pre>";
        } else {
            echo $message;
        }
    }
}

function outputSection($title) {
    if (php_sapi_name() === 'cli') {
        output("\n=== $title ===");
    } else {
        output("<h2>$title</h2>", true);
    }
}

// 1. Verificar configuração do Asaas
outputSection("1. Verificação da Configuração do Asaas");

try {
    $config = getAsaasConfig();
    output("✅ Configuração do Asaas carregada com sucesso!");
    output("   Ambiente: " . $config['env']);
    output("   Base URL: " . $config['base_url']);
    output("   API Key: " . (strlen($config['api_key']) > 0 ? substr($config['api_key'], 0, 20) . '...' : 'VAZIA'));
} catch (Exception $e) {
    output("❌ ERRO ao carregar configuração do Asaas: " . $e->getMessage());
    exit(1);
}

// 2. Verificar se há planos disponíveis
outputSection("2. Verificação de Planos Disponíveis");

$plans = getAllPlans(true);
if (empty($plans)) {
    output("❌ Nenhum plano ativo encontrado!");
    exit(1);
}

output("✅ Planos encontrados: " . count($plans));
foreach ($plans as $plan) {
    output("   - {$plan['code']}: {$plan['name']} (R$ " . number_format($plan['total_amount'], 2, ',', '.') . ")");
}

// 3. Preparar dados de teste
outputSection("3. Preparando Dados de Teste");

$testPlan = $plans[0]; // Usar o primeiro plano disponível

// Gerar CPF válido para teste
function generateValidCPF(): string {
    $n1 = rand(0, 9);
    $n2 = rand(0, 9);
    $n3 = rand(0, 9);
    $n4 = rand(0, 9);
    $n5 = rand(0, 9);
    $n6 = rand(0, 9);
    $n7 = rand(0, 9);
    $n8 = rand(0, 9);
    $n9 = rand(0, 9);
    $d1 = $n9*2 + $n8*3 + $n7*4 + $n6*5 + $n5*6 + $n4*7 + $n3*8 + $n2*9 + $n1*10;
    $d1 = 11 - ($d1 % 11);
    if ($d1 >= 10) $d1 = 0;
    $d2 = $d1*2 + $n9*3 + $n8*4 + $n7*5 + $n6*6 + $n5*7 + $n4*8 + $n3*9 + $n2*10 + $n1*11;
    $d2 = 11 - ($d2 % 11);
    if ($d2 >= 10) $d2 = 0;
    return sprintf('%d%d%d%d%d%d%d%d%d%d%d', $n1, $n2, $n3, $n4, $n5, $n6, $n7, $n8, $n9, $d1, $d2);
}

$testData = [
    'plan_code' => $testPlan['code'],
    'customer_name' => 'Teste API Produção',
    'customer_email' => 'teste.api@example.com',
    'customer_whatsapp' => '47991234567', // Formato válido: DDD + 9 dígitos
    'customer_cpf_cnpj' => generateValidCPF(), // CPF válido gerado
    'payment_method' => 'pix',
    'payment_installments' => 1,
    'max_installments' => 1,
];

output("Dados de teste:");
foreach ($testData as $key => $value) {
    output("   $key: $value");
}

// 4. Validar dados localmente (simular validação da API)
outputSection("4. Validação Local dos Dados");

$errors = [];

$name = trim($testData['customer_name'] ?? '');
if ($name === '') {
    $errors[] = 'Nome do cliente é obrigatório';
}

$email = strtolower(trim($testData['customer_email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'E-mail inválido';
}

$planCode = strtoupper(trim($testData['plan_code'] ?? ''));
if ($planCode === '') {
    $errors[] = 'Código do plano é obrigatório';
}

$paymentMethod = strtolower(trim($testData['payment_method'] ?? ''));
$validPaymentMethods = ['credit_card', 'pix', 'boleto'];
if ($paymentMethod === '' || !in_array($paymentMethod, $validPaymentMethods, true)) {
    $errors[] = 'Método de pagamento inválido';
}

if (!empty($errors)) {
    output("❌ Erros de validação encontrados:");
    foreach ($errors as $error) {
        output("   - $error");
    }
    exit(1);
}

output("✅ Validação local passou!");

// 5. Verificar se o plano existe
outputSection("5. Verificação do Plano");

$plan = getPlanByCode($testData['plan_code']);
if (!$plan || (int)$plan['is_active'] !== 1) {
    output("❌ Plano '{$testData['plan_code']}' não encontrado ou inativo!");
    exit(1);
}

output("✅ Plano encontrado:");
output("   - ID: {$plan['id']}");
output("   - Nome: {$plan['name']}");
output("   - Valor Total: R$ " . number_format($plan['total_amount'], 2, ',', '.'));
output("   - Billing Mode: " . ($plan['billing_mode'] ?? 'prepaid_parceled'));

// 6. Testar requisição HTTP
outputSection("6. Testando Requisição HTTP para a API");

$apiUrl = 'https://painel.imobsites.com.br/api/orders/create.php';

// Preparar JSON
$jsonData = json_encode($testData);
if ($jsonData === false) {
    output("❌ Erro ao codificar JSON: " . json_last_error_msg());
    exit(1);
}

output("URL: $apiUrl");
output("Método: POST");
output("Content-Type: application/json");
output("Payload: " . substr($jsonData, 0, 200) . "...");

// Fazer requisição
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

output("\nResultado da Requisição:");
output("HTTP Code: $httpCode");

if ($curlError) {
    output("❌ Erro cURL: $curlError");
}

if ($response) {
    $responseData = json_decode($response, true);
    if ($responseData) {
        output("\nResposta JSON:");
        output(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        if (isset($responseData['success']) && $responseData['success'] === true) {
            output("\n✅ Requisição bem-sucedida!");
        } else {
            output("\n❌ Requisição falhou!");
            if (isset($responseData['message'])) {
                output("Mensagem: " . $responseData['message']);
            }
        }
    } else {
        output("\nResposta (não-JSON):");
        output($response);
    }
} else {
    output("\n❌ Nenhuma resposta recebida!");
}

// 7. Verificar logs (se possível)
outputSection("7. Informações Adicionais");

output("Para verificar os logs de erro do servidor:");
output("1. Acesse o painel de controle do servidor");
output("2. Verifique os logs de erro do Apache/PHP");
output("3. Procure por entradas com '[orders.create]' ou '[asaas.config]'");

output("\nVerificações recomendadas:");
output("- Verifique se o módulo mod_env do Apache está habilitado");
output("- Verifique se as variáveis do .htaccess estão sendo lidas (use scripts/test_asaas_env.php)");
output("- Verifique os logs de erro do PHP para mensagens mais detalhadas");

if (php_sapi_name() !== 'cli') {
    output("\n<a href='test_asaas_env.php'>🔍 Testar Variáveis de Ambiente</a>", true);
}

