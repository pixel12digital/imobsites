<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configurações ANTES de iniciar a sessão
require_once '../config/paths.php';
require_once '../config/database.php';

if (!function_exists('cleanInput')) {
    function cleanInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['tenant'])) {
    $tenant_slug = cleanInput($_GET['tenant']);
    $tenant = fetch("SELECT id, name FROM tenants WHERE slug = ?", [$tenant_slug]);
    if ($tenant) {
        $_SESSION['tenant_override_id'] = (int)$tenant['id'];
        $_SESSION['tenant_override_notice'] = 'Impersonando tenant: ' . $tenant['name'];
    } else {
        $_SESSION['tenant_override_notice'] = 'Tenant não encontrado para o slug informado.';
    }
}

if (isset($_GET['clear_tenant'])) {
    unset($_SESSION['tenant_override_id'], $_SESSION['tenant_override_notice']);
}

require_once '../config/tenant.php';
require_once '../config/config.php';

$error = '';
$impersonate_notice = $_SESSION['tenant_override_notice'] ?? '';

// Se já estiver logado, redirecionar para o dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email']);
    $senha = $_POST['senha'];
    
    if (empty($email) || empty($senha)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        try {
            // Buscar usuário pelo email
            $usuario = fetch("SELECT id, nome, email, senha, nivel FROM usuarios WHERE email = ? AND ativo = 1 AND tenant_id = ?", [$email, TENANT_ID]);
            
            if ($usuario && (password_verify($senha, $usuario['senha']) || $senha === $usuario['senha'])) {
                if ($usuario['nivel'] === 'admin') {
                    // Login bem-sucedido
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $usuario['id'];
                    $_SESSION['admin_nome'] = $usuario['nome'];
                    $_SESSION['admin_email'] = $usuario['email'];
                    $_SESSION['admin_nivel'] = $usuario['nivel'];
                    $_SESSION['tenant_id'] = TENANT_ID;
                    
                    // Redirecionar para o dashboard
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Acesso negado. Apenas administradores podem acessar o painel.';
                }
            } else {
                $error = 'Email ou senha incorretos.';
            }
        } catch (Exception $e) {
            $error = 'Erro ao processar login. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel Administrativo JTR Imóveis</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="assets/css/admin.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(29, 76, 52, 0.15);
            overflow: hidden;
            border: none;
        }
        
        .login-left {
            background: linear-gradient(135deg, #1D4C34 0%, #2d5a3f 100%);
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .login-brand {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 3rem 2rem;
            color: white;
        }
        
        .login-brand h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .login-brand p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .login-form {
            padding: 3rem 2rem;
            background: white;
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-title h2 {
            color: #1D4C34;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .form-title p {
            color: #6c757d;
            margin-bottom: 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .input-group {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(29, 76, 52, 0.1);
            transition: all 0.3s ease;
        }
        
        .input-group:focus-within {
            box-shadow: 0 4px 16px rgba(29, 76, 52, 0.2);
            transform: translateY(-2px);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: none;
            color: #1D4C34;
            font-size: 1.1rem;
            padding: 0.75rem 1rem;
        }
        
        .form-control {
            border: none;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            background: white;
        }
        
        .form-control:focus {
            box-shadow: none;
            background: white;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #1D4C34 0%, #2d5a3f 100%);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(29, 76, 52, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(29, 76, 52, 0.4);
        }
        
        .back-link {
            color: #1D4C34;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: #2d5a3f;
            transform: translateX(-5px);
        }
        
        .info-text {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .login-left {
                display: none;
            }
            
            .login-form {
                padding: 2rem 1.5rem;
            }
            
            .login-brand h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10 col-lg-12 col-md-9">
                    <div class="card login-card">
                        <div class="row g-0">
                            <div class="col-lg-6 login-left">
                                <div class="login-brand">
                                    <h1><i class="fas fa-home me-3"></i>JTR Imóveis</h1>
                                    <p>Painel Administrativo</p>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="login-form">
                                    <div class="form-title">
                                        <h2>Bem-vindo!</h2>
                                        <p>Faça login para acessar o painel</p>
                                    </div>
                                    
                <?php if ($impersonate_notice): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-user-secret me-2"></i>
                        <?php echo htmlspecialchars($impersonate_notice); ?>
                        <a href="login.php?clear_tenant=1" class="btn btn-sm btn-outline-light ms-3">Encerrar impersonação</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php unset($_SESSION['tenant_override_notice']); endif; ?>

                <?php if ($error): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="">
                                        <div class="form-group">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-envelope"></i>
                                                </span>
                                                <input type="email" class="form-control" name="email" 
                                                       placeholder="Digite seu email" required 
                                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                                <input type="password" class="form-control" name="senha" 
                                                       placeholder="Digite sua senha" required>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-login btn-primary w-100 mb-4">
                                            <i class="fas fa-sign-in-alt me-2"></i>Entrar
                                        </button>
                                    </form>
                                    
                                    <hr class="my-4">
                                    
                                    <div class="text-center">
                                        <a class="back-link" href="../">
                                            <i class="fas fa-arrow-left me-2"></i>Voltar ao Site
                                        </a>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <small class="info-text">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Use as credenciais de administrador para acessar o painel
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Finalizar output buffering
ob_end_flush();
?>
