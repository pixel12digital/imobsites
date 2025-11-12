<?php
// Página de Comparador de Imóveis - imobsites

// Página do Comparador de Imóveis
$page_title = 'Comparador de Imóveis - ' . SITE_NAME;

// Buscar imóveis para comparação (da sessão ou parâmetros)
$imoveis_ids = [];
if (isset($_GET['ids'])) {
    $imoveis_ids = explode(',', $_GET['ids']);
} elseif (isset($_SESSION['comparador'])) {
    $imoveis_ids = $_SESSION['comparador'];
}

// Limitar a 4 imóveis para comparação
$imoveis_ids = array_slice($imoveis_ids, 0, 4);

// Buscar dados dos imóveis
$imoveis_comparacao = [];
if (!empty($imoveis_ids)) {
    $placeholders = str_repeat('?,', count($imoveis_ids) - 1) . '?';
    $sql = "SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro, l.estado, u.nome as corretor_nome, u.telefone as corretor_telefone 
            FROM imoveis i 
            INNER JOIN tipos_imovel t ON i.tipo_id = t.id 
            INNER JOIN localizacoes l ON i.localizacao_id = l.id 
            INNER JOIN usuarios u ON i.usuario_id = u.id 
            WHERE i.id IN ($placeholders)
            ORDER BY FIELD(i.id, $placeholders)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($imoveis_ids, $imoveis_ids));
    $imoveis_comparacao = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar características para comparação
$caracteristicas = [];
if (!empty($imoveis_comparacao)) {
    $placeholders = str_repeat('?,', count($imoveis_comparacao) - 1) . '?';
    $sql = "SELECT ic.imovel_id, c.nome, c.categoria 
            FROM imovel_caracteristicas ic 
            INNER JOIN caracteristicas c ON ic.caracteristica_id = c.id 
            WHERE ic.imovel_id IN ($placeholders)
            ORDER BY c.categoria, c.nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_column($imoveis_comparacao, 'id'));
    $caracteristicas_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar características por imóvel
    foreach ($caracteristicas_raw as $car) {
        $caracteristicas[$car['imovel_id']][] = $car;
    }
}
?>

<div class="container-fluid py-5">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2 mb-1">Comparador de Imóveis</h1>
                    <p class="text-muted mb-0">Compare até 4 imóveis lado a lado</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="adicionarImovel()">
                        <i class="fas fa-plus"></i> Adicionar Imóvel
                    </button>
                    <button class="btn btn-outline-secondary" onclick="limparComparador()">
                        <i class="fas fa-trash"></i> Limpar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($imoveis_comparacao)): ?>
        <!-- Estado vazio -->
        <div class="text-center py-5">
            <i class="fas fa-balance-scale fa-3x text-muted mb-3"></i>
            <h4>Nenhum imóvel para comparar</h4>
            <p class="text-muted mb-4">Adicione imóveis ao comparador para começar a análise</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= getPagePath('imoveis') ?>" class="btn btn-primary">
                    <i class="fas fa-search"></i> Buscar Imóveis
                </a>
                <button class="btn btn-outline-primary" onclick="adicionarImovel()">
                    <i class="fas fa-plus"></i> Adicionar Manualmente
                </button>
            </div>
        </div>
    <?php else: ?>
        <!-- Tabela de Comparação -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 200px;">Características</th>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <th class="text-center" style="width: 250px;">
                                <div class="position-relative">
                                    <button class="btn btn-sm btn-outline-light position-absolute top-0 end-0" 
                                            onclick="removerImovel(<?= $imovel['id'] ?>)" 
                                            title="Remover da comparação">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    
                                    <?php
                                    // Buscar primeira foto por ordem
                                    $stmt = $pdo->prepare("SELECT arquivo FROM fotos_imovel WHERE imovel_id = ? ORDER BY ordem ASC LIMIT 1");
                                    $stmt->execute([$imovel['id']]);
                                    $foto = $stmt->fetch();
                                    ?>
                                    
                                    <?php if ($foto && imageExists($foto['arquivo'])): ?>
                                        <img src="<?= getUploadPath($foto['arquivo']) ?>" 
                                             class="img-fluid rounded mb-2" alt="<?= htmlspecialchars($imovel['titulo']) ?>"
                                             style="height: 120px; width: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="no-image-placeholder d-flex align-items-center justify-content-center rounded mb-2" 
                                             style="height: 120px; width: 100%; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px dashed #dee2e6;">
                                            <div class="text-center">
                                                <i class="fas fa-home fa-2x text-muted"></i>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <h6 class="mb-1"><?= htmlspecialchars($imovel['titulo']) ?></h6>
                                    <small class="text-light"><?= htmlspecialchars($imovel['bairro']) ?>, <?= htmlspecialchars($imovel['cidade']) ?></small>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <!-- Informações Básicas -->
                    <tr class="table-primary">
                        <td colspan="<?= count($imoveis_comparacao) + 1 ?>" class="fw-bold">Informações Básicas</td>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Tipo</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center"><?= htmlspecialchars($imovel['tipo_nome']) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Preço</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center text-primary fw-bold"><?= formatPrice($imovel['preco']) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Status</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center">
                                <span class="badge bg-<?= $imovel['status'] === 'disponivel' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($imovel['status']) ?>
                                </span>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Destaque</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center">
                                <?php if ($imovel['destaque']): ?>
                                    <i class="fas fa-star text-warning"></i>
                                <?php else: ?>
                                    <i class="fas fa-star text-muted"></i>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <!-- Dimensões -->
                    <tr class="table-info">
                        <td colspan="<?= count($imoveis_comparacao) + 1 ?>" class="fw-bold">Dimensões</td>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Área Total</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center"><?= $imovel['area_total'] ?> m²</td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Área Construída</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center"><?= $imovel['area_construida'] ?> m²</td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <!-- Comodidades -->
                    <tr class="table-success">
                        <td colspan="<?= count($imoveis_comparacao) + 1 ?>" class="fw-bold">Comodidades</td>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Quartos</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center">
                                <i class="fas fa-bed text-primary"></i> <?= $imovel['quartos'] ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Banheiros</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center">
                                <i class="fas fa-bath text-primary"></i> <?= $imovel['banheiros'] ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Vagas</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center">
                                <i class="fas fa-car text-primary"></i> <?= $imovel['vagas'] ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Suítes</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center"><?= $imovel['suites'] ?></td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <!-- Características Específicas -->
                    <?php
                    $todas_caracteristicas = [];
                    foreach ($caracteristicas as $imovel_caracs) {
                        foreach ($imovel_caracs as $car) {
                            $todas_caracteristicas[$car['categoria']][$car['nome']] = true;
                        }
                    }
                    
                    foreach ($todas_caracteristicas as $categoria => $caracs):
                    ?>
                        <tr class="table-warning">
                            <td colspan="<?= count($imoveis_comparacao) + 1 ?>" class="fw-bold"><?= ucfirst($categoria) ?></td>
                        </tr>
                        
                        <?php foreach (array_keys($caracs) as $carac_nome): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($carac_nome) ?></td>
                                <?php foreach ($imoveis_comparacao as $imovel): ?>
                                    <td class="text-center">
                                        <?php
                                        $tem_carac = false;
                                        if (isset($caracteristicas[$imovel['id']])) {
                                            foreach ($caracteristicas[$imovel['id']] as $car) {
                                                if ($car['nome'] === $carac_nome) {
                                                    $tem_carac = true;
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                        <?php if ($tem_carac): ?>
                                            <i class="fas fa-check text-success"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times text-muted"></i>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    
                    <!-- Localização -->
                    <tr class="table-secondary">
                        <td colspan="<?= count($imoveis_comparacao) + 1 ?>" class="fw-bold">Localização</td>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Endereço</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center">
                                <?= htmlspecialchars($imovel['endereco']) ?>, <?= htmlspecialchars($imovel['numero']) ?>
                                <?php if ($imovel['complemento']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($imovel['complemento']) ?></small>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Bairro</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center"><?= htmlspecialchars($imovel['bairro']) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Cidade/Estado</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center"><?= htmlspecialchars($imovel['cidade']) ?>/<?= htmlspecialchars($imovel['estado']) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <!-- Corretor -->
                    <tr class="table-light">
                        <td colspan="<?= count($imoveis_comparacao) + 1 ?>" class="fw-bold">Especialista Responsável</td>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Nome</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center"><?= htmlspecialchars($imovel['corretor_nome']) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Contato</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center">
                                <a href="tel:<?= $imovel['corretor_telefone'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($imovel['corretor_telefone']) ?>
                                </a>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <!-- Ações -->
                    <tr class="table-dark">
                        <td colspan="<?= count($imoveis_comparacao) + 1 ?>" class="fw-bold">Ações</td>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Ver Detalhes</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center">
                                <a href="<?= getPagePath('imovel', ['id' => $imovel['id']]) ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> Ver Imóvel
                                </a>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <tr>
                        <td class="fw-bold">Adicionar aos Favoritos</td>
                        <?php foreach ($imoveis_comparacao as $imovel): ?>
                            <td class="text-center">
                                <button class="btn btn-outline-danger btn-sm" 
                                        onclick="toggleFavorito(<?= $imovel['id'] ?>)" 
                                        id="favorito-comp-<?= $imovel['id'] ?>">
                                    <i class="fas fa-heart"></i> Favorito
                                </button>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Resumo da Comparação -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Resumo da Comparação</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h6 class="text-primary">Menor Preço</h6>
                                <?php
                                $menor_preco = min(array_column($imoveis_comparacao, 'preco'));
                                $imovel_menor = array_filter($imoveis_comparacao, fn($i) => $i['preco'] == $menor_preco);
                                $imovel_menor = reset($imovel_menor);
                                ?>
                                <p class="h5 text-success"><?= formatPrice($menor_preco) ?></p>
                                <small class="text-muted"><?= htmlspecialchars($imovel_menor['titulo']) ?></small>
                            </div>
                            
                            <div class="col-md-3 text-center">
                                <h6 class="text-primary">Maior Área</h6>
                                <?php
                                $maior_area = max(array_column($imoveis_comparacao, 'area_total'));
                                $imovel_maior = array_filter($imoveis_comparacao, fn($i) => $i['area_total'] == $maior_area);
                                $imovel_maior = reset($imovel_maior);
                                ?>
                                <p class="h5 text-info"><?= $maior_area ?> m²</p>
                                <small class="text-muted"><?= htmlspecialchars($imovel_maior['titulo']) ?></small>
                            </div>
                            
                            <div class="col-md-3 text-center">
                                <h6 class="text-primary">Mais Quartos</h6>
                                <?php
                                $mais_quartos = max(array_column($imoveis_comparacao, 'quartos'));
                                $imovel_quartos = array_filter($imoveis_comparacao, fn($i) => $i['quartos'] == $mais_quartos);
                                $imovel_quartos = reset($imovel_quartos);
                                ?>
                                <p class="h5 text-warning"><?= $mais_quartos ?> quartos</p>
                                <small class="text-muted"><?= htmlspecialchars($imovel_quartos['titulo']) ?></small>
                            </div>
                            
                            <div class="col-md-3 text-center">
                                <h6 class="text-primary">Melhor Custo-Benefício</h6>
                                <?php
                                $melhor_cb = null;
                                $melhor_ratio = 0;
                                foreach ($imoveis_comparacao as $imovel) {
                                    $ratio = $imovel['area_total'] / $imovel['preco'];
                                    if ($ratio > $melhor_ratio) {
                                        $melhor_ratio = $ratio;
                                        $melhor_cb = $imovel;
                                    }
                                }
                                ?>
                                <p class="h5 text-success"><?= number_format($melhor_ratio * 1000000, 2) ?> m²/R$</p>
                                <small class="text-muted"><?= htmlspecialchars($melhor_cb['titulo']) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para Adicionar Imóvel -->
<div class="modal fade" id="modalAdicionarImovel" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Imóvel ao Comparador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Buscar por:</label>
                    <input type="text" class="form-control" id="buscaImovel" placeholder="Digite o título, endereço ou características...">
                </div>
                
                <div id="resultadosBusca" class="row g-3">
                    <!-- Resultados da busca aparecerão aqui -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Carregar favoritos
document.addEventListener('DOMContentLoaded', function() {
    carregarFavoritosComparador();
});

// Sistema de Favoritos para o comparador
function toggleFavorito(imovelId) {
    const btn = document.getElementById(`favorito-comp-${imovelId}`);
    const icon = btn.querySelector('i');
    
    let favoritos = JSON.parse(localStorage.getItem('favoritos') || '[]');
    
    if (favoritos.includes(imovelId)) {
        favoritos = favoritos.filter(id => id !== imovelId);
        icon.classList.remove('text-danger');
        btn.classList.remove('btn-danger');
        btn.classList.add('btn-outline-danger');
        mostrarNotificacao('Imóvel removido dos favoritos', 'warning');
    } else {
        favoritos.push(imovelId);
        icon.classList.add('text-danger');
        btn.classList.remove('btn-outline-danger');
        btn.classList.add('btn-danger');
        mostrarNotificacao('Imóvel adicionado aos favoritos', 'success');
    }
    
    localStorage.setItem('favoritos', JSON.stringify(favoritos));
}

function carregarFavoritosComparador() {
    const favoritos = JSON.parse(localStorage.getItem('favoritos') || '[]');
    
    favoritos.forEach(imovelId => {
        const btn = document.getElementById(`favorito-comp-${imovelId}`);
        if (btn) {
            const icon = btn.querySelector('i');
            icon.classList.add('text-danger');
            btn.classList.remove('btn-outline-danger');
            btn.classList.add('btn-danger');
        }
    });
}

// Adicionar imóvel ao comparador
function adicionarImovel() {
    const modal = new bootstrap.Modal(document.getElementById('modalAdicionarImovel'));
    modal.show();
    
    // Implementar busca AJAX aqui
    document.getElementById('buscaImovel').addEventListener('input', function() {
        const query = this.value;
        if (query.length > 2) {
            buscarImoveis(query);
        }
    });
}

// Buscar imóveis para adicionar ao comparador
function buscarImoveis(query) {
    // Implementar busca AJAX real
    const resultadosDiv = document.getElementById('resultadosBusca');
    resultadosDiv.innerHTML = '<div class="col-12 text-center"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>';
    
    // Simular busca
    setTimeout(() => {
        // Aqui você faria a busca real no banco
        mostrarResultadosBusca([]);
    }, 1000);
}

function mostrarResultadosBusca(imoveis) {
    const resultadosDiv = document.getElementById('resultadosBusca');
    
    if (imoveis.length === 0) {
        resultadosDiv.innerHTML = '<div class="col-12 text-center text-muted">Nenhum imóvel encontrado</div>';
        return;
    }
    
    // Implementar exibição dos resultados
}

// Remover imóvel do comparador
function removerImovel(imovelId) {
    if (confirm('Tem certeza que deseja remover este imóvel da comparação?')) {
        // Remover da sessão
        let comparador = <?= json_encode($imoveis_ids) ?>;
        comparador = comparador.filter(id => id != imovelId);
        
        // Redirecionar com novos IDs
        if (comparador.length > 0) {
            window.location.href = '<?= getPagePath('comparador') ?>&ids=' + comparador.join(',');
        } else {
            window.location.href = '<?= getPagePath('comparador') ?>';
        }
    }
}

// Limpar comparador
function limparComparador() {
    if (confirm('Tem certeza que deseja limpar o comparador?')) {
        window.location.href = '<?= getPagePath('comparador') ?>';
    }
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


