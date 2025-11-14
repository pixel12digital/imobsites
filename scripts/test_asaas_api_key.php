<?php
/**
 * Script para testar a chave de API do Asaas diretamente
 * e verificar se está válida
 */

// Permitir acesso via web ou CLI
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../master/includes/AsaasConfig.php';

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

outputSection("1. Carregando Configuração do Asaas");

try {
    $config = getAsaasConfig();
    output("✅ Configuração carregada!");
    output("   Ambiente: " . $config['env']);
    output("   Base URL: " . $config['base_url']);
    output("   API Key (primeiros 30 chars): " . substr($config['api_key'], 0, 30) . "...");
    output("   API Key (comprimento): " . strlen($config['api_key']) . " caracteres");
} catch (Exception $e) {
    output("❌ ERRO: " . $e->getMessage());
    exit(1);
}

outputSection("2. Verificando Formato da Chave de API");

$apiKey = $config['api_key'];
$issues = [];

// Verificar comprimento mínimo
if (strlen($apiKey) < 50) {
    $issues[] = "Chave muito curta (mínimo esperado: 50 caracteres)";
}

// Verificar caracteres inválidos (permite $ no início)
$keyToCheck = ltrim($apiKey, '$');
if (preg_match('/[^\w\-_]/', $keyToCheck)) {
    $issues[] = "Chave contém caracteres inválidos (após remover o prefixo '$')";
}

// Verificar se começa com o prefixo esperado (aceita $aact_prod_ ou aact_)
if (!preg_match('/^\$?aact_(prod_|YTU|hmlg_)?/', $apiKey)) {
    $issues[] = "Chave não começa com o prefixo esperado ('aact_' ou '\$aact_prod_')";
}

if (empty($issues)) {
    output("✅ Formato da chave parece válido");
} else {
    output("⚠️ Problemas encontrados no formato:");
    foreach ($issues as $issue) {
        output("   - $issue");
    }
}

outputSection("3. Testando Requisição à API do Asaas");

// Fazer uma requisição simples para verificar se a chave funciona
// Vamos tentar listar os clientes (endpoint que requer autenticação)

$baseUrl = $config['base_url'];
$testUrl = $baseUrl . '/customers?limit=1';

output("URL de teste: $testUrl");
output("Método: GET");

$ch = curl_init($testUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'access_token: ' . $apiKey,
        'User-Agent: imobsites-test/1.0',
    ],
]);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
$statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

output("\nResultado da Requisição:");
output("HTTP Status Code: $statusCode");

if ($curlError) {
    output("❌ Erro cURL: $curlError");
}

if ($responseBody) {
    $responseData = json_decode($responseBody, true);
    
    if ($responseData) {
        output("\nResposta JSON:");
        output(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        if ($statusCode === 200) {
            output("\n✅ Chave de API VÁLIDA! A requisição foi bem-sucedida.");
        } elseif ($statusCode === 401) {
            output("\n❌ Chave de API INVÁLIDA! Erro de autenticação (401).");
            if (isset($responseData['errors']) && is_array($responseData['errors'])) {
                foreach ($responseData['errors'] as $error) {
                    if (isset($error['description'])) {
                        output("   Erro: " . $error['description']);
                    } elseif (isset($error['message'])) {
                        output("   Erro: " . $error['message']);
                    }
                }
            } elseif (isset($responseData['message'])) {
                output("   Mensagem: " . $responseData['message']);
            }
        } elseif ($statusCode === 403) {
            output("\n❌ Chave de API sem permissão! Erro de autorização (403).");
        } else {
            output("\n⚠️ Resposta inesperada do servidor.");
        }
    } else {
        output("\nResposta (não-JSON):");
        output(substr($responseBody, 0, 500));
    }
} else {
    output("\n❌ Nenhuma resposta recebida do servidor.");
}

outputSection("4. Verificando Ambiente vs Chave");

$env = $config['env'];
$baseUrl = $config['base_url'];

$isSandboxUrl = strpos($baseUrl, 'sandbox') !== false;
$isProductionUrl = strpos($baseUrl, 'api.asaas.com') !== false && strpos($baseUrl, 'sandbox') === false;

output("Ambiente configurado: $env");
output("URL base: $baseUrl");
output("É URL sandbox: " . ($isSandboxUrl ? 'Sim' : 'Não'));
output("É URL produção: " . ($isProductionUrl ? 'Sim' : 'Não'));

if ($env === 'sandbox' && !$isSandboxUrl) {
    output("⚠️ AVISO: Ambiente configurado como 'sandbox' mas URL não é de sandbox!");
}

if ($env === 'production' && !$isProductionUrl) {
    output("⚠️ AVISO: Ambiente configurado como 'production' mas URL não é de produção!");
}

outputSection("5. Recomendações");

if ($statusCode === 401) {
    output("A chave de API está retornando erro 401 (não autorizado).");
    output("\nPossíveis causas:");
    output("1. A chave foi revogada ou expirada no painel do Asaas");
    output("2. A chave está incorreta ou foi copiada com espaços extras");
    output("3. A chave é de produção mas está sendo usada em sandbox (ou vice-versa)");
    output("4. A chave foi corrompida durante a cópia (caracteres especiais)");
    output("\nSoluções:");
    output("1. Acesse o painel do Asaas: https://www.asaas.com");
    output("2. Vá em Configurações > Integrações > API");
    output("3. Gere uma nova chave de API");
    output("4. Copie a chave COMPLETA (sem espaços no início/fim)");
    output("5. Atualize o .htaccess com a nova chave");
    output("6. Reinicie o Apache ou aguarde alguns minutos");
} elseif ($statusCode === 200) {
    output("✅ A chave de API está funcionando corretamente!");
    output("Se ainda estiver tendo problemas, verifique:");
    output("1. Se o erro ocorre em outro ponto do código");
    output("2. Se há logs de erro mais detalhados");
    output("3. Se o problema é específico de criação de cliente");
}

if (php_sapi_name() !== 'cli') {
    output("\n<a href='test_asaas_env.php'>🔍 Voltar para Teste de Variáveis</a>", true);
}

