<?php
/**
 * Script de debug detalhado para verificar problemas com a chave de API do Asaas
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

outputSection("1. Análise Detalhada da Chave de API");

try {
    $config = getAsaasConfig();
    $apiKey = $config['api_key'];
    
    output("Comprimento: " . strlen($apiKey) . " caracteres");
    output("Primeiros 50 caracteres: " . substr($apiKey, 0, 50));
    output("Últimos 20 caracteres: " . substr($apiKey, -20));
    
    // Verificar espaços
    $trimmed = trim($apiKey);
    if ($trimmed !== $apiKey) {
        output("⚠️ ATENÇÃO: A chave tem espaços extras no início ou fim!");
        output("   Comprimento original: " . strlen($apiKey));
        output("   Comprimento após trim: " . strlen($trimmed));
        output("   Diferença: " . (strlen($apiKey) - strlen($trimmed)) . " caracteres");
    } else {
        output("✅ Sem espaços extras detectados");
    }
    
    // Verificar caracteres especiais
    $hasSpecialChars = preg_match('/[^\w\-_]/', $apiKey);
    if ($hasSpecialChars) {
        output("⚠️ ATENÇÃO: A chave contém caracteres especiais que podem causar problemas");
        // Mostrar caracteres especiais
        $special = [];
        for ($i = 0; $i < strlen($apiKey); $i++) {
            $char = $apiKey[$i];
            if (!preg_match('/[\w\-_]/', $char)) {
                $special[] = "Posição $i: '$char' (ASCII " . ord($char) . ")";
            }
        }
        if (!empty($special)) {
            output("   Caracteres especiais encontrados:");
            foreach (array_slice($special, 0, 10) as $info) {
                output("   - $info");
            }
        }
    } else {
        output("✅ Apenas caracteres alfanuméricos, hífens e underscores");
    }
    
    // Verificar formato
    if (preg_match('/^aact_[a-z0-9_]+$/', $apiKey)) {
        output("✅ Formato da chave parece correto (começa com 'aact_')");
    } else {
        output("⚠️ Formato da chave pode estar incorreto");
        output("   Esperado: começar com 'aact_' seguido de caracteres alfanuméricos");
    }
    
    // Mostrar representação hexadecimal (útil para debug)
    output("\nRepresentação hexadecimal (primeiros 100 bytes):");
    $hex = bin2hex(substr($apiKey, 0, 50));
    output("   " . chunk_split($hex, 40, "\n   "));
    
} catch (Exception $e) {
    output("❌ ERRO: " . $e->getMessage());
    exit(1);
}

outputSection("2. Testando Diferentes Formatos de Header");

$apiKey = $config['api_key'];
$baseUrl = $config['base_url'];
$testUrl = $baseUrl . '/customers?limit=1';

// Formatos de header para testar
$headerFormats = [
    'access_token' => ['access_token: ' . $apiKey],
    'asaas-access-token' => ['asaas-access-token: ' . $apiKey],
    'Authorization Bearer' => ['Authorization: Bearer ' . $apiKey],
    'X-API-Key' => ['X-API-Key: ' . $apiKey],
];

foreach ($headerFormats as $formatName => $headers) {
    output("\nTestando formato: $formatName");
    
    $ch = curl_init($testUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: imobsites-test/1.0',
        ], $headers),
    ]);
    
    $responseBody = curl_exec($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    output("   HTTP Status: $statusCode");
    
    if ($statusCode === 200) {
        output("   ✅ SUCESSO com formato '$formatName'!");
        break;
    } elseif ($statusCode === 401) {
        $responseData = json_decode($responseBody, true);
        if (isset($responseData['errors'][0]['description'])) {
            output("   ❌ " . $responseData['errors'][0]['description']);
        }
    }
}

outputSection("3. Verificando Chave com Trim");

// Testar com chave após trim (caso tenha espaços)
$trimmedKey = trim($apiKey);
if ($trimmedKey !== $apiKey) {
    output("Testando com chave após remover espaços extras...");
    
    $ch = curl_init($testUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'access_token: ' . $trimmedKey,
            'User-Agent: imobsites-test/1.0',
        ],
    ]);
    
    $responseBody = curl_exec($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    output("HTTP Status: $statusCode");
    
    if ($statusCode === 200) {
        output("✅ SUCESSO! O problema era espaços extras na chave!");
        output("\n⚠️ IMPORTANTE: Atualize o .htaccess removendo os espaços extras da chave.");
    }
}

outputSection("4. Instruções para Gerar Nova Chave");

output("Como gerar uma nova chave de API no Asaas:");
output("");
output("1. Acesse: https://www.asaas.com");
output("2. Faça login na sua conta");
output("3. Vá em: Configurações > Integrações > API");
output("4. Se já existe uma chave:");
output("   - Clique em 'Revogar' ou 'Excluir' na chave antiga");
output("   - Aguarde alguns segundos");
output("5. Clique em 'Gerar Nova Chave' ou 'Criar Chave de API'");
output("6. IMPORTANTE ao copiar:");
output("   - Selecione TODA a chave (do início ao fim)");
output("   - Não deixe espaços no início ou fim");
output("   - Copie exatamente como aparece");
output("   - A chave deve ter aproximadamente 150-200 caracteres");
output("7. Cole no .htaccess entre as aspas:");
output("   SetEnv ASAAS_API_KEY \"COLE_A_CHAVE_AQUI\"");
output("8. Verifique que não há quebras de linha dentro das aspas");
output("9. Salve o arquivo");
output("10. Aguarde 1-2 minutos ou reinicie o Apache");

outputSection("5. Verificação do .htaccess");

$htaccessPath = __DIR__ . '/../.htaccess';
if (file_exists($htaccessPath)) {
    $htaccessContent = file_get_contents($htaccessPath);
    
    // Procurar pela linha com ASAAS_API_KEY
    if (preg_match('/SetEnv\s+ASAAS_API_KEY\s+"([^"]+)"/', $htaccessContent, $matches)) {
        $keyInHtaccess = $matches[1];
        output("Chave encontrada no .htaccess:");
        output("   Comprimento: " . strlen($keyInHtaccess) . " caracteres");
        output("   Primeiros 30 chars: " . substr($keyInHtaccess, 0, 30) . "...");
        
        // Comparar com a chave carregada
        if ($keyInHtaccess === $apiKey) {
            output("✅ Chave no .htaccess corresponde à chave carregada");
        } else {
            output("⚠️ ATENÇÃO: Chave no .htaccess é diferente da chave carregada!");
            output("   Isso pode indicar que há espaços extras ou caracteres especiais");
        }
        
        // Verificar espaços
        if (trim($keyInHtaccess) !== $keyInHtaccess) {
            output("⚠️ PROBLEMA ENCONTRADO: A chave no .htaccess tem espaços extras!");
            output("   Solução: Remova os espaços do início e fim da chave no .htaccess");
        }
    } else {
        output("⚠️ Não foi possível encontrar a chave no .htaccess");
    }
} else {
    output("⚠️ Arquivo .htaccess não encontrado em: " . $htaccessPath);
}

outputSection("6. Próximos Passos");

output("Com base nos testes acima:");
output("");
output("1. Se a chave tem espaços extras:");
output("   → Remova os espaços do .htaccess e teste novamente");
output("");
output("2. Se nenhum formato de header funcionou:");
output("   → A chave está realmente inválida");
output("   → Gere uma nova chave no painel do Asaas");
output("   → Atualize o .htaccess com a nova chave");
output("");
output("3. Após atualizar:");
output("   → Execute este script novamente para verificar");
output("   → Execute scripts/test_order_api_production.php para testar a API completa");

if (php_sapi_name() !== 'cli') {
    output("\n<a href='test_asaas_api_key.php'>🔄 Testar Novamente</a> | ", true);
    output("<a href='test_asaas_env.php'>🔍 Ver Variáveis</a>", true);
}

