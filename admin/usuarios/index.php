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

// Processar exclusão
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    
    // Não permitir excluir o próprio usuário
    if ($user_id === $_SESSION['admin_id']) {
        $error = 'Você não pode excluir sua própria conta.';
    } else {
        try {
            // Verificar se o usuário existe
            $usuario_existe = fetch("SELECT * FROM usuarios WHERE id = ? AND tenant_id = ?", [$user_id, TENANT_ID]);
            if (!$usuario_existe) {
                $error = 'Usuário não encontrado.';
            } else {
                // Verificar se o usuário pode ser excluído (sem registros dependentes)
                $check_sql = "SELECT 
                    (SELECT COUNT(*) FROM imoveis WHERE usuario_id = ? AND tenant_id = ?) as total_imoveis,
                    (SELECT COUNT(*) FROM clientes WHERE usuario_id = ? AND tenant_id = ?) as total_clientes";
                
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([$user_id, TENANT_ID, $user_id, TENANT_ID]);
                $dependencies = $check_stmt->fetch();
                
                if ($dependencies['total_imoveis'] > 0 || $dependencies['total_clientes'] > 0) {
                    // Se há dependências, apenas desativar o usuário
                    if (update('usuarios', ['ativo' => 0], 'id = ? AND tenant_id = ?', [$user_id, TENANT_ID])) {
                        $success = 'Usuário <strong>' . htmlspecialchars($usuario_existe['nome']) . '</strong> foi <strong>desativado</strong> com sucesso. ' .
                                 'Não foi possível excluí-lo permanentemente devido a ' . 
                                 $dependencies['total_imoveis'] . ' imóveis e ' . $dependencies['total_clientes'] . ' clientes associados.';
                    } else {
                        $error = 'Erro ao desativar usuário. Tente novamente.';
                    }
                } else {
                    // Se não há dependências, excluir o usuário permanentemente
                    $delete_sql = "DELETE FROM usuarios WHERE id = ? AND tenant_id = ?";
                    $delete_stmt = $pdo->prepare($delete_sql);
                    
                    if ($delete_stmt->execute([$user_id, TENANT_ID])) {
                        $rows_affected = $delete_stmt->rowCount();
                        if ($rows_affected > 0) {
                            $success = 'Usuário <strong>' . htmlspecialchars($usuario_existe['nome']) . '</strong> foi <strong>excluído permanentemente</strong> com sucesso.';
                        } else {
                            $error = 'Nenhuma alteração foi feita. Usuário pode não existir.';
                        }
                    } else {
                        $error = 'Erro ao excluir usuário. Tente novamente.';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Erro interno do sistema: ' . $e->getMessage();
            // Log do erro para debug
            error_log("Erro na exclusão de usuário ID {$user_id}: " . $e->getMessage());
        }
    }
}

// Buscar usuários
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_clause = 'WHERE tenant_id = ?';
$search_params = [TENANT_ID];

if ($search) {
    $where_clause .= " AND (nome LIKE ? OR email LIKE ?)";
    $search_params[] = "%{$search}%";
    $search_params[] = "%{$search}%";
}

// Contar total de usuários
$count_sql = "SELECT COUNT(*) as total FROM usuarios " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($search_params);
$total_users = $count_stmt->fetch()['total'];

$total_pages = ceil($total_users / $per_page);

// Buscar usuários com paginação
$sql = "SELECT id, nome, email, nivel, ativo, data_criacao FROM usuarios " . $where_clause . " ORDER BY data_criacao DESC LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($search_params);
$usuarios = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Painel Admin</title>
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
                        <i class="fas fa-users me-2"></i>
                        Gerenciar Usuários
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="adicionar.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>
                            Novo Usuário
                        </a>
                    </div>
                </div>

                <!-- Alertas -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtros e Busca -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Buscar por nome ou email..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="text-muted">
                            Total: <?php echo $total_users; ?> usuário(s)
                        </span>
                    </div>
                </div>

                <!-- Tabela de Usuários -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Nível</th>
                                <th>Status</th>
                                <th>Data Criação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usuarios)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhum usuário encontrado.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?php echo $usuario['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($usuario['nome']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                        <td>
                                            <?php if ($usuario['nivel'] === 'admin'): ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-shield-alt me-1"></i>
                                                    Admin
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-user me-1"></i>
                                                    Usuário
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($usuario['ativo']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>
                                                    Ativo
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-times me-1"></i>
                                                    Inativo
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($usuario['data_criacao'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="editar.php?id=<?php echo $usuario['id']; ?>" class="btn btn-outline-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                                                                 <?php if ($usuario['id'] !== $_SESSION['admin_id']): ?>
                                                     <button type="button" class="btn btn-outline-danger" title="Excluir/Desativar" 
                                                             onclick="confirmDelete(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nome']); ?>')">
                                                         <i class="fas fa-trash"></i>
                                                     </button>
                                                 <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Navegação de páginas">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                        Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                                 <div class="modal-body">
                     <p>Tem certeza que deseja <strong id="actionType">excluir/desativar</strong> o usuário <strong id="userName"></strong>?</p>
                     <div id="dependencyInfo" style="display: none;">
                         <div class="alert alert-warning">
                             <i class="fas fa-exclamation-triangle me-2"></i>
                             <strong>Atenção:</strong> Este usuário possui registros dependentes e será apenas <strong>desativado</strong>.
                         </div>
                     </div>
                     <div id="deleteInfo" style="display: none;">
                         <div class="alert alert-danger">
                             <i class="fas fa-trash me-2"></i>
                             <strong>Exclusão Permanente:</strong> Este usuário será <strong>excluído permanentemente</strong> do sistema.
                         </div>
                     </div>
                     <p class="text-muted">
                         <i class="fas fa-info-circle me-1"></i>
                         O sistema verifica automaticamente se o usuário pode ser excluído ou se deve ser apenas desativado.
                     </p>
                     <p class="text-danger">
                         <i class="fas fa-exclamation-triangle me-1"></i>
                         Esta ação não pode ser desfeita.
                     </p>
                 </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Cancelar
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="user_id" id="userId">
                        <button type="submit" name="delete_user" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>
                            Excluir
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        function confirmDelete(userId, userName) {
            // Verificar dependências antes de mostrar o modal
            checkUserDependencies(userId, userName);
        }
        
        function checkUserDependencies(userId, userName) {
            // Fazer uma requisição AJAX para verificar dependências
            fetch('check-dependencies.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('userId').value = userId;
                    document.getElementById('userName').textContent = userName;
                    
                    // Mostrar informações apropriadas baseadas nas dependências
                    if (data.hasDependencies) {
                        document.getElementById('actionType').textContent = 'desativar';
                        document.getElementById('dependencyInfo').style.display = 'block';
                        document.getElementById('deleteInfo').style.display = 'none';
                        document.querySelector('button[name="delete_user"]').innerHTML = '<i class="fas fa-ban me-2"></i>Desativar';
                        document.querySelector('button[name="delete_user"]').className = 'btn btn-warning';
                    } else {
                        document.getElementById('actionType').textContent = 'excluir permanentemente';
                        document.getElementById('dependencyInfo').style.display = 'none';
                        document.getElementById('deleteInfo').style.display = 'block';
                        document.querySelector('button[name="delete_user"]').innerHTML = '<i class="fas fa-trash me-2"></i>Excluir';
                        document.querySelector('button[name="delete_user"]').className = 'btn btn-danger';
                    }
                    
                    new bootstrap.Modal(document.getElementById('deleteModal')).show();
                })
                .catch(error => {
                    console.error('Erro ao verificar dependências:', error);
                    // Fallback: mostrar modal padrão
                    document.getElementById('userId').value = userId;
                    document.getElementById('userName').textContent = userName;
                    new bootstrap.Modal(document.getElementById('deleteModal')).show();
                });
        }
    </script>
</body>
</html>
