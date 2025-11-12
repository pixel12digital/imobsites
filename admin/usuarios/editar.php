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
$usuario = null;

// Verificar se foi passado um ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_GET['id'];

// Buscar dados do usuário
$usuario = fetch("SELECT * FROM usuarios WHERE id = ? AND tenant_id = ?", [$user_id, TENANT_ID]);

if (!$usuario) {
    header('Location: index.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = cleanInput($_POST['nome']);
    $email = cleanInput($_POST['email']);
    $nivel = cleanInput($_POST['nivel']);
         $ativo = (int)$_POST['ativo'];
         // Campos telefone e endereco não existem na tabela usuarios
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    
    // Validações
    if (empty($nome) || empty($email)) {
        $error = 'Nome e email são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } else {
        // Verificar se o email já existe (exceto para o usuário atual)
        $existing_user = fetch("SELECT id FROM usuarios WHERE email = ? AND id != ? AND tenant_id = ?", [$email, $user_id, TENANT_ID]);
        if ($existing_user) {
            $error = 'Este email já está cadastrado por outro usuário.';
        } else {
            // Preparar dados para atualização
            $update_data = [
                 'nome' => $nome,
                 'email' => $email,
                 'nivel' => $nivel,
                 'ativo' => $ativo
             ];
            
            // Se uma nova senha foi fornecida, validar e incluir
            if (!empty($nova_senha)) {
                if (strlen($nova_senha) < 6) {
                    $error = 'A nova senha deve ter pelo menos 6 caracteres.';
                } elseif ($nova_senha !== $confirmar_senha) {
                    $error = 'As senhas não coincidem.';
                } else {
                    $update_data['senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);
                }
            }
            
            if (empty($error)) {
                // Atualizar usuário
            if (update('usuarios', $update_data, 'id = ? AND tenant_id = ?', [$user_id, TENANT_ID])) {
                    $success = 'Usuário atualizado com sucesso!';
                    
                    // Atualizar dados da sessão se for o usuário logado
                    if ($user_id === $_SESSION['admin_id']) {
                        $_SESSION['admin_nome'] = $nome;
                        $_SESSION['admin_email'] = $email;
                        $_SESSION['admin_nivel'] = $nivel;
                    }
                    
                    // Recarregar dados do usuário
                    $usuario = fetch("SELECT * FROM usuarios WHERE id = ? AND tenant_id = ?", [$user_id, TENANT_ID]);
                } else {
                    $error = 'Erro ao atualizar usuário. Tente novamente.';
                }
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
    <title>Editar Usuário - Painel Admin</title>
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
                        <i class="fas fa-user-edit me-2"></i>
                        Editar Usuário
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
                                                   value="<?php echo htmlspecialchars($usuario['nome']); ?>" 
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">
                                                <i class="fas fa-envelope me-1"></i>
                                                Email *
                                            </label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($usuario['email']); ?>" 
                                                   required>
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
                                                <option value="usuario" <?php echo ($usuario['nivel'] === 'usuario') ? 'selected' : ''; ?>>
                                                    <i class="fas fa-user me-1"></i>
                                                    Usuário
                                                </option>
                                                <option value="admin" <?php echo ($usuario['nivel'] === 'admin') ? 'selected' : ''; ?>>
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
                                                 <option value="1" <?php echo ($usuario['ativo'] == 1) ? 'selected' : ''; ?>>
                                                     <i class="fas fa-check me-1"></i>
                                                     Ativo
                                                 </option>
                                                 <option value="0" <?php echo ($usuario['ativo'] == 0) ? 'selected' : ''; ?>>
                                                     <i class="fas fa-times me-1"></i>
                                                     Inativo
                                                 </option>
                                             </select>
                                        </div>
                                    </div>

                                                                         <!-- Campos telefone e endereco não existem na tabela usuarios -->

                                    <hr class="my-4">

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="nova_senha" class="form-label">
                                                <i class="fas fa-lock me-1"></i>
                                                Nova Senha
                                            </label>
                                            <input type="password" class="form-control" id="nova_senha" name="nova_senha" 
                                                   minlength="6">
                                            <div class="form-text">Deixe em branco para manter a senha atual</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="confirmar_senha" class="form-label">
                                                <i class="fas fa-lock me-1"></i>
                                                Confirmar Nova Senha
                                            </label>
                                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" 
                                                   minlength="6">
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="index.php" class="btn btn-secondary me-md-2">
                                            <i class="fas fa-times me-2"></i>
                                            Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Atualizar Usuário
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
                                    Informações do Usuário
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>ID:</strong> <?php echo $usuario['id']; ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Data de Criação:</strong><br>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($usuario['data_criacao'])); ?>
                                    </small>
                                </div>
                                <div class="mb-3">
                                    <strong>Última Atualização:</strong><br>
                                    <small class="text-muted">
                                        <?php echo isset($usuario['data_atualizacao']) ? date('d/m/Y H:i', strtotime($usuario['data_atualizacao'])) : 'Nunca'; ?>
                                    </small>
                                </div>
                                
                                <?php if ($user_id === $_SESSION['admin_id']): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Atenção:</strong> Você está editando seu próprio perfil.
                                    </div>
                                <?php endif; ?>
                                
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-lightbulb me-2"></i>Dicas:</h6>
                                    <ul class="mb-0">
                                        <li>Campos marcados com * são obrigatórios</li>
                                        <li>Deixe a senha em branco para mantê-la</li>
                                        <li>A nova senha deve ter pelo menos 6 caracteres</li>
                                        <li>Alterar o nível pode afetar o acesso</li>
                                    </ul>
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
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            if (novaSenha || confirmarSenha) {
                if (novaSenha !== confirmarSenha) {
                    e.preventDefault();
                    alert('As senhas não coincidem!');
                    return false;
                }
                
                if (novaSenha.length < 6) {
                    e.preventDefault();
                    alert('A nova senha deve ter pelo menos 6 caracteres!');
                    return false;
                }
            }
        });
        
                 // Máscara para telefone removida - campo não existe na tabela
    </script>
</body>
</html>
