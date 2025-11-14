<?php
/**
 * Script para resolver conflito do .htaccess com Git
 * Execute este arquivo via navegador UMA VEZ para fazer backup e preparar para git pull
 * 
 * IMPORTANTE: Delete este arquivo após usar por segurança!
 */

// Verificar se está sendo executado via navegador (não CLI)
if (php_sapi_name() === 'cli') {
    die("Este script deve ser executado via navegador.\n");
}

// Verificar se está em produção (ajuste conforme necessário)
$isProduction = (strpos($_SERVER['HTTP_HOST'], 'imobsites.com.br') !== false) 
    || (strpos($_SERVER['HTTP_HOST'], 'painel.imobsites.com.br') !== false);

// Permitir execução em qualquer ambiente (mais flexível)
// if (!$isProduction) {
//     die("Este script deve ser executado apenas em produção.\n");
// }

$rootDir = dirname(__DIR__);
$htaccessPath = $rootDir . '/.htaccess';
$backupPath = $rootDir . '/.htaccess.backup.' . date('Y-m-d_His');

$messages = [];
$errors = [];

// Verificar se .htaccess existe
if (!file_exists($htaccessPath)) {
    $errors[] = "Arquivo .htaccess não encontrado em: $htaccessPath";
} else {
    // Fazer backup
    if (copy($htaccessPath, $backupPath)) {
        $messages[] = "✅ Backup criado: " . basename($backupPath);
        
        // Renomear .htaccess temporariamente
        $tempPath = $rootDir . '/.htaccess.temp';
        if (rename($htaccessPath, $tempPath)) {
            $messages[] = "✅ .htaccess renomeado temporariamente para .htaccess.temp";
            $messages[] = "✅ Agora você pode fazer o 'Update from Remote' no cPanel Git Version Control";
            $messages[] = "⚠️ Após o pull, renomeie .htaccess.temp de volta para .htaccess";
        } else {
            $errors[] = "❌ Erro ao renomear .htaccess";
        }
    } else {
        $errors[] = "❌ Erro ao criar backup";
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix .htaccess Git - Produção</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007cba;
            padding-bottom: 10px;
        }
        .message {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .info {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #007cba;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix .htaccess Git - Produção</h1>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (empty($errors) && !empty($messages)): ?>
            <div class="info">
                <h3>📋 Próximos Passos:</h3>
                <ol>
                    <li>Vá para o <strong>Git Version Control</strong> no cPanel</li>
                    <li>Clique na aba <strong>"Pull or Deploy"</strong></li>
                    <li>Clique em <strong>"Update from Remote"</strong></li>
                    <li>Após o pull funcionar, volte ao <strong>File Manager</strong></li>
                    <li>Renomeie <code>.htaccess.temp</code> de volta para <code>.htaccess</code></li>
                </ol>
            </div>
            
            <div class="warning">
                <strong>⚠️ IMPORTANTE:</strong><br>
                Após concluir, <strong>delete este arquivo</strong> (<code>scripts/fix_htaccess_git.php</code>) por segurança!
            </div>
        <?php endif; ?>
        
        <?php if (file_exists($rootDir . '/.htaccess.temp')): ?>
            <div class="step">
                <h3>🔄 Restaurar .htaccess</h3>
                <p>Se você já fez o pull e quer restaurar o .htaccess:</p>
                <form method="POST" action="">
                    <input type="hidden" name="restore" value="1">
                    <button type="submit" style="padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Restaurar .htaccess
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <?php
        // Restaurar .htaccess se solicitado
        if (isset($_POST['restore']) && file_exists($rootDir . '/.htaccess.temp')) {
            if (rename($rootDir . '/.htaccess.temp', $rootDir . '/.htaccess')) {
                echo '<div class="message">✅ .htaccess restaurado com sucesso!</div>';
            } else {
                echo '<div class="error">❌ Erro ao restaurar .htaccess</div>';
            }
        }
        ?>
    </div>
</body>
</html>

