<?php
session_start();

require_once '../config/paths.php';
require_once '../config/database.php';
require_once '../config/master.php';
require_once 'utils.php';

if (isset($_SESSION['master_logged_in']) && $_SESSION['master_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Informe e-mail e senha.';
    } elseif (strcasecmp($email, MASTER_EMAIL) !== 0 || !verifyMasterPassword($password)) {
        $error = 'Credenciais inválidas.';
    } else {
        $_SESSION['master_logged_in'] = true;
        $_SESSION['master_email'] = MASTER_EMAIL;
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo MASTER_PANEL_NAME; ?> - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0a1429 0%, #14274e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <i class="fas fa-layer-group fa-3x text-primary mb-3"></i>
            <h1 class="h4 mb-0"><?php echo MASTER_PANEL_NAME; ?></h1>
            <p class="text-muted small">Acesso restrito aos administradores da plataforma</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        <form method="POST" novalidate>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control form-control-lg" id="email" name="email" placeholder="admin@empresa.com" required autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Senha</label>
                <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Digite sua senha" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-door-open me-2"></i>Entrar
                </button>
            </div>
        </form>
        <p class="text-center text-muted small mt-4 mb-0">&copy; <?php echo date('Y'); ?> JTR Platform. Todos os direitos reservados.</p>
    </div>
</body>
</html>

