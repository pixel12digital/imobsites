<?php
// Página de Filtros Avançados - imobsites

// Página de Filtros Avançados por Características
$page_title = 'Filtros Avançados - ' . SITE_NAME;

// Buscar todas as características organizadas por categoria
$stmt = $pdo->prepare("SELECT c.*, COUNT(ic.imovel_id) as total_imoveis 
                      FROM caracteristicas c 
                      LEFT JOIN imovel_caracteristicas ic ON c.id = ic.caracteristica_id AND ic.tenant_id = ?
                      WHERE c.ativo = 1 
                      GROUP BY c.id 
                      ORDER BY c.categoria, c.nome");
$stmt->execute([TENANT_ID]);
$caracteristicas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar por categoria
$caracteristicas_por_categoria = [];
foreach ($caracteristicas as $car) {
    $caracteristicas_por_categoria[$car['categoria']][] = $car;
}

// Processar filtros se enviados
$filtros_aplicados = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $caracteristicas_selecionadas = $_POST['caracteristicas'] ?? [];
    $preco_min = $_POST['preco_min'] ?? '';
    $preco_max = $_POST['preco_max'] ?? '';
    $area_min = $_POST['area_min'] ?? '';
    $area_max = $_POST['area_max'] ?? '';
    $tipo_id = $_POST['tipo_id'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    
    // Construir query com filtros de características
    $sql = "SELECT DISTINCT i.*, t.nome as tipo_nome, l.cidade, l.bairro, u.nome as corretor_nome 
            FROM imoveis i 
            INNER JOIN tipos_imovel t ON i.tipo_id = t.id 
            INNER JOIN localizacoes l ON i.localizacao_id = l.id 
            INNER JOIN usuarios u ON i.usuario_id = u.id 
            WHERE i.tenant_id = ? AND i.status = 'disponivel'";
    
    $params = [TENANT_ID];
    
    if (!empty($caracteristicas_selecionadas)) {
        $placeholders = str_repeat('?,', count($caracteristicas_selecionadas) - 1) . '?';
        $sql .= " AND i.id IN (
                    SELECT DISTINCT imovel_id 
                    FROM imovel_caracteristicas 
                    WHERE tenant_id = ? AND caracteristica_id IN ($placeholders)
                    GROUP BY imovel_id 
                    HAVING COUNT(DISTINCT caracteristica_id) = ?
                  )";
        $params = array_merge($params, [TENANT_ID], $caracteristicas_selecionadas, [count($caracteristicas_selecionadas)]);
    }
    
    if (!empty($preco_min)) {
        $sql .= " AND i.preco >= ?";
        $params[] = $preco_min;
    }
    
    if (!empty($preco_max)) {
        $sql .= " AND i.preco <= ?";
        $params[] = $preco_max;
    }
    
    if (!empty($area_min)) {
        $sql .= " AND i.area_total >= ?";
        $params[] = $area_min;
    }
    
    if (!empty($area_max)) {
        $sql .= " AND i.area_total <= ?";
        $params[] = $area_max;
    }
    
    if (!empty($tipo_id)) {
        $sql .= " AND i.tipo_id = ?";
        $params[] = $tipo_id;
    }
    
    if (!empty($cidade)) {
        $sql .= " AND l.cidade LIKE ?";
        $params[] = "%$cidade%";
    }
    
    $sql .= " ORDER BY i.destaque DESC, i.data_criacao DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $imoveis_filtrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filtros_aplicados = [
        'caracteristicas' => $caracteristicas_selecionadas,
        'preco_min' => $preco_min,
        'preco_max' => $preco_max,
        'area_min' => $area_min,
        'area_max' => $area_max,
        'tipo_id' => $tipo_id,
        'cidade' => $cidade
    ];
}

// Buscar dados para filtros básicos
$tipos_stmt = $pdo->prepare("SELECT * FROM tipos_imovel WHERE tenant_id = ? AND ativo = 1");
$tipos_stmt->execute([TENANT_ID]);
$tipos = $tipos_stmt->fetchAll();

$cidades_stmt = $pdo->prepare("SELECT DISTINCT cidade FROM localizacoes WHERE tenant_id = ? ORDER BY cidade");
$cidades_stmt->execute([TENANT_ID]);
$cidades = $cidades_stmt->fetchAll();
?>

<div class="container-fluid py-5">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2 mb-1">Filtros Avançados</h1>
                    <p class="text-muted mb-0">Filtre imóveis por características específicas</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= getPagePath('imoveis') ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Voltar aos Imóveis
                    </a>
                    <button class="btn btn-outline-success" onclick="salvarFiltros()">
                        <i class="fas fa-save"></i> Salvar Filtros
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Formulário de Filtros -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros por Características</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="filtrosAvancadosForm">
                        <!-- Filtros Básicos -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary">Filtros Básicos</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Tipo de Imóvel</label>
                                <select name="tipo_id" class="form-select">
                                    <option value="">Todos os tipos</option>
                                    <?php foreach ($tipos as $tipo): ?>
                                        <option value="<?= $tipo['id'] ?>" <?= ($filtros_aplicados['tipo_id'] ?? '') == $tipo['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tipo['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Cidade</label>
                                <select name="cidade" class="form-select">
                                    <option value="">Todas as cidades</option>
                                    <?php foreach ($cidades as $c): ?>
                                        <option value="<?= htmlspecialchars($c['cidade']) ?>" <?= ($filtros_aplicados['cidade'] ?? '') === $c['cidade'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['cidade']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <label class="form-label">Preço Mín.</label>
                                    <input type="number" name="preco_min" class="form-control" placeholder="R$ 0" 
                                           value="<?= $filtros_aplicados['preco_min'] ?? '' ?>" min="0" step="1000">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Preço Máx.</label>
                                    <input type="number" name="preco_max" class="form-control" placeholder="R$ 999.999" 
                                           value="<?= $filtros_aplicados['preco_max'] ?? '' ?>" min="0" step="1000">
                                </div>
                            </div>
                            
                            <div class="row mt-2">
                                <div class="col-6">
                                    <label class="form-label">Área Mín. (m²)</label>
                                    <input type="number" name="area_min" class="form-control" placeholder="0" 
                                           value="<?= $filtros_aplicados['area_min'] ?? '' ?>" min="0" step="10">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Área Máx. (m²)</label>
                                    <input type="number" name="area_max" class="form-control" placeholder="999" 
                                           value="<?= $filtros_aplicados['area_max'] ?? '' ?>" min="0" step="10">
                                </div>
                            </div>
                        </div>

                        <!-- Filtros por Características -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary">Características Específicas</h6>
                            <p class="text-muted small">Selecione as características que o imóvel DEVE ter:</p>
                            
                            <?php foreach ($caracteristicas_por_categoria as $categoria => $caracs): ?>
                                <div class="mb-3">
                                    <h6 class="text-secondary"><?= ucfirst($categoria) ?></h6>
                                    <?php foreach ($caracs as $car): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="caracteristicas[]" 
                                                   value="<?= $car['id'] ?>" 
                                                   id="car_<?= $car['id'] ?>"
                                                   <?= in_array($car['id'], $filtros_aplicados['caracteristicas'] ?? []) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="car_<?= $car['id'] ?>">
                                                <?= htmlspecialchars($car['nome']) ?>
                                                <span class="badge bg-light text-dark ms-2"><?= $car['total_imoveis'] ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Botões -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Aplicar Filtros
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="limparFiltros()">
                                <i class="fas fa-times"></i> Limpar Filtros
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div class="col-lg-8">
            <?php if (isset($imoveis_filtrados)): ?>
                <!-- Header dos Resultados -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1">Resultados da Busca</h4>
                        <p class="text-muted mb-0"><?= count($imoveis_filtrados) ?> imóveis encontrados</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="toggleView()">
                            <i class="fas fa-th" id="viewIcon"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="exportarResultados()">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                    </div>
                </div>

                <!-- Filtros Aplicados -->
                <?php if (!empty($filtros_aplicados['caracteristicas'])): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h6 class="mb-2">Filtros Aplicados:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($filtros_aplicados['caracteristicas'] as $car_id): ?>
                                    <?php
                                    $car_nome = '';
                                    foreach ($caracteristicas as $car) {
                                        if ($car['id'] == $car_id) {
                                            $car_nome = $car['nome'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="badge bg-primary"><?= htmlspecialchars($car_nome) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Grid de Imóveis -->
                <?php if (empty($imoveis_filtrados)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>Nenhum imóvel encontrado</h4>
                        <p class="text-muted">Tente ajustar os filtros ou remover algumas características</p>
                    </div>
                <?php else: ?>
                    <div class="row" id="imoveisGrid">
                        <?php foreach ($imoveis_filtrados as $imovel): ?>
                            <div class="col-lg-6 col-md-6 mb-4">
                                <div class="card h-100 shadow-sm hover-shadow">
                                    <div class="position-relative">
                                        <?php
                                        // Buscar primeira foto por ordem
                                        $stmt = $pdo->prepare("SELECT arquivo FROM fotos_imovel WHERE imovel_id = ? AND tenant_id = ? ORDER BY ordem ASC LIMIT 1");
                                        $stmt->execute([$imovel['id'], TENANT_ID]);
                                        $foto = $stmt->fetch();
                                        ?>
                                        
                                        <?php if ($foto && imageExists($foto['arquivo'])): ?>
                                            <img src="<?= getUploadPath($foto['arquivo']) ?>" 
                                                 class="card-img-top" alt="<?= htmlspecialchars($imovel['titulo']) ?>"
                                                 style="height: 200px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="no-image-placeholder d-flex align-items-center justify-content-center" 
                                                 style="height: 200px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px 8px 0 0;">
                                                <div class="text-center">
                                                    <i class="fas fa-home fa-3x text-muted mb-2"></i>
                                                    <p class="text-muted small mb-0">Foto não disponível</p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Badges -->
                                        <div class="position-absolute top-0 start-0 m-2">
                                            <?php if ($imovel['destaque']): ?>
                                                <span class="badge bg-warning text-dark">Destaque</span>
                                            <?php endif; ?>
                                            <span class="badge bg-success">Disponível</span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?= htmlspecialchars($imovel['titulo']) ?></h5>
                                        <p class="card-text text-muted small">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?= htmlspecialchars($imovel['bairro']) ?>, <?= htmlspecialchars($imovel['cidade']) ?>
                                        </p>
                                        
                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <small class="text-muted">Quartos</small>
                                                <div class="fw-bold"><?= $imovel['quartos'] ?></div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Banheiros</small>
                                                <div class="fw-bold"><?= $imovel['banheiros'] ?></div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Vagas</small>
                                                <div class="fw-bold"><?= $imovel['vagas'] ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="h5 text-primary mb-0"><?= formatPrice($imovel['preco']) ?></span>
                                                <small class="text-muted"><?= formatDate($imovel['data_criacao']) ?></small>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <a href="<?= getPagePath('imovel', ['id' => $imovel['id']]) ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> Ver Detalhes
                                                </a>
                                                <button class="btn btn-outline-info btn-sm" 
                                                        onclick="adicionarAoComparador(<?= $imovel['id'] ?>)">
                                                    <i class="fas fa-balance-scale"></i> Comparar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Estado inicial -->
                <div class="text-center py-5">
                    <i class="fas fa-filter fa-3x text-muted mb-3"></i>
                    <h4>Configure os Filtros</h4>
                    <p class="text-muted">Use os filtros à esquerda para encontrar imóveis com características específicas</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Toggle entre visualizações
function toggleView() {
    const grid = document.getElementById('imoveisGrid');
    const icon = document.getElementById('viewIcon');
    
    if (grid && grid.classList.contains('row')) {
        grid.classList.remove('row');
        grid.classList.add('row-cols-1');
        icon.classList.remove('fa-th');
        icon.classList.add('fa-list');
    } else if (grid) {
        grid.classList.remove('row-cols-1');
        grid.classList.add('row');
        icon.classList.remove('fa-list');
        icon.classList.add('fa-th');
    }
}

// Limpar filtros
function limparFiltros() {
    document.getElementById('filtrosAvancadosForm').reset();
    window.location.href = '<?= getPagePath('filtros-avancados') ?>';
}

// Salvar filtros
function salvarFiltros() {
    const form = document.getElementById('filtrosAvancadosForm');
    const formData = new FormData(form);
    
    // Salvar no localStorage
    const filtros = {};
    for (let [key, value] of formData.entries()) {
        if (key === 'caracteristicas[]') {
            if (!filtros[key]) filtros[key] = [];
            filtros[key].push(value);
        } else {
            filtros[key] = value;
        }
    }
    
    localStorage.setItem('filtrosSalvos', JSON.stringify(filtros));
    mostrarNotificacao('Filtros salvos com sucesso!', 'success');
}

// Carregar filtros salvos
document.addEventListener('DOMContentLoaded', function() {
    const filtrosSalvos = localStorage.getItem('filtrosSalvos');
    if (filtrosSalvos) {
        const filtros = JSON.parse(filtrosSalvos);
        const form = document.getElementById('filtrosAvancadosForm');
        
        // Aplicar filtros salvos
        Object.keys(filtros).forEach(key => {
            const element = form.querySelector(`[name="${key}"]`);
            if (element) {
                if (key === 'caracteristicas[]') {
                    filtros[key].forEach(value => {
                        const checkbox = form.querySelector(`[name="${key}"][value="${value}"]`);
                        if (checkbox) checkbox.checked = true;
                    });
                } else {
                    element.value = filtros[key];
                }
            }
        });
    }
});

// Exportar resultados
function exportarResultados() {
    mostrarNotificacao('Funcionalidade de exportação em desenvolvimento', 'info');
}

// Adicionar ao comparador
function adicionarAoComparador(imovelId) {
    let comparador = JSON.parse(localStorage.getItem('comparador') || '[]');
    
    if (comparador.includes(imovelId)) {
        mostrarNotificacao('Imóvel já está no comparador', 'warning');
        return;
    }
    
    if (comparador.length >= 4) {
        mostrarNotificacao('Máximo de 4 imóveis no comparador. Remova um para adicionar outro.', 'warning');
        return;
    }
    
    comparador.push(imovelId);
    localStorage.setItem('comparador', JSON.stringify(comparador));
    mostrarNotificacao('Imóvel adicionado ao comparador', 'success');
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


