<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configuraÃ§Ãµes ANTES de iniciar a sessÃ£o
require_once '../../config/paths.php';
require_once '../../config/database.php';
require_once '../../config/tenant.php';
require_once '../../config/config.php';

// Agora iniciar a sessÃ£o
session_start();

// Verificar se o usuÃ¡rio estÃ¡ logado
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in'] || !isset($_SESSION['tenant_id']) || (int)$_SESSION['tenant_id'] !== TENANT_ID) {
    header('Location: ../login.php');
    exit;
}

// Verificar se o usuÃ¡rio tem nÃ­vel de administrador
if ($_SESSION['admin_nivel'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Processar exclusÃ£o
if (isset($_POST['delete_contact']) && isset($_POST['contact_id'])) {
    $contact_id = (int)$_POST['contact_id'];
    
    $delete_stmt = query("DELETE FROM contatos WHERE id = ? AND tenant_id = ?", [$contact_id, TENANT_ID]);
    if ($delete_stmt && $delete_stmt->rowCount() > 0) {
        $success = 'Contato excluÃ­do com sucesso.';
    } else {
        $error = 'Erro ao excluir contato.';
    }
}

// Processar marcaÃ§Ã£o como lido/nÃ£o lido
if (isset($_POST['toggle_status']) && isset($_POST['contact_id'])) {
    $contact_id = (int)$_POST['contact_id'];
    $current_status = $_POST['current_status'];
    $new_status = ($current_status === 'lido') ? 'nao_lido' : 'lido';
    
    if (update('contatos', ['status' => $new_status], 'id = ? AND tenant_id = ?', [$contact_id, TENANT_ID])) {
        $success = 'Status do contato atualizado com sucesso.';
    } else {
        $error = 'Erro ao atualizar status do contato.';
    }
}

// Buscar contatos
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
$tipo_filter = isset($_GET['tipo']) ? cleanInput($_GET['tipo']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_clause = 'WHERE tenant_id = ?';
$params = [TENANT_ID];

if ($search || $status_filter || $tipo_filter) {
    $conditions = [];
    
    if ($search) {
        $conditions[] = "(nome LIKE ? OR email LIKE ? OR telefone LIKE ? OR mensagem LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    if ($status_filter) {
        $conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    if ($tipo_filter) {
        $conditions[] = "tipo_operacao = ?";
        $params[] = $tipo_filter;
    }
    
    $where_clause .= " AND " . implode(' AND ', $conditions);
}

// Contar total de contatos
$count_sql = "SELECT COUNT(*) as total FROM contatos " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_contacts = $count_stmt->fetch()['total'];

$total_pages = ceil($total_contacts / $per_page);

// Buscar contatos com paginaÃ§Ã£o
$sql = "SELECT id, nome, email, telefone, assunto, tipo_operacao, mensagem, status, data_envio FROM contatos " . $where_clause . " ORDER BY data_envio DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contatos = $stmt->fetchAll();

// EstatÃ­sticas
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'nao_lido' THEN 1 ELSE 0 END) as nao_lidos,
    SUM(CASE WHEN status = 'lido' THEN 1 ELSE 0 END) as lidos
FROM contatos
WHERE tenant_id = ?";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute([TENANT_ID]);
$stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Contatos - Painel Admin</title>
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
                                ImÃ³veis
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../usuarios/">
                                <i class="fas fa-users me-2"></i>
                                UsuÃ¡rios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="../contatos/">
                                <i class="fas fa-envelope me-2"></i>
                                Contatos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../configuracoes/">
                                <i class="fas fa-cog me-2"></i>
                                ConfiguraÃ§Ãµes
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
                        <i class="fas fa-envelope me-2"></i>
                        Gerenciar Contatos
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="visualizar.php" class="btn btn-primary">
                            <i class="fas fa-eye me-2"></i>
                            Visualizar Todos
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

                <!-- EstatÃ­sticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stats['total']; ?></h4>
                                        <small>Total de Contatos</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-envelope fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stats['nao_lidos']; ?></h4>
                                        <small>NÃ£o Lidos</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-envelope-open fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stats['lidos']; ?></h4>
                                        <small>Lidos</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $total_contacts; ?></h4>
                                        <small>Exibindo</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-list fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EstatÃ­sticas por Tipo -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $vendas_sql = "SELECT COUNT(*) as total FROM contatos WHERE tipo_operacao = 'venda'";
                                            $vendas_stmt = $pdo->prepare($vendas_sql);
                                            $vendas_stmt->execute();
                                            echo $vendas_stmt->fetch()['total'];
                                            ?>
                                        </h4>
                                        <small>Contatos de Venda</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-home fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $locacoes_sql = "SELECT COUNT(*) as total FROM contatos WHERE tipo_operacao = 'locacao'";
                                            $locacoes_stmt = $pdo->prepare($locacoes_sql);
                                            $locacoes_stmt->execute();
                                            echo $locacoes_stmt->fetch()['total'];
                                            ?>
                                        </h4>
                                        <small>Contatos de LocaÃ§Ã£o</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-key fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $outros_sql = "SELECT COUNT(*) as total FROM contatos WHERE tipo_operacao = 'outros' OR tipo_operacao IS NULL";
                                            $outros_stmt = $pdo->prepare($outros_sql);
                                            $outros_stmt->execute();
                                            echo $outros_stmt->fetch()['total'];
                                            ?>
                                        </h4>
                                        <small>Outros Contatos</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-question fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros e Busca -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <form method="GET" class="row g-2">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="Buscar por nome, email, telefone ou mensagem..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">Todos os Status</option>
                                    <option value="nao_lido" <?php echo $status_filter === 'nao_lido' ? 'selected' : ''; ?>>NÃ£o Lidos</option>
                                    <option value="lido" <?php echo $status_filter === 'lido' ? 'selected' : ''; ?>>Lidos</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="tipo" class="form-select">
                                    <option value="">Todos os Tipos</option>
                                    <option value="venda" <?php echo (isset($_GET['tipo']) && $_GET['tipo'] === 'venda') ? 'selected' : ''; ?>>Venda</option>
                                    <option value="locacao" <?php echo (isset($_GET['tipo']) && $_GET['tipo'] === 'locacao') ? 'selected' : ''; ?>>LocaÃ§Ã£o</option>
                                    <option value="outros" <?php echo (isset($_GET['tipo']) && $_GET['tipo'] === 'outros') ? 'selected' : ''; ?>>Outros</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-search me-2"></i>
                                    Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="text-muted">
                            Total: <?php echo $total_contacts; ?> contato(s)
                        </span>
                    </div>
                </div>

                <!-- Tabela de Contatos -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Assunto</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Data Envio</th>
                                <th>AÃ§Ãµes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($contatos)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhum contato encontrado.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($contatos as $contato): ?>
                                    <tr class="<?php echo $contato['status'] === 'nao_lido' ? 'table-warning' : ''; ?>">
                                        <td><?php echo $contato['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($contato['nome']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($contato['email']); ?></td>
                                        <td><?php echo htmlspecialchars($contato['telefone'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($contato['assunto']): ?>
                                                <span title="<?php echo htmlspecialchars($contato['assunto']); ?>">
                                                    <?php echo strlen($contato['assunto']) > 30 ? substr(htmlspecialchars($contato['assunto']), 0, 30) . '...' : htmlspecialchars($contato['assunto']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($contato['tipo_operacao']): ?>
                                                <?php if ($contato['tipo_operacao'] === 'venda'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-home me-1"></i>
                                                        Venda
                                                    </span>
                                                <?php elseif ($contato['tipo_operacao'] === 'locacao'): ?>
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-key me-1"></i>
                                                        LocaÃ§Ã£o
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-question me-1"></i>
                                                        Outros
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($contato['status'] === 'nao_lido'): ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-envelope-open me-1"></i>
                                                    NÃ£o Lido
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>
                                                    Lido
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($contato['data_envio'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="visualizar.php?id=<?php echo $contato['id']; ?>" class="btn btn-outline-primary" title="Visualizar">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="contact_id" value="<?php echo $contato['id']; ?>">
                                                    <input type="hidden" name="current_status" value="<?php echo $contato['status']; ?>">
                                                    <button type="submit" name="toggle_status" class="btn btn-outline-<?php echo $contato['status'] === 'nao_lido' ? 'success' : 'warning'; ?>" title="<?php echo $contato['status'] === 'nao_lido' ? 'Marcar como lido' : 'Marcar como nÃ£o lido'; ?>">
                                                        <i class="fas fa-<?php echo $contato['status'] === 'nao_lido' ? 'check' : 'envelope'; ?>"></i>
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-outline-danger" title="Excluir" 
                                                        onclick="confirmDelete(<?php echo $contato['id']; ?>, '<?php echo htmlspecialchars($contato['nome']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PaginaÃ§Ã£o -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="NavegaÃ§Ã£o de pÃ¡ginas">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&tipo=<?php echo urlencode($tipo_filter); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&tipo=<?php echo urlencode($tipo_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&tipo=<?php echo urlencode($tipo_filter); ?>">
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

    <!-- Modal de ConfirmaÃ§Ã£o de ExclusÃ£o -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                        Confirmar ExclusÃ£o
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir o contato de <strong id="contactName"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-info-circle me-1"></i>
                        Esta aÃ§Ã£o nÃ£o pode ser desfeita.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Cancelar
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="contact_id" id="contactId">
                        <button type="submit" name="delete_contact" class="btn btn-danger">
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
        function confirmDelete(contactId, contactName) {
            document.getElementById('contactId').value = contactId;
            document.getElementById('contactName').textContent = contactName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
