<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configurações ANTES de iniciar a sessão
require_once '../../config/paths.php';
require_once '../../config/database.php';
require_once '../../config/tenant.php';
require_once '../../config/config.php';

// Agora iniciar a sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in'] || !isset($_SESSION['tenant_id']) || (int)$_SESSION['tenant_id'] !== TENANT_ID) {
    header('Location: ../login.php');
    exit;
}

// Verificar se o usuário tem nível de administrador
if ($_SESSION['admin_nivel'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = cleanInput($_POST['nome']);
    $email = cleanInput($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $nivel = cleanInput($_POST['nivel']);
         $ativo = (int)$_POST['ativo'];
         // Campos telefone e endereco não existem na tabela usuarios
    
    // Validações
    if (empty($nome) || empty($email) || empty($senha)) {
        $error = 'Nome, email e senha são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } elseif (strlen($senha) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmar_senha) {
        $error = 'As senhas não coincidem.';
    } else {
        // Verificar se o email já existe
        $existing_user = fetch("SELECT id FROM usuarios WHERE email = ? AND tenant_id = ?", [$email, TENANT_ID]);
        if ($existing_user) {
            $error = 'Este email já está cadastrado.';
        } else {
            // Preparar dados para inserção
            $user_data = [
                 'nome' => $nome,
                 'email' => $email,
                 'senha' => password_hash($senha, PASSWORD_DEFAULT),
                 'nivel' => $nivel,
                 'ativo' => $ativo,
                'data_criacao' => date('Y-m-d H:i:s'),
                'tenant_id' => TENANT_ID
             ];
            
            // Inserir usuário
            if (insert('usuarios', $user_data)) {
                $success = 'Usuário criado com sucesso!';
                // Limpar formulário
                $_POST = array();
            } else {
                $error = 'Erro ao criar usuário. Tente novamente.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Usuário - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../imoveis/">
                                <i class="fas fa-home me-2"></i>
                                Imóveis
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="../usuarios/">
                                <i class="fas fa-users me-2"></i>
                                Usuários
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../contatos/">
                                <i class="fas fa-envelope me-2"></i>
                                Contatos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../configuracoes/">
                                <i class="fas fa-cog me-2"></i>
                                Configurações
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../perfil.php">
                                <i class="fas fa-user me-2"></i>
                                Meu Perfil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Sair
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-plus me-2"></i>
                        Adicionar Novo Usuário
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Voltar
                        </a>
                    </div>
                </div>

                <!-- Alertas -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Formulário -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-edit me-2"></i>
                                    Informações do Usuário
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="userForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="nome" class="form-label">
                                                <i class="fas fa-user me-1"></i>
                                                Nome Completo *
                                            </label>
                                            <input type="text" class="form-control" id="nome" name="nome" 
                                                   value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" 
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">
                                                <i class="fas fa-envelope me-1"></i>
                                                Email *
                                            </label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                                   required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="senha" class="form-label">
                                                <i class="fas fa-lock me-1"></i>
                                                Senha *
                                            </label>
                                            <input type="password" class="form-control" id="senha" name="senha" 
                                                   minlength="6" required>
                                            <div class="form-text">Mínimo 6 caracteres</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="confirmar_senha" class="form-label">
                                                <i class="fas fa-lock me-1"></i>
                                                Confirmar Senha *
                                            </label>
                                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" 
                                                   minlength="6" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="nivel" class="form-label">
                                                <i class="fas fa-shield-alt me-1"></i>
                                                Nível de Acesso *
                                            </label>
                                            <select class="form-select" id="nivel" name="nivel" required>
                                                <option value="">Selecione...</option>
                                                <option value="usuario" <?php echo (isset($_POST['nivel']) && $_POST['nivel'] === 'usuario') ? 'selected' : ''; ?>>
                                                    <i class="fas fa-user me-1"></i>
                                                    Usuário
                                                </option>
                                                <option value="admin" <?php echo (isset($_POST['nivel']) && $_POST['nivel'] === 'admin') ? 'selected' : ''; ?>>
                                                    <i class="fas fa-shield-alt me-1"></i>
                                                    Administrador
                                                </option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                                                                         <label for="ativo" class="form-label">
                                                 <i class="fas fa-toggle-on me-1"></i>
                                                 Status *
                                             </label>
                                             <select class="form-select" id="ativo" name="ativo" required>
                                                 <option value="">Selecione...</option>
                                                 <option value="1" <?php echo (isset($_POST['ativo']) && $_POST['ativo'] == 1) ? 'selected' : ''; ?>>
                                                     <i class="fas fa-check me-1"></i>
                                                     Ativo
                                                 </option>
                                                 <option value="0" <?php echo (isset($_POST['ativo']) && $_POST['ativo'] == 0) ? 'selected' : ''; ?>>
                                                     <i class="fas fa-times me-1"></i>
                                                     Inativo
                                                 </option>
                                             </select>
                                        </div>
                                    </div>

                                                                         <!-- Campos telefone e endereco não existem na tabela usuarios -->

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="index.php" class="btn btn-secondary me-md-2">
                                            <i class="fas fa-times me-2"></i>
                                            Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Criar Usuário
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Informações
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-lightbulb me-2"></i>Dicas:</h6>
                                    <ul class="mb-0">
                                        <li>Campos marcados com * são obrigatórios</li>
                                        <li>A senha deve ter pelo menos 6 caracteres</li>
                                        <li>O email deve ser único no sistema</li>
                                        <li>Usuários administradores têm acesso total</li>
                                    </ul>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Importante:</h6>
                                    <p class="mb-0">Após criar o usuário, ele poderá fazer login imediatamente se o status estiver como "Ativo".</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Validação do formulário
        document.getElementById('userForm').addEventListener('submit', function(e) {
            const senha = document.getElementById('senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            if (senha !== confirmarSenha) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                return false;
            }
            
            if (senha.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres!');
                return false;
            }
        });
        
                 // Máscara para telefone removida - campo não existe na tabela
    </script>
</body>
</html>
