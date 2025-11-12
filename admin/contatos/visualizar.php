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
$contato = null;
$contatos = [];

// Processar marcação como lido
if (isset($_POST['mark_as_read']) && isset($_POST['contact_id'])) {
    $contact_id = (int)$_POST['contact_id'];
    
    if (update('contatos', ['status' => 'lido'], 'id = ? AND tenant_id = ?', [$contact_id, TENANT_ID])) {
        $success = 'Contato marcado como lido.';
    } else {
        $error = 'Erro ao atualizar status do contato.';
    }
}

// Se foi passado um ID específico, mostrar apenas esse contato
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $contact_id = (int)$_GET['id'];
    $contato = fetch("SELECT * FROM contatos WHERE id = ? AND tenant_id = ?", [$contact_id, TENANT_ID]);
    
    if (!$contato) {
        header('Location: index.php');
        exit;
    }
    
    // Marcar como lido automaticamente ao visualizar
    if ($contato['status'] === 'nao_lido') {
        update('contatos', ['status' => 'lido'], 'id = ? AND tenant_id = ?', [$contact_id, TENANT_ID]);
        $contato['status'] = 'lido';
    }
} else {
    // Buscar todos os contatos para listagem
    $search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    $where_clause = 'WHERE tenant_id = ?';
    $params = [TENANT_ID];

    if ($search || $status_filter) {
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
        
        $where_clause .= " AND " . implode(' AND ', $conditions);
    }

    // Contar total de contatos
    $count_sql = "SELECT COUNT(*) as total FROM contatos " . $where_clause;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_contacts = $count_stmt->fetch()['total'];

    $total_pages = ceil($total_contacts / $per_page);

    // Buscar contatos com paginação
    $sql = "SELECT id, nome, email, telefone, assunto, mensagem, status, data_envio FROM contatos " . $where_clause . " ORDER BY data_envio DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contatos = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $contato ? 'Visualizar Contato' : 'Todos os Contatos'; ?> - Painel Admin</title>
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
                            <a class="nav-link text-white" href="../usuarios/">
                                <i class="fas fa-users me-2"></i>
                                Usuários
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
                        <i class="fas fa-envelope me-2"></i>
                        <?php echo $contato ? 'Visualizar Contato' : 'Todos os Contatos'; ?>
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

                <?php if ($contato): ?>
                    <!-- Visualizar Contato Específico -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-envelope me-2"></i>
                                        Mensagem de <?php echo htmlspecialchars($contato['nome']); ?>
                                    </h5>
                                    <div>
                                        <?php if ($contato['status'] === 'nao_lido'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="contact_id" value="<?php echo $contato['id']; ?>">
                                                <button type="submit" name="mark_as_read" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check me-1"></i>
                                                    Marcar como Lido
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>
                                                Lido
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Nome:</strong><br>
                                            <?php echo htmlspecialchars($contato['nome']); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Email:</strong><br>
                                            <a href="mailto:<?php echo htmlspecialchars($contato['email']); ?>">
                                                <?php echo htmlspecialchars($contato['email']); ?>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <?php if ($contato['telefone']): ?>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Telefone:</strong><br>
                                                <a href="tel:<?php echo htmlspecialchars($contato['telefone']); ?>">
                                                    <?php echo htmlspecialchars($contato['telefone']); ?>
                                                </a>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Data de Envio:</strong><br>
                                                <?php echo date('d/m/Y H:i', strtotime($contato['data_envio'])); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Data de Envio:</strong><br>
                                                <?php echo date('d/m/Y H:i', strtotime($contato['data_envio'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($contato['assunto']): ?>
                                        <div class="mb-3">
                                            <strong>Assunto:</strong><br>
                                            <?php echo htmlspecialchars($contato['assunto']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <strong>Mensagem:</strong><br>
                                        <div class="border rounded p-3 bg-light">
                                            <?php echo nl2br(htmlspecialchars($contato['mensagem'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <a href="mailto:<?php echo htmlspecialchars($contato['email']); ?>?subject=Re: <?php echo urlencode($contato['assunto'] ?? 'Contato via site'); ?>" class="btn btn-primary">
                                            <i class="fas fa-reply me-2"></i>
                                            Responder por Email
                                        </a>
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="fas fa-list me-2"></i>
                                            Ver Todos
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Informações do Contato
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong>ID:</strong> <?php echo $contato['id']; ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Status:</strong><br>
                                        <?php if ($contato['status'] === 'nao_lido'): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-envelope-open me-1"></i>
                                                Não Lido
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>
                                                Lido
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Data de Envio:</strong><br>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i:s', strtotime($contato['data_envio'])); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-lightbulb me-2"></i>Dicas:</h6>
                                        <ul class="mb-0">
                                            <li>Clique no email para abrir o cliente de email</li>
                                            <li>Clique no telefone para fazer uma ligação</li>
                                            <li>Use o botão "Responder" para enviar email</li>
                                            <li>Contatos não lidos são destacados</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Lista de Todos os Contatos -->
                    <!-- Filtros e Busca -->
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <form method="GET" class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" name="search" class="form-control" placeholder="Buscar por nome, email, telefone ou mensagem..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select name="status" class="form-select">
                                        <option value="">Todos os Status</option>
                                        <option value="nao_lido" <?php echo ($status_filter ?? '') === 'nao_lido' ? 'selected' : ''; ?>>Não Lidos</option>
                                        <option value="lido" <?php echo ($status_filter ?? '') === 'lido' ? 'selected' : ''; ?>>Lidos</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-search me-2"></i>
                                        Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="text-muted">
                                Total: <?php echo $total_contacts ?? 0; ?> contato(s)
                            </span>
                        </div>
                    </div>

                    <!-- Lista de Contatos -->
                    <div class="row">
                        <?php foreach ($contatos as $contato_item): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 <?php echo $contato_item['status'] === 'nao_lido' ? 'border-warning' : ''; ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="card-title mb-0">
                                            <?php echo htmlspecialchars($contato_item['nome']); ?>
                                        </h6>
                                        <?php if ($contato_item['status'] === 'nao_lido'): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-envelope-open me-1"></i>
                                                Não Lido
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>
                                                Lido
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text">
                                            <strong>Email:</strong> <?php echo htmlspecialchars($contato_item['email']); ?><br>
                                            <?php if ($contato_item['telefone']): ?>
                                                <strong>Telefone:</strong> <?php echo htmlspecialchars($contato_item['telefone']); ?><br>
                                            <?php endif; ?>
                                            <?php if ($contato_item['assunto']): ?>
                                                <strong>Assunto:</strong> <?php echo htmlspecialchars($contato_item['assunto']); ?><br>
                                            <?php endif; ?>
                                            <strong>Mensagem:</strong><br>
                                            <small class="text-muted">
                                                <?php echo strlen($contato_item['mensagem']) > 100 ? substr(htmlspecialchars($contato_item['mensagem']), 0, 100) . '...' : htmlspecialchars($contato_item['mensagem']); ?>
                                            </small>
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($contato_item['data_envio'])); ?>
                                        </small>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-flex gap-1">
                                            <a href="?id=<?php echo $contato_item['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>
                                                Ver
                                            </a>
                                            <a href="mailto:<?php echo htmlspecialchars($contato_item['email']); ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-reply me-1"></i>
                                                Email
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginação -->
                    <?php if (isset($total_pages) && $total_pages > 1): ?>
                        <nav aria-label="Navegação de páginas">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
