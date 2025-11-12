<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configurações ANTES de iniciar a sessão
$config_path = dirname(__DIR__) . '/../config/';
require_once $config_path . 'paths.php';
require_once $config_path . 'database.php';
require_once $config_path . 'tenant.php';
require_once $config_path . 'config.php';

// Agora iniciar a sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['tenant_id']) || (int)$_SESSION['tenant_id'] !== TENANT_ID) {
    header('Location: ../login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Processar exclusão
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Verificar se a localização pertence ao tenant
        $localizacao = fetch("SELECT id FROM localizacoes WHERE id = ? AND tenant_id = ?", [$id, TENANT_ID]);

        if (!$localizacao) {
            $error_message = "Localização não encontrada ou não pertence a este cliente.";
        } else {
            // Verificar se há imóveis usando esta localização
            $imoveis_count = fetch("SELECT COUNT(*) as total FROM imoveis WHERE localizacao_id = ? AND tenant_id = ?", [$id, TENANT_ID]);
            
            if ($imoveis_count['total'] > 0) {
                $error_message = "Não é possível excluir esta localização pois existem " . $imoveis_count['total'] . " imóvel(is) cadastrado(s) nela.";
            } else {
                // Excluir localização
                $deleted_stmt = query("DELETE FROM localizacoes WHERE id = ? AND tenant_id = ?", [$id, TENANT_ID]);
                if ($deleted_stmt && $deleted_stmt->rowCount() > 0) {
                    $success_message = "Localização excluída com sucesso!";
                } else {
                    $error_message = "Erro ao excluir localização.";
                }
            }
        }
    } catch (Exception $e) {
        $error_message = "Erro: " . $e->getMessage();
    }
}

// Buscar todas as localizações
$localizacoes = fetchAll("SELECT * FROM localizacoes WHERE tenant_id = ? ORDER BY estado, cidade, bairro", [TENANT_ID]);

// Buscar estatísticas
$total_localizacoes = count($localizacoes);
$estados_unicos = fetchAll("SELECT DISTINCT estado FROM localizacoes WHERE tenant_id = ? ORDER BY estado", [TENANT_ID]);
$total_estados = count($estados_unicos);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Localizações - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/subheader.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-map-marker-alt text-primary"></i>
                        Gerenciar Localizações
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="adicionar.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nova Localização
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Total de Localizações</h5>
                                        <h2 class="mb-0"><?php echo $total_localizacoes; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-map-marker-alt fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Estados</h5>
                                        <h2 class="mb-0"><?php echo $total_estados; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-flag fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Cidades</h5>
                                        <h2 class="mb-0"><?php echo count(array_unique(array_column($localizacoes, 'cidade'))); ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-city fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Localizações -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Lista de Localizações
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($localizacoes)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Nenhuma localização cadastrada</h5>
                                <p class="text-muted">Clique em "Nova Localização" para começar.</p>
                                <a href="adicionar.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Cadastrar Primeira Localização
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Cidade</th>
                                            <th>Bairro</th>
                                            <th>Estado</th>
                                            <th>CEP</th>
                                            <th>Imóveis</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($localizacoes as $localizacao): ?>
                                            <?php
                                            // Contar imóveis nesta localização
                                            $imoveis_count = fetch("SELECT COUNT(*) as total FROM imoveis WHERE localizacao_id = ? AND tenant_id = ?", [$localizacao['id'], TENANT_ID]);
                                            ?>
                                            <tr>
                                                <td><span class="badge bg-secondary">#<?php echo $localizacao['id']; ?></span></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($localizacao['cidade']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($localizacao['bairro']): ?>
                                                        <span class="text-muted"><?php echo htmlspecialchars($localizacao['bairro']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Não informado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $localizacao['estado']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($localizacao['cep']): ?>
                                                        <?php echo htmlspecialchars($localizacao['cep']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Não informado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($imoveis_count['total'] > 0): ?>
                                                        <span class="badge bg-success"><?php echo $imoveis_count['total']; ?> imóvel(is)</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Sem imóveis</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="editar.php?id=<?php echo $localizacao['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($imoveis_count['total'] == 0): ?>
                                                            <a href="?delete=<?php echo $localizacao['id']; ?>" 
                                                               class="btn btn-sm btn-outline-danger" 
                                                               title="Excluir"
                                                               onclick="return confirm('Tem certeza que deseja excluir esta localização?')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-outline-secondary" 
                                                                    title="Não pode excluir - possui imóveis"
                                                                    disabled>
                                                                <i class="fas fa-lock"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filtros por Estado -->
                <?php if (!empty($estados_unicos)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-filter"></i> Filtrar por Estado
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($estados_unicos as $estado): ?>
                                    <?php
                                    $count_estado = fetch("SELECT COUNT(*) as total FROM localizacoes WHERE estado = ?", [$estado['estado']]);
                                    ?>
                                    <div class="col-md-3 mb-2">
                                        <a href="?estado=<?php echo $estado['estado']; ?>" 
                                           class="btn btn-outline-primary w-100">
                                            <i class="fas fa-flag"></i> <?php echo $estado['estado']; ?>
                                            <span class="badge bg-primary ms-2"><?php echo $count_estado['total']; ?></span>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
