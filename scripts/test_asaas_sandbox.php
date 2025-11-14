<?php
/**
 * Script de teste para validar comunicação com a API do Asaas (sandbox).
 *
 * Este script faz uma chamada simples à API do Asaas para verificar se:
 * - A configuração está sendo lida corretamente (variáveis de ambiente ou config/asaas.php)
 * - A autenticação está funcionando (API key válida)
 * - A URL base está correta
 * - A comunicação HTTP está funcionando
 *
 * INSTRUÇÕES DE USO:
 *
 * 1. Via CLI (linha de comando):
 *    php scripts/test_asaas_sandbox.php
 *
 * 2. Via navegador (apenas para testes locais):
 *    http://localhost/imobsites/scripts/test_asaas_sandbox.php
 *
 * IMPORTANTE:
 * - Este script é apenas para testes e validação
 * - Não expõe dados sensíveis (API key nunca é impressa)
 * - Em produção, remova ou proteja este arquivo adequadamente
 */

declare(strict_types=1);

// Detectar se está rodando via CLI ou navegador
$isCli = php_sapi_name() === 'cli';

// Bootstrap: carregar apenas o necessário para o teste
require_once __DIR__ . '/../master/includes/AsaasConfig.php';
require_once __DIR__ . '/../master/includes/AsaasClient.php';

/**
 * Função auxiliar para exibir resultados de forma legível
 */
function displayResult(bool $isCli, bool $status, int $httpCode, ?string $message, ?array $responseData = null): void
{
    if ($isCli) {
        // Saída para CLI (texto simples)
        echo "\n";
        echo "========================================\n";
        echo "TESTE DE CONEXÃO COM ASAAS SANDBOX\n";
        echo "========================================\n\n";
        
        echo "Status: " . ($status ? "✓ OK" : "✗ FALHA") . "\n";
        echo "HTTP Code: " . $httpCode . "\n";
        
        if ($message) {
            echo "Mensagem: " . $message . "\n";
        }
        
        if ($responseData !== null) {
            $jsonPreview = json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $preview = mb_substr($jsonPreview, 0, 300);
            echo "\nResposta (primeiros 300 caracteres):\n";
            echo $preview;
            if (mb_strlen($jsonPreview) > 300) {
                echo "\n... (resposta truncada)";
            }
        }
        
        echo "\n\n";
    } else {
        // Saída para navegador (HTML)
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Teste de Conexão - Asaas Sandbox</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    max-width: 800px;
                    margin: 40px auto;
                    padding: 20px;
                    background: #f5f5f5;
                }
                .container {
                    background: white;
                    border-radius: 8px;
                    padding: 30px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #333;
                    margin-top: 0;
                    border-bottom: 2px solid #eee;
                    padding-bottom: 10px;
                }
                .status {
                    display: inline-block;
                    padding: 8px 16px;
                    border-radius: 4px;
                    font-weight: bold;
                    margin: 10px 0;
                }
                .status.ok {
                    background: #d4edda;
                    color: #155724;
                }
                .status.fail {
                    background: #f8d7da;
                    color: #721c24;
                }
                .info {
                    background: #f8f9fa;
                    border-left: 4px solid #007bff;
                    padding: 15px;
                    margin: 15px 0;
                }
                .info-label {
                    font-weight: bold;
                    color: #495057;
                    margin-bottom: 5px;
                }
                .info-value {
                    color: #212529;
                    font-family: 'Courier New', monospace;
                    word-break: break-all;
                }
                pre {
                    background: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                    padding: 15px;
                    overflow-x: auto;
                    max-height: 400px;
                    overflow-y: auto;
                }
                .warning {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 15px;
                    margin: 15px 0;
                    color: #856404;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Teste de Conexão - Asaas Sandbox</h1>
                
                <div class="status <?php echo $status ? 'ok' : 'fail'; ?>">
                    <?php echo $status ? '✓ CONEXÃO OK' : '✗ FALHA NA CONEXÃO'; ?>
                </div>
                
                <div class="info">
                    <div class="info-label">Código HTTP:</div>
                    <div class="info-value"><?php echo htmlspecialchars((string)$httpCode, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                
                <?php if ($message): ?>
                <div class="info">
                    <div class="info-label">Mensagem:</div>
                    <div class="info-value"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($responseData !== null): ?>
                <div class="info">
                    <div class="info-label">Resposta da API (primeiros 300 caracteres):</div>
                    <pre><?php 
                        $jsonPreview = json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $preview = mb_substr($jsonPreview, 0, 300);
                        echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');
                        if (mb_strlen($jsonPreview) > 300) {
                            echo "\n... (resposta truncada)";
                        }
                    ?></pre>
                </div>
                <?php endif; ?>
                
                <div class="warning">
                    <strong>⚠️ Aviso:</strong> Este script é apenas para testes. Em produção, remova ou proteja este arquivo adequadamente.
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

// Iniciar o teste
try {
    // 1. Verificar se a configuração está disponível
    $config = getAsaasConfig();
    
    // Log da fonte da configuração (sem expor a API key)
    $configSource = getenv('ASAAS_API_KEY') !== false ? 'variáveis de ambiente' : 'arquivo config/asaas.php';
    
    if (!$isCli) {
        // Em modo web, mostrar informações da configuração (sem API key)
        echo "<!-- Configuração carregada de: " . htmlspecialchars($configSource, ENT_QUOTES, 'UTF-8') . " -->\n";
        echo "<!-- Ambiente: " . htmlspecialchars($config['env'], ENT_QUOTES, 'UTF-8') . " -->\n";
        echo "<!-- URL Base: " . htmlspecialchars($config['base_url'], ENT_QUOTES, 'UTF-8') . " -->\n";
    }
    
    // 2. Fazer a chamada de teste: GET /customers?limit=1
    // Esta é uma chamada simples que apenas valida a autenticação
    $response = asaasRequest('GET', '/customers', ['limit' => 1]);
    
    // 3. Se chegou aqui, a chamada foi bem-sucedida
    $httpCode = 200; // asaasRequest lança exceção se não for 2xx
    $status = true;
    $message = 'Autenticação bem-sucedida! A API key está válida e a comunicação está funcionando.';
    
    // Limitar o tamanho da resposta para não lotar a tela
    $previewData = $response;
    if (isset($previewData['data']) && is_array($previewData['data']) && count($previewData['data']) > 0) {
        // Se houver dados, mostrar apenas o primeiro item
        $previewData['data'] = [array_slice($previewData['data'], 0, 1)[0]];
        $previewData['_preview_note'] = 'Apenas o primeiro item da lista está sendo exibido para preview.';
    }
    
    displayResult($isCli, $status, $httpCode, $message, $previewData);
    
} catch (RuntimeException $e) {
    // Erro de configuração ou comunicação
    $status = false;
    $httpCode = 0;
    $message = $e->getMessage();
    
    // Tentar extrair código HTTP se disponível (pode estar na mensagem)
    if (preg_match('/HTTP (\d+)/', $message, $matches)) {
        $httpCode = (int)$matches[1];
    }
    
    // Mensagens amigáveis para erros comuns
    if (strpos($message, 'ASAAS_API_KEY') !== false) {
        $message = 'API key não configurada. Verifique as variáveis de ambiente ou o arquivo config/asaas.php';
    } elseif (strpos($message, '401') !== false || strpos($message, 'Unauthorized') !== false) {
        $message = 'API key inválida ou expirada. Verifique se a chave está correta no config/asaas.php ou nas variáveis de ambiente.';
    } elseif (strpos($message, '404') !== false || strpos($message, 'Not Found') !== false) {
        $message = 'URL base incorreta. Verifique se ASAAS_API_BASE_URL está configurada corretamente.';
    } elseif (strpos($message, 'cURL') !== false || strpos($message, 'Falha de comunicação') !== false) {
        $message = 'Erro de conexão com a API do Asaas: ' . $message;
    }
    
    displayResult($isCli, $status, $httpCode ?: 500, $message, null);
    
} catch (Throwable $e) {
    // Erro inesperado
    $status = false;
    $httpCode = 500;
    $message = 'Erro inesperado: ' . $e->getMessage();
    
    if (!$isCli) {
        error_log('[test_asaas_sandbox] Erro: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    }
    
    displayResult($isCli, $status, $httpCode, $message, null);
}

