<?php
session_start();

require_once '../config/paths.php';
require_once '../config/database.php';
require_once '../config/master.php';
require_once '../config/email.php';
require_once 'utils.php';
require_once 'includes/MailService.php';

$error = '';
$success = '';
$testError = '';
$testSuccess = '';

if (!isset($_SESSION['master_logged_in']) || $_SESSION['master_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Carregar configurações atuais
$emailConfig = null;
try {
    $stmt = $pdo->query("SELECT * FROM email_settings LIMIT 1");
    $emailConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$emailConfig) {
        // Criar registro padrão
        $fileConfig = getEmailConfig();
        $defaultConfig = [
            'transport' => $fileConfig['smtp_enabled'] ? 'smtp' : 'mail',
            'host' => $fileConfig['smtp_host'] ?? '',
            'port' => $fileConfig['smtp_port'] ?? 587,
            'encryption' => $fileConfig['smtp_secure'] ?? 'tls',
            'username' => $fileConfig['smtp_user'] ?? '',
            'password' => $fileConfig['smtp_pass'] ?? '',
            'from_name' => $fileConfig['from_name'] ?? '',
            'from_email' => $fileConfig['from_email'] ?? '',
            'reply_to_email' => $fileConfig['reply_to'] ?? $fileConfig['from_email'] ?? '',
            'bcc_email' => '',
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO email_settings (
                transport, host, port, encryption, username, password,
                from_name, from_email, reply_to_email, bcc_email
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $defaultConfig['transport'],
            $defaultConfig['host'],
            $defaultConfig['port'],
            $defaultConfig['encryption'],
            $defaultConfig['username'],
            $defaultConfig['password'],
            $defaultConfig['from_name'],
            $defaultConfig['from_email'],
            $defaultConfig['reply_to_email'],
            $defaultConfig['bcc_email'],
        ]);
        
        // Recarregar
        $stmt = $pdo->query("SELECT * FROM email_settings LIMIT 1");
        $emailConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'Erro ao carregar configurações. Verifique se a tabela email_settings existe.';
    error_log('[mail.settings.error] ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_settings'])) {
            // Validações
            $transport = $_POST['transport'] ?? 'mail';
            $host = trim($_POST['host'] ?? '');
            $port = !empty($_POST['port']) ? (int)$_POST['port'] : null;
            $encryption = $_POST['encryption'] ?? 'none';
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $fromName = trim($_POST['from_name'] ?? '');
            $fromEmail = trim($_POST['from_email'] ?? '');
            $replyToEmail = trim($_POST['reply_to_email'] ?? '');
            $bccEmail = trim($_POST['bcc_email'] ?? '');

            // Validações específicas
            if ($transport === 'smtp') {
                if (empty($host)) {
                    $error = 'Host é obrigatório quando Transport é SMTP.';
                }
                if (empty($port) || $port < 1 || $port > 65535) {
                    $error = 'Porta inválida (deve estar entre 1 e 65535).';
                }
            }

            if (!empty($fromEmail) && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'E-mail remetente inválido.';
            }

            if (!empty($replyToEmail) && !filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'E-mail de resposta inválido.';
            }

            if (!empty($bccEmail) && !filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'E-mail BCC inválido.';
            }

            if (empty($error)) {
                // Se password vier vazio, manter o valor atual
                $updateData = [
                    'transport' => $transport,
                    'host' => $host,
                    'port' => $port,
                    'encryption' => $encryption,
                    'username' => $username,
                    'from_name' => $fromName,
                    'from_email' => $fromEmail,
                    'reply_to_email' => $replyToEmail,
                    'bcc_email' => $bccEmail,
                ];

                // Se password foi informado (não está vazio), atualizar
                if (!empty($password)) {
                    $updateData['password'] = $password;
                }

                // Atualizar registro (sempre o primeiro e único)
                $setParts = [];
                $params = [];
                foreach ($updateData as $key => $value) {
                    $setParts[] = "{$key} = ?";
                    $params[] = $value;
                }
                $params[] = $emailConfig['id'];

                $sql = "UPDATE email_settings SET " . implode(', ', $setParts) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Limpar cache estático do MailService
                if (class_exists('MailService')) {
                    try {
                        $reflection = new ReflectionClass('MailService');
                        if ($reflection->hasProperty('cachedConfig')) {
                            $property = $reflection->getProperty('cachedConfig');
                            $property->setAccessible(true);
                            $property->setValue(null, null);
                        }
                    } catch (Throwable $reflectionError) {
                        // Ignorar erros de reflection
                        error_log('[mail.settings.warning] Não foi possível limpar cache: ' . substr($reflectionError->getMessage(), 0, 50));
                    }
                }

                $success = 'Configurações salvas com sucesso.';
                
                // Recarregar configurações
                $stmt = $pdo->query("SELECT * FROM email_settings LIMIT 1");
                $emailConfig = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        if (isset($_POST['test_email'])) {
            $testEmail = trim($_POST['test_email_to'] ?? '');
            
            if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $testError = 'E-mail de destino inválido.';
            } else {
                try {
                    // Limpar cache estático do MailService para usar configurações mais recentes
                    if (class_exists('MailService')) {
                        try {
                            $reflection = new ReflectionClass('MailService');
                            if ($reflection->hasProperty('cachedConfig')) {
                                $property = $reflection->getProperty('cachedConfig');
                                $property->setAccessible(true);
                                $property->setValue(null, null);
                            }
                        } catch (Throwable $reflectionError) {
                            // Ignorar erros de reflection
                            error_log('[mail.test.warning] Não foi possível limpar cache: ' . substr($reflectionError->getMessage(), 0, 50));
                        }
                    }
                    
                    $mailService = new MailService($pdo);
                    
                    // Carregar configurações atuais do banco para mostrar no e-mail
                    $stmt = $pdo->query("SELECT * FROM email_settings LIMIT 1");
                    $currentConfig = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$currentConfig) {
                        // Usar fallback
                        $fileConfig = getEmailConfig();
                        $currentConfig = [
                            'transport' => $fileConfig['smtp_enabled'] ? 'smtp' : 'mail',
                            'host' => $fileConfig['smtp_host'] ?? 'N/A',
                            'port' => $fileConfig['smtp_port'] ?? 'N/A',
                            'encryption' => $fileConfig['smtp_secure'] ?? 'N/A',
                        ];
                    }
                    
                    $configInfo = [
                        'host' => $currentConfig['host'] ?? 'N/A',
                        'port' => $currentConfig['port'] ?? 'N/A',
                        'transport' => $currentConfig['transport'] ?? 'N/A',
                        'encryption' => $currentConfig['encryption'] ?? 'N/A',
                    ];
                    
                    $testBody = sprintf(
                        '<p>Este é um e-mail de teste do sistema ImobSites.</p>
                        <p><strong>Data e hora:</strong> %s</p>
                        <p><strong>Configurações utilizadas:</strong></p>
                        <ul>
                            <li><strong>Transport:</strong> %s</li>
                            <li><strong>Host:</strong> %s</li>
                            <li><strong>Porta:</strong> %s</li>
                            <li><strong>Criptografia:</strong> %s</li>
                        </ul>
                        <p>Se você recebeu este e-mail, as configurações de SMTP estão funcionando corretamente.</p>',
                        date('d/m/Y H:i:s'),
                        htmlspecialchars($configInfo['transport']),
                        htmlspecialchars($configInfo['host']),
                        htmlspecialchars((string)$configInfo['port']),
                        htmlspecialchars($configInfo['encryption'])
                    );
                    
                    $htmlBody = $mailService->buildEmailTemplate('Teste de e-mail – ImobSites', $testBody);
                    
                    $result = $mailService->send(
                        $testEmail,
                        'Teste',
                        'Teste de e-mail – ImobSites',
                        $htmlBody
                    );
                    
                    if ($result) {
                        error_log('[mail.test.sent] E-mail de teste enviado para ' . substr($testEmail, 0, 50));
                        $testSuccess = sprintf('E-mail de teste enviado para %s.', htmlspecialchars($testEmail));
                    } else {
                        $testError = 'Não foi possível enviar o e-mail de teste. Verifique as configurações.';
                        error_log('[mail.test.error] Falha ao enviar e-mail de teste para ' . substr($testEmail, 0, 50));
                    }
                } catch (Throwable $e) {
                    $testError = 'Não foi possível enviar o e-mail de teste. Verifique as configurações.';
                    error_log('[mail.test.error] Exceção: ' . substr($e->getMessage(), 0, 200));
                }
            }
        }
    } catch (Throwable $e) {
        $error = 'Erro ao processar: ' . $e->getMessage();
        error_log('[mail.settings.error] ' . $e->getMessage());
    }
}

// Se ainda não tem config, carregar do arquivo como fallback
if (!$emailConfig) {
    $fileConfig = getEmailConfig();
    $emailConfig = [
        'transport' => $fileConfig['smtp_enabled'] ? 'smtp' : 'mail',
        'host' => $fileConfig['smtp_host'] ?? '',
        'port' => $fileConfig['smtp_port'] ?? 587,
        'encryption' => $fileConfig['smtp_secure'] ?? 'tls',
        'username' => $fileConfig['smtp_user'] ?? '',
        'password' => '',
        'from_name' => $fileConfig['from_name'] ?? '',
        'from_email' => $fileConfig['from_email'] ?? '',
        'reply_to_email' => $fileConfig['reply_to'] ?? $fileConfig['from_email'] ?? '',
        'bcc_email' => '',
    ];
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="page-title mb-1"><i class="fas fa-envelope me-2 text-primary"></i>Configurações de e-mail (SMTP)</h1>
        <p class="page-subtitle mb-0">Configure as opções de envio de e-mail do sistema.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="fas fa-circle-exclamation me-2"></i>
        <div><?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <div><?php echo htmlspecialchars($success); ?></div>
    </div>
<?php endif; ?>

<div class="card mb-4 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="fas fa-cog me-2 text-primary"></i>Configurações SMTP
        </h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="save_settings" value="1">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Transporte <span class="text-danger">*</span></label>
                    <select class="form-select" name="transport" id="transport" required>
                        <option value="mail" <?php echo ($emailConfig['transport'] ?? '') === 'mail' ? 'selected' : ''; ?>>mail() nativo</option>
                        <option value="smtp" <?php echo ($emailConfig['transport'] ?? '') === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                    </select>
                    <div class="form-text text-muted">Escolha o método de envio de e-mail.</div>
                </div>
                <div class="col-md-6" id="host-group" style="<?php echo ($emailConfig['transport'] ?? '') === 'smtp' ? '' : 'display:none;'; ?>">
                    <label class="form-label">Host SMTP <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="host" maxlength="191" 
                           value="<?php echo htmlspecialchars($emailConfig['host'] ?? ''); ?>"
                           <?php echo ($emailConfig['transport'] ?? '') === 'smtp' ? 'required' : ''; ?>>
                    <div class="form-text text-muted">Exemplo: smtp.gmail.com</div>
                </div>
                <div class="col-md-3" id="port-group" style="<?php echo ($emailConfig['transport'] ?? '') === 'smtp' ? '' : 'display:none;'; ?>">
                    <label class="form-label">Porta <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="port" min="1" max="65535"
                           value="<?php echo htmlspecialchars((string)($emailConfig['port'] ?? '')); ?>"
                           <?php echo ($emailConfig['transport'] ?? '') === 'smtp' ? 'required' : ''; ?>>
                    <div class="form-text text-muted">587 (TLS) ou 465 (SSL)</div>
                </div>
                <div class="col-md-3" id="encryption-group" style="<?php echo ($emailConfig['transport'] ?? '') === 'smtp' ? '' : 'display:none;'; ?>">
                    <label class="form-label">Criptografia</label>
                    <select class="form-select" name="encryption">
                        <option value="none" <?php echo ($emailConfig['encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>Nenhuma</option>
                        <option value="tls" <?php echo ($emailConfig['encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?php echo ($emailConfig['encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    </select>
                </div>
                <div class="col-md-6" id="username-group" style="<?php echo ($emailConfig['transport'] ?? '') === 'smtp' ? '' : 'display:none;'; ?>">
                    <label class="form-label">Usuário</label>
                    <input type="text" class="form-control" name="username" maxlength="191"
                           value="<?php echo htmlspecialchars($emailConfig['username'] ?? ''); ?>">
                    <div class="form-text text-muted">Nome de usuário para autenticação SMTP.</div>
                </div>
                <div class="col-md-6" id="password-group" style="<?php echo ($emailConfig['transport'] ?? '') === 'smtp' ? '' : 'display:none;'; ?>">
                    <label class="form-label">Senha</label>
                    <input type="password" class="form-control" name="password" maxlength="255"
                           placeholder="<?php echo !empty($emailConfig['password']) ? '********' : ''; ?>">
                    <div class="form-text text-muted">Deixe em branco para manter a senha atual.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nome do remetente</label>
                    <input type="text" class="form-control" name="from_name" maxlength="191"
                           value="<?php echo htmlspecialchars($emailConfig['from_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail do remetente</label>
                    <input type="email" class="form-control" name="from_email" maxlength="191"
                           value="<?php echo htmlspecialchars($emailConfig['from_email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail de resposta (Reply-To)</label>
                    <input type="email" class="form-control" name="reply_to_email" maxlength="191"
                           value="<?php echo htmlspecialchars($emailConfig['reply_to_email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail BCC (cópia oculta)</label>
                    <input type="email" class="form-control" name="bcc_email" maxlength="191"
                           value="<?php echo htmlspecialchars($emailConfig['bcc_email'] ?? ''); ?>">
                    <div class="form-text text-muted">Receberá uma cópia de todos os e-mails enviados.</div>
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Salvar configurações
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="fas fa-paper-plane me-2 text-primary"></i>Enviar e-mail de teste
        </h5>
    </div>
    <div class="card-body">
        <?php if ($testError): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                <i class="fas fa-circle-exclamation me-2"></i>
                <div><?php echo htmlspecialchars($testError); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($testSuccess): ?>
            <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div><?php echo htmlspecialchars($testSuccess); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="test_email" value="1">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Enviar para (e-mail) <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="test_email_to" maxlength="191" required
                           placeholder="exemplo@email.com"
                           value="<?php echo isset($_POST['test_email_to']) ? htmlspecialchars($_POST['test_email_to']) : ''; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane me-1"></i>Enviar e-mail de teste
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const transportSelect = document.getElementById('transport');
    const hostGroup = document.getElementById('host-group');
    const portGroup = document.getElementById('port-group');
    const encryptionGroup = document.getElementById('encryption-group');
    const usernameGroup = document.getElementById('username-group');
    const passwordGroup = document.getElementById('password-group');
    
    function toggleSmtpFields() {
        const isSmtp = transportSelect.value === 'smtp';
        
        if (isSmtp) {
            hostGroup.style.display = '';
            portGroup.style.display = '';
            encryptionGroup.style.display = '';
            usernameGroup.style.display = '';
            passwordGroup.style.display = '';
            const hostInput = hostGroup.querySelector('input');
            const portInput = portGroup.querySelector('input');
            if (hostInput) hostInput.required = true;
            if (portInput) portInput.required = true;
        } else {
            hostGroup.style.display = 'none';
            portGroup.style.display = 'none';
            encryptionGroup.style.display = 'none';
            usernameGroup.style.display = 'none';
            passwordGroup.style.display = 'none';
            const hostInput = hostGroup.querySelector('input');
            const portInput = portGroup.querySelector('input');
            if (hostInput) hostInput.required = false;
            if (portInput) portInput.required = false;
        }
    }
    
    transportSelect.addEventListener('change', toggleSmtpFields);
    toggleSmtpFields(); // Inicializar estado
});
</script>

<?php include 'includes/footer.php'; ?>

