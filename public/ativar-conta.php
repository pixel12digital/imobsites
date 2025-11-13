<?php
/**
 * Tela pública de ativação de conta.
 *
 * TODO: ajustar layout conforme identidade visual final.
 */

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../master/utils.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$error = '';
$success = '';
$user = null;
$planInfo = null;

if ($token !== '') {
    $user = fetch('SELECT * FROM usuarios WHERE activation_token = ? AND ativo = 0 LIMIT 1', [$token]);

    if ($user) {
        if (!empty($user['activation_expires_at']) && strtotime($user['activation_expires_at']) < time()) {
            $error = 'Este link de ativação expirou. Solicite um novo link.';
            $user = null;
        } else {
            $planInfo = fetch('SELECT plan_code, billing_cycle, total_amount FROM orders WHERE tenant_id = ? ORDER BY id DESC LIMIT 1', [$user['tenant_id']]);
        }
    } else {
        $error = 'Link inválido ou já utilizado.';
    }
} else {
    $error = 'Token de ativação não informado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $acceptTerms = isset($_POST['accept_terms']);

    if (strlen($password) < 8) {
        $error = 'A senha deve ter no mínimo 8 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'As senhas informadas não coincidem.';
    } elseif (!$acceptTerms) {
        $error = 'É necessário aceitar os termos de uso para prosseguir.';
    } else {
        $validUser = fetch('SELECT * FROM usuarios WHERE activation_token = ? AND ativo = 0 LIMIT 1', [$token]);

        if (!$validUser) {
            $error = 'Link inválido ou já utilizado.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $now = date('Y-m-d H:i:s');

            update('usuarios', [
                'senha' => $hashedPassword,
                'ativo' => 1,
                'activation_token' => null,
                'activation_expires_at' => null,
                'activated_at' => $now,
                'data_atualizacao' => $now,
            ], 'id = ?', [$validUser['id']]);

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $validUser['id'];
            $_SESSION['admin_nome'] = $validUser['nome'];
            $_SESSION['admin_email'] = $validUser['email'];
            $_SESSION['admin_nivel'] = $validUser['nivel'] ?? 'admin';
            $_SESSION['tenant_id'] = $validUser['tenant_id'];

            if (!isset($_SESSION['tenant_override_id'])) {
                $_SESSION['tenant_override_id'] = $validUser['tenant_id'];
            }

            $destination = '/admin/index.php';
            header('Location: ' . $destination);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativar Conta - ImobSites</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f9ff 0%, #eef3fb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .activation-card {
            max-width: 540px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(2, 58, 141, 0.12);
            overflow: hidden;
            background: #fff;
        }
        .activation-header {
            background: #023A8D;
            color: #fff;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
        }
        .activation-header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        .activation-header p {
            margin: 0;
            opacity: 0.9;
        }
        .activation-body {
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="activation-card">
        <div class="activation-header">
            <h1>Ativar Conta</h1>
            <p>Finalize seu cadastro e acesse o painel ImobSites</p>
        </div>
        <div class="activation-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($user && !$error): ?>
                <div class="mb-4">
                    <h5 class="mb-1"><?php echo htmlspecialchars($user['nome'] ?? $user['email']); ?></h5>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                    <?php if ($planInfo): ?>
                        <span class="badge bg-primary-subtle text-primary">
                            Plano: <?php echo htmlspecialchars($planInfo['plan_code']); ?> • <?php echo htmlspecialchars($planInfo['billing_cycle']); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <div class="mb-3">
                        <label for="password" class="form-label">Nova senha</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                        <div class="form-text">Mínimo de 8 caracteres.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar senha</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" minlength="8" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="accept_terms" name="accept_terms" required>
                        <label class="form-check-label" for="accept_terms">
                            Li e aceito os <a href="/termos-de-uso" target="_blank">Termos de Uso</a> e <a href="/politica-de-privacidade" target="_blank">Política de Privacidade</a>.
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        Ativar conta e acessar painel
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <a class="btn btn-outline-primary" href="https://imobsites.com.br">Ir para o site</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
ob_end_flush();

