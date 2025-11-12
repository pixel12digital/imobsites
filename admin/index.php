<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configurações ANTES de iniciar a sessão
require_once '../config/paths.php';
require_once '../config/database.php';
require_once '../config/tenant.php';
require_once '../config/config.php';

// Agora iniciar a sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['tenant_id']) || (int)$_SESSION['tenant_id'] !== TENANT_ID) {
    header('Location: login.php');
    exit;
}

// Buscar estatísticas para o dashboard
$total_imoveis = fetch("SELECT COUNT(*) as total FROM imoveis WHERE tenant_id = ?", [TENANT_ID])['total'];
$total_usuarios = fetch("SELECT COUNT(*) as total FROM usuarios WHERE tenant_id = ?", [TENANT_ID])['total'];
    // $total_contatos = fetch("SELECT COUNT(*) as total FROM contatos")['total'];
    $total_contatos = 0; // Valor fixo para evitar erros
$imoveis_destaque = fetch("SELECT COUNT(*) as total FROM imoveis WHERE destaque = 1 AND tenant_id = ?", [TENANT_ID])['total'];

// Buscar imóveis recentes
$imoveis_recentes = fetchAll("
    SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro 
    FROM imoveis i 
    LEFT JOIN tipos_imovel t ON i.tipo_id = t.id AND t.tenant_id = i.tenant_id
    LEFT JOIN localizacoes l ON i.localizacao_id = l.id AND l.tenant_id = i.tenant_id
    WHERE i.tenant_id = ?
    ORDER BY i.data_criacao DESC 
    LIMIT 5
", [TENANT_ID]);

    // Buscar contatos recentes - Ocultado temporariamente
    /*
    $contatos_recentes = fetchAll("
        SELECT * FROM contatos
        ORDER BY data_criacao DESC
        LIMIT 5
    ");
    */
    $contatos_recentes = []; // Array vazio para evitar erros

$page_title = 'Dashboard';
include 'includes/header.php';
?>

<!-- Mensagens de Sucesso -->
<?php if (isset($_GET['success']) && $_GET['success'] === 'imovel_cadastrado'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <strong>Sucesso!</strong> Imóvel cadastrado com sucesso!
        <?php if (isset($_GET['id'])): ?>
            <br><small class="text-muted">ID do imóvel: <?php echo htmlspecialchars($_GET['id']); ?></small>
        <?php endif; ?>
        <div class="mt-2">
            <a href="imoveis/adicionar.php" class="btn btn-success btn-sm me-2">
                <i class="fas fa-plus me-1"></i>Adicionar Outro Imóvel
            </a>
            <a href="imoveis/" class="btn btn-primary btn-sm">
                <i class="fas fa-home me-1"></i>Ver Todos os Imóveis
            </a>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'imovel_atualizado'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <strong>Sucesso!</strong> Imóvel atualizado com sucesso!
        <div class="mt-2">
            <a href="imoveis/" class="btn btn-primary btn-sm me-2">
                <i class="fas fa-home me-1"></i>Ver Todos os Imóveis
            </a>
            <a href="imoveis/adicionar.php" class="btn btn-success btn-sm">
                <i class="fas fa-plus me-1"></i>Adicionar Novo Imóvel
            </a>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="imoveis/adicionar.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus me-1"></i>Novo Imóvel
            </a>
        </div>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total de Imóveis
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_imoveis; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-home fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Imóveis em Destaque
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $imoveis_destaque; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-star fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total de Usuários
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_usuarios; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contatos - Ocultado temporariamente
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Contatos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_contatos; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-envelope fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    -->
</div>

<!-- Conteúdo em Largura Total -->
<div class="row">
    <!-- Imóveis Recentes -->
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-home me-2"></i>Imóveis Recentes
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($imoveis_recentes)): ?>
                    <p class="text-muted">Nenhum imóvel cadastrado ainda.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Imóvel</th>
                                    <th>Tipo</th>
                                    <th>Localização</th>
                                    <th>Preço</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($imoveis_recentes as $imovel): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $imovel['titulo']; ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($imovel['tipo_nome'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php 
                                            $localizacao = [];
                                            if (!empty($imovel['cidade'])) $localizacao[] = $imovel['cidade'];
                                            if (!empty($imovel['bairro'])) $localizacao[] = $imovel['bairro'];
                                            echo htmlspecialchars(implode(', ', $localizacao) ?: 'N/A');
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                R$ <?php echo number_format($imovel['preco'], 2, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="imoveis/editar.php?id=<?php echo $imovel['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo getPagePath('imovel', ['id' => $imovel['id']]); ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-info" title="Visualizar">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger" 
                                                    title="Excluir"
                                                    onclick="confirmarExclusao(<?php echo $imovel['id']; ?>, '<?php echo htmlspecialchars($imovel['titulo'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Contatos Recentes - Ocultado temporariamente
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-envelope me-2"></i>Contatos Recentes
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($contatos_recentes)): ?>
                    <p class="text-muted">Nenhum contato recebido ainda.</p>
                <?php else: ?>
                    <?php foreach ($contatos_recentes as $contato): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($contato['nome']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($contato['email']); ?></small>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y', strtotime($contato['data_criacao'])); ?>
                                </div>
                            </div>
                            <p class="mb-1 mt-2"><?php echo htmlspecialchars(substr($contato['mensagem'], 0, 100)); ?>...</p>
                            <a href="contatos/visualizar.php?id=<?php echo $contato['id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                Ver Detalhes
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    -->
</div>

<?php include 'includes/footer.php'; ?>

<script>
function confirmarExclusao(imovelId, titulo) {
    if (confirm(`Tem certeza que deseja EXCLUIR o imóvel "${titulo}"?\n\n⚠️ ATENÇÃO: Esta ação é IRREVERSÍVEL e excluirá:\n• O imóvel\n• Todas as fotos\n• Características associadas\n• Histórico de preços\n• Interesses relacionados\n\nDigite "EXCLUIR" para confirmar:`)) {
        const confirmacao = prompt('Digite "EXCLUIR" para confirmar a exclusão:');
        if (confirmacao === 'EXCLUIR') {
            // Mostrar loading
            const btn = event.target.closest('button');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Excluindo...';
            btn.disabled = true;
            
            // Fazer requisição AJAX para exclusão
            fetch('imoveis/excluir.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: imovelId,
                    confirmacao: confirmacao
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar mensagem de sucesso
                    alert('✅ Imóvel excluído com sucesso!');
                    // Recarregar a página para atualizar a lista
                    location.reload();
                } else {
                    alert('❌ Erro ao excluir: ' + (data.message || 'Erro desconhecido'));
                    // Restaurar botão
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao excluir: ' + error.message);
                // Restaurar botão
                btn.innerHTML = originalContent;
                btn.disabled = false;
            });
        }
    }
}
</script>
