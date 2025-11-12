<?php
// Página de Histórico de Preços - imobsites
?>

<?php
// Página de Histórico de Preços
$page_title = 'Histórico de Preços - ' . SITE_NAME;

// Buscar imóvel específico se ID fornecido
$imovel_id = isset($_GET['imovel_id']) ? (int)$_GET['imovel_id'] : 0;
$imovel = null;

if ($imovel_id > 0) {
    // Buscar dados do imóvel
    $stmt = $pdo->prepare("SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro 
                           FROM imoveis i 
                           INNER JOIN tipos_imovel t ON i.tipo_id = t.id 
                           INNER JOIN localizacoes l ON i.localizacao_id = l.id 
                           WHERE i.id = ?");
    $stmt->execute([$imovel_id]);
    $imovel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($imovel) {
        // Buscar histórico de preços
        $stmt = $pdo->prepare("SELECT hp.*, u.nome as usuario_nome 
                               FROM historico_precos hp 
                               LEFT JOIN usuarios u ON hp.usuario_id = u.id 
                               WHERE hp.imovel_id = ? 
                               ORDER BY hp.data_alteracao DESC");
        $stmt->execute([$imovel_id]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Buscar todos os imóveis com histórico de preços
    $stmt = $pdo->prepare("SELECT i.id, i.titulo, i.preco, t.nome as tipo_nome, l.cidade, l.bairro,
                           (SELECT COUNT(*) FROM historico_precos WHERE imovel_id = i.id) as total_alteracoes,
                           (SELECT MAX(data_alteracao) FROM historico_precos WHERE imovel_id = i.id) as ultima_alteracao
                           FROM imoveis i 
                           INNER JOIN tipos_imovel t ON i.tipo_id = t.id 
                           INNER JOIN localizacoes l ON i.localizacao_id = l.id 
                           WHERE EXISTS (SELECT 1 FROM historico_precos WHERE imovel_id = i.id)
                           ORDER BY ultima_alteracao DESC");
    $stmt->execute();
    $imoveis_com_historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid py-5">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2 mb-1">Histórico de Preços</h1>
                    <p class="text-muted mb-0">Acompanhe a evolução dos preços dos imóveis</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= getPagePath('imoveis') ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Voltar aos Imóveis
                    </a>
                    <button class="btn btn-outline-success" onclick="exportarHistorico()">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($imovel_id > 0 && $imovel): ?>
        <!-- Histórico de um imóvel específico -->
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-home"></i> <?= htmlspecialchars($imovel['titulo']) ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Tipo:</strong> <?= htmlspecialchars($imovel['tipo_nome']) ?></p>
                                <p><strong>Localização:</strong> <?= htmlspecialchars($imovel['bairro']) ?>, <?= htmlspecialchars($imovel['cidade']) ?></p>
                                <p><strong>Preço Atual:</strong> <span class="h5 text-primary"><?= formatPrice($imovel['preco']) ?></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Área Total:</strong> <?= $imovel['area_total'] ?> m²</p>
                                <p><strong>Quartos:</strong> <?= $imovel['quartos'] ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?= $imovel['status'] === 'disponivel' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($imovel['status']) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráfico de Evolução de Preços -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Evolução de Preços</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="graficoPrecos" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Tabela de Histórico -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Alterações de Preço</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historico)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                                <p class="text-muted">Nenhuma alteração de preço registrada para este imóvel.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Data</th>
                                            <th>Preço Anterior</th>
                                            <th>Preço Novo</th>
                                            <th>Variação</th>
                                            <th>Motivo</th>
                                            <th>Alterado por</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historico as $alteracao): ?>
                                            <?php
                                            $variacao = $alteracao['preco_novo'] - $alteracao['preco_anterior'];
                                            $percentual = ($variacao / $alteracao['preco_anterior']) * 100;
                                            $classe_variacao = $variacao > 0 ? 'text-success' : ($variacao < 0 ? 'text-danger' : 'text-muted');
                                            $icone_variacao = $variacao > 0 ? 'fa-arrow-up' : ($variacao < 0 ? 'fa-arrow-down' : 'fa-minus');
                                            ?>
                                            <tr>
                                                <td><?= formatDate($alteracao['data_alteracao']) ?></td>
                                                <td><?= formatPrice($alteracao['preco_anterior']) ?></td>
                                                <td><?= formatPrice($alteracao['preco_novo']) ?></td>
                                                <td>
                                                    <span class="<?= $classe_variacao ?>">
                                                        <i class="fas <?= $icone_variacao ?>"></i>
                                                        <?= formatPrice(abs($variacao)) ?>
                                                        (<?= number_format($percentual, 1) ?>%)
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($alteracao['motivo']) ?></td>
                                                <td><?= htmlspecialchars($alteracao['usuario_nome'] ?? 'Sistema') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        // Gráfico de evolução de preços
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('graficoPrecos').getContext('2d');
            
            const dados = <?= json_encode($historico) ?>;
            const labels = dados.map(h => '<?= date('d/m/Y', strtotime("' + h.data_alteracao + '")) ?>').reverse();
            const precos = dados.map(h => h.preco_novo).reverse();
            
            // Adicionar preço atual se não estiver no histórico
            if (dados.length > 0 && dados[0]['preco_novo'] != <?= $imovel['preco'] ?>) {
                labels.push('<?= date('d/m/Y') ?>');
                precos.push(<?= $imovel['preco'] ?>);
            }
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Preço (R$)',
                        data: precos,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Evolução do Preço'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>

    <?php else: ?>
        <!-- Lista de todos os imóveis com histórico -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Imóveis com Histórico de Preços</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($imoveis_com_historico)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                <h4>Nenhum histórico encontrado</h4>
                                <p class="text-muted">Os imóveis ainda não tiveram alterações de preço registradas.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Imóvel</th>
                                            <th>Tipo</th>
                                            <th>Localização</th>
                                            <th>Preço Atual</th>
                                            <th>Total de Alterações</th>
                                            <th>Última Alteração</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($imoveis_com_historico as $imovel): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($imovel['titulo']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($imovel['tipo_nome']) ?></td>
                                                <td><?= htmlspecialchars($imovel['bairro']) ?>, <?= htmlspecialchars($imovel['cidade']) ?></td>
                                                <td>
                                                    <span class="h6 text-primary"><?= formatPrice($imovel['preco']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= $imovel['total_alteracoes'] ?> alterações</span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= formatDate($imovel['ultima_alteracao']) ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?= getPagePath('historico-precos', ['imovel_id' => $imovel['id']]) ?>" 
                                                           class="btn btn-outline-primary">
                                                            <i class="fas fa-chart-line"></i> Ver Histórico
                                                        </a>
                                                        <a href="<?= getPagePath('imovel', ['id' => $imovel['id']]) ?>" 
                                                           class="btn btn-outline-info">
                                                            <i class="fas fa-eye"></i> Ver Imóvel
                                                        </a>
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
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Exportar histórico
function exportarHistorico() {
    // Implementar exportação para CSV/PDF
    mostrarNotificacao('Funcionalidade de exportação em desenvolvimento', 'info');
}

// Notificações
function mostrarNotificacao(mensagem, tipo = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${tipo} border-0 position-fixed`;
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${mensagem}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>


