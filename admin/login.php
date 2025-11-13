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
    $tenant = fetch("SELECT id, name, slug FROM tenants WHERE slug = ?", [$tenant_slug]);
    if ($tenant) {
        $_SESSION['tenant_override_id'] = (int)$tenant['id'];
        $_SESSION['tenant_override_notice'] = 'Acessando como cliente: ' . $tenant['name'];
        if ($tenant['slug'] === 'tenant-padrao') {
            $_SESSION['tenant_override_logo'] = '../assets/logo-fundo-braco.png';
        } else {
            unset($_SESSION['tenant_override_logo']);
        }

        // Guardar rota de retorno para o painel master, caso o acesso tenha partido dele
        if (empty($_SESSION['tenant_override_return_url'])) {
            $_SESSION['tenant_override_return_url'] = '../master/index.php';
        }
    } else {
        $_SESSION['tenant_override_notice'] = 'Tenant não encontrado para o slug informado.';
    }
}

if (isset($_GET['clear_tenant'])) {
    $returnUrl = $_SESSION['tenant_override_return_url'] ?? '../master/index.php';

    unset(
        $_SESSION['tenant_override_id'],
        $_SESSION['tenant_override_notice'],
        $_SESSION['tenant_override_logo'],
        $_SESSION['tenant_override_return_url']
    );

    // Garantir que o redirecionamento permaneça interno
    if (preg_match('#^https?://#i', $returnUrl)) {
        $returnUrl = '../master/index.php';
    }

    header('Location: ' . $returnUrl);
    exit;
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
    <title>Login - Painel Administrativo imobsites</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="assets/css/admin.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #F5F7FA 0%, #E6ECF5 100%);
            min-height: 100vh;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(28, 59, 90, 0.15);
            overflow: hidden;
            border: none;
        }
        
        .login-left {
            background: #1b2f4b;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 4rem 2.5rem 3rem;
        }
        
        .login-brand {
            position: relative;
            z-index: 2;
            color: white;
            width: 100%;
            max-width: 420px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.85rem;
        }
        
        .login-brand__logo {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-brand__logo img {
            height: 110px;
            width: auto;
        }
        
        .login-brand__text {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0;
        }

        .login-brand h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin: 0.15rem 0 0.2rem;
        }
        
        .login-brand p {
            font-size: 1.15rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
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
            color: #1C3B5A;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .form-title p {
            color: #5A6473;
            margin-bottom: 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .input-group {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(28, 59, 90, 0.12);
            transition: all 0.3s ease;
        }
        
        .input-group:focus-within {
            box-shadow: 0 4px 16px rgba(28, 59, 90, 0.25);
            transform: translateY(-2px);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: none;
            color: #1C3B5A;
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
            background: #F7931E;
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            color: #1E232B;
            box-shadow: 0 4px 12px rgba(28, 59, 90, 0.2);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(28, 59, 90, 0.25);
            background: #ff9f35;
            color: #1E232B;
        }
        
        .back-link {
            color: #1C3B5A;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: #F7931E;
            transform: translateX(-5px);
        }
        
        .info-text {
            color: #5A6473;
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
            
            .login-brand {
                padding: 2.75rem 2rem 2.5rem;
                align-items: center;
                text-align: center;
                gap: 0.9rem;
            }
            
            .login-brand__logo {
                margin-bottom: 0;
            }
            
            .login-brand__logo img {
                height: 80px;
            }
            
            .login-brand h1 {
                font-size: 2.2rem;
                margin: 0.1rem 0 0.2rem;
            }
            
            .login-brand p {
                font-size: 1rem;
                margin: 0;
            }
        }

        @media (min-width: 992px) {
            .login-brand__text {
                margin-top: 2.5rem;
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
                                    <div class="login-brand__logo">
                                        <?php if (!empty($_SESSION['tenant_override_logo'])): ?>
                                            <img src="<?php echo htmlspecialchars($_SESSION['tenant_override_logo']); ?>" alt="Imobsites">
                                        <?php else: ?>
                                            <img src="../assets/logo-imob.png" alt="Imobsites">
                                        <?php endif; ?>
                                    </div>
                                    <div class="login-brand__text">
                                        <h1>Painel Imobsites</h1>
                                        <p>Administre seus imóveis com eficiência</p>
                                    </div>
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
                        <a href="login.php?clear_tenant=1" class="btn btn-sm btn-outline-light ms-3">Voltar para modo administrador</a>
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
