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
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['tenant_id']) || (int)$_SESSION['tenant_id'] !== TENANT_ID) {
    header('Location: ../login.php');
    exit;
}

// Processar exclusão
if (isset($_POST['excluir']) && isset($_POST['imovel_id'])) {
    $imovel_id = (int)$_POST['imovel_id'];
    
    try {
        // Excluir fotos primeiro (devido à foreign key)
        query("DELETE FROM fotos_imovel WHERE imovel_id = ? AND tenant_id = ?", [$imovel_id, TENANT_ID]);
        query("DELETE FROM imovel_caracteristicas WHERE imovel_id = ? AND tenant_id = ?", [$imovel_id, TENANT_ID]);
        
        // Excluir o imóvel
        query("DELETE FROM imoveis WHERE id = ? AND tenant_id = ?", [$imovel_id, TENANT_ID]);
        
        $success_message = "Imóvel excluído com sucesso!";
    } catch (Exception $e) {
        $error_message = "Erro ao excluir imóvel: " . $e->getMessage();
    }
}

// Processar alteração de status
if (isset($_POST['alterar_status']) && isset($_POST['imovel_id']) && isset($_POST['novo_status'])) {
    $imovel_id = (int)$_POST['imovel_id'];
    $novo_status = cleanInput($_POST['novo_status']);
    
    try {
        query("UPDATE imoveis SET status = ? WHERE id = ? AND tenant_id = ?", [$novo_status, $imovel_id, TENANT_ID]);
        $success_message = "Status alterado com sucesso!";
    } catch (Exception $e) {
        $error_message = "Erro ao alterar status: " . $e->getMessage();
    }
}

// Processar alteração de destaque
if (isset($_POST['alterar_destaque']) && isset($_POST['imovel_id'])) {
    $imovel_id = (int)$_POST['imovel_id'];
    
    try {
        $imovel = fetch("SELECT destaque FROM imoveis WHERE id = ? AND tenant_id = ?", [$imovel_id, TENANT_ID]);
        $novo_destaque = $imovel['destaque'] ? 0 : 1;
        
        query("UPDATE imoveis SET destaque = ? WHERE id = ? AND tenant_id = ?", [$novo_destaque, $imovel_id, TENANT_ID]);
        $success_message = "Destaque alterado com sucesso!";
    } catch (Exception $e) {
        $error_message = "Erro ao alterar destaque: " . $e->getMessage();
    }
}

// Buscar imóveis com paginação
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Filtros
$filtro_status = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
$filtro_tipo = isset($_GET['tipo']) ? (int)$_GET['tipo'] : 0;
$filtro_cidade = isset($_GET['cidade']) ? cleanInput($_GET['cidade']) : '';
$busca = isset($_GET['busca']) ? cleanInput($_GET['busca']) : '';

// Construir query
$sql = "SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro, u.nome as corretor_nome 
        FROM imoveis i 
        LEFT JOIN tipos_imovel t ON i.tipo_id = t.id AND t.tenant_id = i.tenant_id
        LEFT JOIN localizacoes l ON i.localizacao_id = l.id AND l.tenant_id = i.tenant_id
        LEFT JOIN usuarios u ON i.usuario_id = u.id AND u.tenant_id = i.tenant_id
        WHERE i.tenant_id = ?";

$params = [TENANT_ID];

if ($filtro_status) {
    $sql .= " AND i.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_tipo) {
    $sql .= " AND i.tipo_id = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_cidade) {
    $sql .= " AND l.cidade LIKE ?";
    $params[] = "%$filtro_cidade%";
}

if ($busca) {
    $sql .= " AND (i.titulo LIKE ? OR i.descricao LIKE ? OR l.bairro LIKE ?)";
    $searchTerm = "%$busca%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY i.data_criacao DESC LIMIT " . (int)$por_pagina . " OFFSET " . (int)$offset;

$imoveis = fetchAll($sql, $params);

// Total de registros para paginação
$sql_count = "SELECT COUNT(*) as total FROM imoveis WHERE tenant_id = ?";
$count_params = [TENANT_ID];

if ($filtro_status) {
    $sql_count .= " AND status = ?";
    $count_params[] = $filtro_status;
}

if ($filtro_tipo) {
    $sql_count .= " AND tipo_id = ?";
    $count_params[] = $filtro_tipo;
}

if ($filtro_cidade) {
    $sql_count .= " AND localizacao_id IN (SELECT id FROM localizacoes WHERE tenant_id = ? AND cidade LIKE ?)";
    $count_params[] = TENANT_ID;
    $count_params[] = "%$filtro_cidade%";
}

if ($busca) {
    $sql_count .= " AND (titulo LIKE ? OR descricao LIKE ? OR id IN (
        SELECT imovel_id FROM localizacoes l
        WHERE l.tenant_id = ? AND l.bairro LIKE ?
    ))";
    $searchTerm = "%$busca%";
    $count_params[] = $searchTerm;
    $count_params[] = $searchTerm;
    $count_params[] = TENANT_ID;
    $count_params[] = $searchTerm;
}

$result_count = fetch($sql_count, $count_params);
$total_imoveis = $result_count ? $result_count['total'] : 0;
$total_paginas = ceil($total_imoveis / $por_pagina);

// Buscar tipos e cidades para filtros
$tipos_imovel = fetchAll("SELECT id, nome FROM tipos_imovel WHERE tenant_id = ? ORDER BY nome", [TENANT_ID]);
$cidades = fetchAll("SELECT DISTINCT cidade FROM localizacoes WHERE tenant_id = ? ORDER BY cidade", [TENANT_ID]);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Imóveis - Painel Administrativo</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-home me-2"></i>JTR Imóveis - Admin
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['admin_nome']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../perfil.php"><i class="fas fa-user-cog me-2"></i>Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-home me-2"></i>Imóveis
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../usuarios/">
                                <i class="fas fa-users me-2"></i>Usuários
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../contatos/">
                                <i class="fas fa-envelope me-2"></i>Contatos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../configuracoes/">
                                <i class="fas fa-cog me-2"></i>Configurações
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../../" target="_blank">
                                <i class="fas fa-external-link-alt me-2"></i>Ver Site
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Conteúdo Principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestão de Imóveis</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="adicionar.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i>Novo Imóvel
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Mensagens -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="busca" class="form-label">Buscar</label>
                                <input type="text" class="form-control" id="busca" name="busca" 
                                       value="<?php echo htmlspecialchars($busca); ?>" 
                                       placeholder="Título, descrição ou bairro">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Todos</option>
                                    <option value="disponivel" <?php echo $filtro_status === 'disponivel' ? 'selected' : ''; ?>>Disponível</option>
                                    <option value="vendido" <?php echo $filtro_status === 'vendido' ? 'selected' : ''; ?>>Vendido</option>
                                    <option value="alugado" <?php echo $filtro_status === 'alugado' ? 'selected' : ''; ?>>Alugado</option>
                                    <option value="reservado" <?php echo $filtro_status === 'reservado' ? 'selected' : ''; ?>>Reservado</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="tipo" class="form-label">Tipo</label>
                                <select class="form-select" id="tipo" name="tipo">
                                    <option value="">Todos</option>
                                    <?php foreach ($tipos_imovel as $tipo): ?>
                                        <option value="<?php echo $tipo['id']; ?>" <?php echo $filtro_tipo == $tipo['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipo['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="cidade" class="form-label">Cidade</label>
                                <select class="form-select" id="cidade" name="cidade">
                                    <option value="">Todas</option>
                                    <?php foreach ($cidades as $cidade): ?>
                                        <option value="<?php echo htmlspecialchars($cidade['cidade']); ?>" <?php echo $filtro_cidade === $cidade['cidade'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cidade['cidade']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Filtrar
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Limpar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Imóveis -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Imóveis (<?php echo $total_imoveis; ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($imoveis): ?>
                                                         <div class="table-responsive">
                                 <table class="table table-hover table-imoveis">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Título</th>
                                            <th>Tipo</th>
                                            <th>Localização</th>
                                            <th>Preço</th>
                                            <th>Status</th>
                                            <th>Destaque</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($imoveis as $imovel): ?>
                                            <tr>
                                                <td><?php echo $imovel['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($imovel['titulo']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        Corretor: <?php echo htmlspecialchars($imovel['corretor_nome']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($imovel['tipo_nome']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                                    <?php echo htmlspecialchars($imovel['bairro'] . ', ' . $imovel['cidade']); ?>
                                                </td>
                                                <td>
                                                    <strong class="text-primary">
                                                        <?php echo formatPrice($imovel['preco']); ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="imovel_id" value="<?php echo $imovel['id']; ?>">
                                                        <select name="novo_status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                            <option value="disponivel" <?php echo $imovel['status'] === 'disponivel' ? 'selected' : ''; ?>>Disponível</option>
                                                            <option value="vendido" <?php echo $imovel['status'] === 'vendido' ? 'selected' : ''; ?>>Vendido</option>
                                                            <option value="alugado" <?php echo $imovel['status'] === 'alugado' ? 'selected' : ''; ?>>Alugado</option>
                                                            <option value="reservado" <?php echo $imovel['status'] === 'reservado' ? 'selected' : ''; ?>>Reservado</option>
                                                        </select>
                                                        <input type="hidden" name="alterar_status" value="1">
                                                    </form>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="imovel_id" value="<?php echo $imovel['id']; ?>">
                                                        <input type="hidden" name="alterar_destaque" value="1">
                                                        <button type="submit" class="btn btn-sm <?php echo $imovel['destaque'] ? 'btn-warning' : 'btn-outline-warning'; ?>">
                                                            <i class="fas fa-star"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="editar.php?id=<?php echo $imovel['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="fotos.php?id=<?php echo $imovel['id']; ?>" 
                                                           class="btn btn-outline-info" title="Gerenciar Fotos">
                                                            <i class="fas fa-images"></i>
                                                        </a>
                                                        <a href="../../index.php?page=imovel&id=<?php echo $imovel['id']; ?>" 
                                                           class="btn btn-outline-secondary" title="Ver no Site" target="_blank">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="confirmarExclusao(<?php echo $imovel['id']; ?>)" title="Excluir">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginação -->
                            <?php if ($total_paginas > 1): ?>
                                <nav aria-label="Navegação de páginas">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($pagina > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>&status=<?php echo $filtro_status; ?>&tipo=<?php echo $filtro_tipo; ?>&cidade=<?php echo $filtro_cidade; ?>&busca=<?php echo $busca; ?>">
                                                    Anterior
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                            <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                                <a class="page-link" href="?pagina=<?php echo $i; ?>&status=<?php echo $filtro_status; ?>&tipo=<?php echo $filtro_tipo; ?>&cidade=<?php echo $filtro_cidade; ?>&busca=<?php echo $busca; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($pagina < $total_paginas): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>&status=<?php echo $filtro_status; ?>&tipo=<?php echo $filtro_tipo; ?>&cidade=<?php echo $filtro_cidade; ?>&busca=<?php echo $busca; ?>">
                                                    Próxima
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-home fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Nenhum imóvel encontrado</h5>
                                <p class="text-muted">Tente ajustar os filtros ou adicionar um novo imóvel.</p>
                                <a href="adicionar.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Adicionar Imóvel
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="confirmarExclusaoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir este imóvel?</p>
                    <p class="text-danger"><strong>Esta ação não pode ser desfeita!</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="imovel_id" id="imovelExcluirId">
                        <button type="submit" name="excluir" class="btn btn-danger">Excluir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Admin JS -->
    <script src="../assets/js/admin.js"></script>
    
    <script>
    function confirmarExclusao(imovelId) {
        document.getElementById('imovelExcluirId').value = imovelId;
        new bootstrap.Modal(document.getElementById('confirmarExclusaoModal')).show();
    }
    </script>
</body>
</html>
