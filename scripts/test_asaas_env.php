<?php
/**
 * Script para testar se as variáveis de ambiente do Asaas estão sendo lidas corretamente
 * após a configuração no .htaccess
 */

// Simular ambiente web (necessário para ler variáveis do .htaccess)
if (php_sapi_name() === 'cli') {
    // Em CLI, as variáveis do .htaccess não estão disponíveis
    echo "⚠️  AVISO: Este script deve ser executado via web (não CLI) para testar variáveis do .htaccess\n";
    echo "Acesse via: https://painel.imobsites.com.br/scripts/test_asaas_env.php\n\n";
    
    // Mas podemos testar se o código de leitura funciona
    echo "Testando leitura de variáveis de ambiente (CLI):\n";
    echo "ASAAS_API_KEY: " . (getenv('ASAAS_API_KEY') ?: 'NÃO ENCONTRADA') . "\n";
    echo "ASAAS_ENV: " . (getenv('ASAAS_ENV') ?: 'NÃO ENCONTRADA') . "\n";
    echo "ASAAS_API_BASE_URL: " . (getenv('ASAAS_API_BASE_URL') ?: 'NÃO ENCONTRADA') . "\n";
    echo "ASAAS_WEBHOOK_TOKEN: " . (getenv('ASAAS_WEBHOOK_TOKEN') ?: 'NÃO ENCONTRADA') . "\n";
    exit;
}

// Para ambiente web
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../master/includes/AsaasConfig.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste de Variáveis de Ambiente - Asaas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #007bff; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .var-name { font-weight: bold; color: #007bff; }
        .var-value { color: #666; word-break: break-all; }
        .var-missing { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Teste de Variáveis de Ambiente - Asaas</h1>
        
        <div class="section">
            <h2>1. Verificação via getenv()</h2>
            <?php
            $envVars = [
                'ASAAS_API_KEY' => getenv('ASAAS_API_KEY'),
                'ASAAS_ENV' => getenv('ASAAS_ENV'),
                'ASAAS_API_BASE_URL' => getenv('ASAAS_API_BASE_URL'),
                'ASAAS_WEBHOOK_TOKEN' => getenv('ASAAS_WEBHOOK_TOKEN'),
            ];
            
            foreach ($envVars as $var => $value) {
                $status = $value !== false && $value !== null && $value !== '' ? 'success' : 'error';
                $displayValue = $value !== false && $value !== null && $value !== '' 
                    ? (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value)
                    : 'NÃO ENCONTRADA';
                
                echo "<div class='$status'>";
                echo "<span class='var-name'>$var:</span> ";
                echo "<span class='var-value'>$displayValue</span>";
                echo "</div><br>";
            }
            ?>
        </div>
        
        <div class="section">
            <h2>2. Verificação via $_ENV</h2>
            <?php
            foreach ($envVars as $var => $value) {
                $envValue = $_ENV[$var] ?? null;
                $status = $envValue !== null && $envValue !== '' ? 'success' : 'error';
                $displayValue = $envValue !== null && $envValue !== '' 
                    ? (strlen($envValue) > 50 ? substr($envValue, 0, 50) . '...' : $envValue)
                    : 'NÃO ENCONTRADA';
                
                echo "<div class='$status'>";
                echo "<span class='var-name'>$var:</span> ";
                echo "<span class='var-value'>$displayValue</span>";
                echo "</div><br>";
            }
            ?>
        </div>
        
        <div class="section">
            <h2>3. Verificação via $_SERVER</h2>
            <?php
            foreach ($envVars as $var => $value) {
                $serverValue = $_SERVER[$var] ?? null;
                $status = $serverValue !== null && $serverValue !== '' ? 'success' : 'error';
                $displayValue = $serverValue !== null && $serverValue !== '' 
                    ? (strlen($serverValue) > 50 ? substr($serverValue, 0, 50) . '...' : $serverValue)
                    : 'NÃO ENCONTRADA';
                
                echo "<div class='$status'>";
                echo "<span class='var-name'>$var:</span> ";
                echo "<span class='var-value'>$displayValue</span>";
                echo "</div><br>";
            }
            ?>
        </div>
        
        <div class="section">
            <h2>4. Teste via getAsaasConfig()</h2>
            <?php
            try {
                $config = getAsaasConfig();
                echo "<div class='success'>✅ Configuração carregada com sucesso!</div><br>";
                echo "<pre>";
                echo "API Key: " . (strlen($config['api_key']) > 0 ? substr($config['api_key'], 0, 20) . '...' : 'VAZIA') . "\n";
                echo "Ambiente: " . $config['env'] . "\n";
                echo "Base URL: " . $config['base_url'] . "\n";
                echo "Webhook Token: " . ($config['webhook_token'] ? substr($config['webhook_token'], 0, 20) . '...' : 'NÃO CONFIGURADO') . "\n";
                echo "</pre>";
            } catch (Exception $e) {
                echo "<div class='error'>❌ ERRO ao carregar configuração:</div>";
                echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
            }
            ?>
        </div>
        
        <div class="section">
            <h2>5. Informações do Servidor</h2>
            <pre>
HTTP_HOST: <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'N/A'); ?>

SERVER_SOFTWARE: <?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A'); ?>

PHP Version: <?php echo PHP_VERSION; ?>

SAPI: <?php echo php_sapi_name(); ?>

Document Root: <?php echo htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A'); ?>
            </pre>
        </div>
        
        <div class="section">
            <h2>6. Verificação do .htaccess</h2>
            <?php
            $htaccessPath = __DIR__ . '/../.htaccess';
            if (file_exists($htaccessPath)) {
                echo "<div class='success'>✅ Arquivo .htaccess encontrado</div><br>";
                $htaccessContent = file_get_contents($htaccessPath);
                
                // Verificar se contém as configurações do Asaas
                $checks = [
                    'ASAAS_API_KEY' => strpos($htaccessContent, 'ASAAS_API_KEY') !== false,
                    'ASAAS_ENV' => strpos($htaccessContent, 'ASAAS_ENV') !== false,
                    'ASAAS_API_BASE_URL' => strpos($htaccessContent, 'ASAAS_API_BASE_URL') !== false,
                    'SetEnv' => strpos($htaccessContent, 'SetEnv') !== false,
                ];
                
                foreach ($checks as $check => $found) {
                    $status = $found ? 'success' : 'error';
                    $icon = $found ? '✅' : '❌';
                    echo "<div class='$status'>$icon $check: " . ($found ? 'ENCONTRADO' : 'NÃO ENCONTRADO') . "</div><br>";
                }
                
                // Mostrar trecho relevante do .htaccess
                if (preg_match('/#.*Asaas.*?<\/IfModule>/s', $htaccessContent, $matches)) {
                    echo "<h3>Trecho do .htaccess relacionado ao Asaas:</h3>";
                    echo "<pre>" . htmlspecialchars($matches[0]) . "</pre>";
                }
            } else {
                echo "<div class='error'>❌ Arquivo .htaccess NÃO encontrado em: " . htmlspecialchars($htaccessPath) . "</div>";
            }
            ?>
        </div>
        
        <div class="section">
            <h2>⚠️ Observações Importantes</h2>
            <ul>
                <li>As variáveis de ambiente do <code>.htaccess</code> só funcionam quando o PHP é executado via Apache (não funciona em CLI)</li>
                <li>O módulo <code>mod_env</code> do Apache deve estar habilitado</li>
                <li>Após alterar o <code>.htaccess</code>, pode ser necessário reiniciar o Apache ou limpar o cache</li>
                <li>Verifique os logs de erro do Apache se as variáveis não estiverem sendo lidas</li>
            </ul>
        </div>
    </div>
</body>
</html>

