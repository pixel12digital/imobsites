<?php
// Página de Imóveis - imobsites

// Página de Listagem de Imóveis com Filtros Avançados
$page_title = 'Imóveis Disponíveis' . (SITE_NAME ? ' - ' . SITE_NAME : '');
$contactName = SITE_NAME ?: 'Especialista';
$contactPhoneDigits = PHONE_VENDA ? preg_replace('/\D+/', '', PHONE_VENDA) : '';

// Processar filtros
$tipo_id = isset($_GET['tipo_imovel']) ? (int)$_GET['tipo_imovel'] : (isset($_GET['tipo']) ? (int)$_GET['tipo'] : 0);
$preco_min = isset($_GET['preco_min']) ? (float)$_GET['preco_min'] : 0;
$preco_max = isset($_GET['preco_max']) ? (float)$_GET['preco_max'] : 0;
$preco_locacao_min = isset($_GET['preco_locacao_min']) ? (float)$_GET['preco_locacao_min'] : 0;
$preco_locacao_max = isset($_GET['preco_locacao_max']) ? (float)$_GET['preco_locacao_max'] : 0;
$area_min = isset($_GET['area_min']) ? (float)$_GET['area_min'] : 0;
$area_max = isset($_GET['area_max']) ? (float)$_GET['area_max'] : 0;
$quartos = isset($_GET['quartos']) ? (int)$_GET['quartos'] : 0;
$banheiros = isset($_GET['banheiros']) ? (int)$_GET['banheiros'] : 0;
$vagas = isset($_GET['vagas']) ? (int)$_GET['vagas'] : 0;
$cidade = isset($_GET['cidade']) ? cleanInput($_GET['cidade']) : '';
$bairro = isset($_GET['bairro']) ? cleanInput($_GET['bairro']) : '';
$status = isset($_GET['status']) ? cleanInput($_GET['status']) : 'disponivel';
$caracteristicas = isset($_GET['caracteristicas']) ? $_GET['caracteristicas'] : [];
$tipo_negocio = isset($_GET['tipo_negocio']) ? cleanInput($_GET['tipo_negocio']) : '';
$ordenacao = isset($_GET['ordenacao']) ? cleanInput($_GET['ordenacao']) : 'recentes';
$destaque = isset($_GET['destaque']) ? (bool)$_GET['destaque'] : false;
$busca = isset($_GET['busca']) ? cleanInput($_GET['busca']) : '';

// Construir query SQL
$sql = "SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro, l.estado,
               CONCAT('imoveis/', i.id, '/', (SELECT arquivo FROM fotos_imovel WHERE imovel_id = i.id ORDER BY ordem ASC LIMIT 1)) as foto_principal
        FROM imoveis i 
        INNER JOIN tipos_imovel t ON i.tipo_id = t.id 
        INNER JOIN localizacoes l ON i.localizacao_id = l.id 
        WHERE 1=1";

$params = [];

// Adicionar busca geral
if (!empty($busca)) {
    $sql .= " AND (
        i.titulo LIKE ? OR 
        i.descricao LIKE ? OR 
        l.cidade LIKE ? OR 
        l.bairro LIKE ? OR 
        t.nome LIKE ?
    )";
    $searchTerm = "%{$busca}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($tipo_id > 0) {
    $sql .= " AND i.tipo_id = ?";
    $params[] = $tipo_id;
}

if ($preco_min > 0) {
    $sql .= " AND i.preco >= ?";
    $params[] = $preco_min;
}

if ($preco_max > 0) {
    $sql .= " AND i.preco <= ?";
    $params[] = $preco_max;
}

// Filtros de preço de locação
if ($preco_locacao_min > 0) {
    $sql .= " AND i.preco_locacao >= ?";
    $params[] = $preco_locacao_min;
}

if ($preco_locacao_max > 0) {
    $sql .= " AND i.preco_locacao <= ?";
    $params[] = $preco_locacao_max;
}

if ($area_min > 0) {
    $sql .= " AND i.area_total >= ?";
    $params[] = $area_min;
}

if ($area_max > 0) {
    $sql .= " AND i.area_max <= ?";
    $params[] = $area_max;
}

if ($quartos > 0) {
    $sql .= " AND i.quartos >= ?";
    $params[] = $quartos;
}

if ($banheiros > 0) {
    $sql .= " AND i.banheiros >= ?";
    $params[] = $banheiros;
}

if ($vagas > 0) {
    $sql .= " AND i.vagas >= ?";
    $params[] = $vagas;
}

if (!empty($cidade)) {
    $sql .= " AND l.cidade LIKE ?";
    $params[] = "%$cidade%";
}

if (!empty($bairro)) {
    $sql .= " AND l.bairro LIKE ?";
    $params[] = "%$bairro%";
}

if (!empty($status)) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

if (!empty($tipo_negocio)) {
    $sql .= " AND i.tipo_negocio = ?";
    $params[] = $tipo_negocio;
}

if ($destaque) {
    $sql .= " AND i.destaque = 1";
}

// Adicionar ordenação
switch ($ordenacao) {
    case 'preco_menor':
        $sql .= " ORDER BY i.preco ASC";
        break;
    case 'preco_maior':
        $sql .= " ORDER BY i.preco DESC";
        break;
    case 'area_maior':
        $sql .= " ORDER BY i.area_total DESC";
        break;
    case 'destaque':
        $sql .= " ORDER BY i.destaque DESC, i.data_criacao DESC";
        break;
    case 'recentes':
    default:
        $sql .= " ORDER BY i.data_criacao DESC";
        break;
}

// Executar query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar dados para filtros
$tipos = $pdo->query("SELECT * FROM tipos_imovel WHERE ativo = 1")->fetchAll();
$cidades = $pdo->query("SELECT DISTINCT cidade FROM localizacoes ORDER BY cidade")->fetchAll();
$caracteristicas_list = $pdo->query("SELECT * FROM caracteristicas WHERE ativo = 1 ORDER BY categoria, nome")->fetchAll();
?>

<div class="container-fluid py-5">
    <div class="row">
        <!-- Sidebar de Filtros -->
        <div class="col-lg-3">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros Avançados</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" id="filtrosForm">
                        <input type="hidden" name="page" value="imoveis">
                        
                        <!-- Tipo de Negócio -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de Negócio</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_negocio" value="" id="todos" <?= empty($tipo_negocio) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="todos">Todos</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_negocio" value="venda" id="venda" <?= $tipo_negocio === 'venda' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="venda">Venda</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_negocio" value="locacao" id="locacao" <?= $tipo_negocio === 'locacao' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="locacao">Locação</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_negocio" value="venda_locacao" id="venda_locacao" <?= $tipo_negocio === 'venda_locacao' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="venda_locacao">Venda + Locação</label>
                            </div>
                        </div>

                        <!-- Tipo de Imóvel -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de Imóvel</label>
                            <select name="tipo_imovel" class="form-select">
                                <option value="">Todos os tipos</option>
                                <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?= $tipo['id'] ?>" <?= $tipo_id == $tipo['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Faixa de Preço -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Faixa de Preço (Venda)</label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="number" name="preco_min" class="form-control" placeholder="Min" value="<?= $preco_min ?>" min="0" step="1000">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="preco_max" class="form-control" placeholder="Max" value="<?= $preco_max ?>" min="0" step="1000">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Faixa de Preço de Locação -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Faixa de Preço (Locação)</label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="number" name="preco_locacao_min" class="form-control" placeholder="Min" value="<?= isset($_GET['preco_locacao_min']) ? (float)$_GET['preco_locacao_min'] : '' ?>" min="0" step="100">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="preco_locacao_max" class="form-control" placeholder="Max" value="<?= isset($_GET['preco_locacao_max']) ? (float)$_GET['preco_locacao_max'] : '' ?>" min="0" step="100">
                                </div>
                            </div>
                        </div>

                        <!-- Faixa de Área -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Área (m²)</label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="number" name="area_min" class="form-control" placeholder="Min" value="<?= $area_min ?>" min="0" step="10">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="area_max" class="form-control" placeholder="Max" value="<?= $area_max ?>" min="0" step="10">
                                </div>
                            </div>
                        </div>

                        <!-- Quartos, Banheiros, Vagas -->
                        <div class="row mb-3">
                            <div class="col-4">
                                <label class="form-label fw-bold">Quartos</label>
                                <select name="quartos" class="form-select">
                                    <option value="">Qualquer</option>
                                    <option value="1" <?= $quartos == 1 ? 'selected' : '' ?>>1+</option>
                                    <option value="2" <?= $quartos == 2 ? 'selected' : '' ?>>2+</option>
                                    <option value="3" <?= $quartos == 3 ? 'selected' : '' ?>>3+</option>
                                    <option value="4" <?= $quartos == 4 ? 'selected' : '' ?>>4+</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold">Banheiros</label>
                                <select name="banheiros" class="form-select">
                                    <option value="">Qualquer</option>
                                    <option value="1" <?= $banheiros == 1 ? 'selected' : '' ?>>1+</option>
                                    <option value="2" <?= $banheiros == 2 ? 'selected' : '' ?>>2+</option>
                                    <option value="3" <?= $banheiros == 3 ? 'selected' : '' ?>>3+</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold">Vagas</label>
                                <select name="vagas" class="form-select">
                                    <option value="">Qualquer</option>
                                    <option value="1" <?= $vagas == 1 ? 'selected' : '' ?>>1+</option>
                                    <option value="2" <?= $vagas == 2 ? 'selected' : '' ?>>2+</option>
                                    <option value="3" <?= $vagas == 3 ? 'selected' : '' ?>>3+</option>
                                </select>
                            </div>
                        </div>

                        <!-- Localização -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Cidade</label>
                            <select name="cidade" class="form-select" id="cidadeSelect">
                                <option value="">Todas as cidades</option>
                                <?php foreach ($cidades as $c): ?>
                                    <option value="<?= htmlspecialchars($c['cidade']) ?>" <?= $cidade === $c['cidade'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['cidade']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Bairro</label>
                            <select name="bairro" class="form-select" id="bairroSelect">
                                <option value="">Todos os bairros</option>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="disponivel" <?= $status === 'disponivel' ? 'selected' : '' ?>>Disponível</option>
                                <option value="vendido" <?= $status === 'vendido' ? 'selected' : '' ?>>Vendido</option>
                                <option value="alugado" <?= $status === 'alugado' ? 'selected' : '' ?>>Alugado</option>
                                <option value="reservado" <?= $status === 'reservado' ? 'selected' : '' ?>>Reservado</option>
                            </select>
                        </div>

                        <!-- Destaque -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="destaque" value="1" id="destaque" <?= $destaque ? 'checked' : '' ?>>
                                <label class="form-check-label" for="destaque">
                                    Apenas em destaque
                                </label>
                            </div>
                        </div>

                        <!-- Ordenação -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ordenar por</label>
                            <select name="ordenacao" class="form-select">
                                <option value="recentes" <?= $ordenacao === 'recentes' ? 'selected' : '' ?>>Mais recentes</option>
                                <option value="preco_menor" <?= $ordenacao === 'preco_menor' ? 'selected' : '' ?>>Menor preço</option>
                                <option value="preco_maior" <?= $ordenacao === 'preco_maior' ? 'selected' : '' ?>>Maior preço</option>
                                <option value="area_maior" <?= $ordenacao === 'area_maior' ? 'selected' : '' ?>>Maior área</option>
                                <option value="destaque" <?= $ordenacao === 'destaque' ? 'selected' : '' ?>>Destaque</option>
                            </select>
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

        <!-- Lista de Imóveis -->
        <div class="col-lg-9">
            <!-- Header da Lista -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Imóveis Disponíveis</h2>
                    <p class="text-muted mb-0"><?= count($imoveis) ?> imóveis encontrados</p>
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
            <?php
            $filtros_ativos = [];
            if ($tipo_id > 0) {
                $tipo_nome = '';
                foreach ($tipos as $tipo) {
                    if ($tipo['id'] == $tipo_id) {
                        $tipo_nome = $tipo['nome'];
                        break;
                    }
                }
                $filtros_ativos[] = "Tipo: " . $tipo_nome;
            }
            if ($preco_min > 0) $filtros_ativos[] = "Preço mínimo (Venda): " . formatPrice($preco_min);
if ($preco_max > 0) $filtros_ativos[] = "Preço máximo (Venda): " . formatPrice($preco_max);
if ($preco_locacao_min > 0) $filtros_ativos[] = "Preço mínimo (Locação): " . formatPrice($preco_locacao_min);
if ($preco_locacao_max > 0) $filtros_ativos[] = "Preço máximo (Locação): " . formatPrice($preco_locacao_max);
            if ($area_min > 0) $filtros_ativos[] = "Área mínima: " . $area_min . "m²";
            if ($area_max > 0) $filtros_ativos[] = "Área máxima: " . $area_max . "m²";
            if ($quartos > 0) $filtros_ativos[] = "Quartos: " . $quartos . "+";
            if ($banheiros > 0) $filtros_ativos[] = "Banheiros: " . $banheiros . "+";
            if ($vagas > 0) $filtros_ativos[] = "Vagas: " . $vagas . "+";
            if (!empty($cidade)) $filtros_ativos[] = "Cidade: " . $cidade;
            if (!empty($bairro)) $filtros_ativos[] = "Bairro: " . $bairro;
            if (!empty($tipo_negocio)) {
    $negocio_label = '';
    switch($tipo_negocio) {
        case 'venda':
            $negocio_label = 'Venda';
            break;
        case 'locacao':
            $negocio_label = 'Locação';
            break;
        case 'venda_locacao':
            $negocio_label = 'Venda + Locação';
            break;
        default:
            $negocio_label = ucfirst($tipo_negocio);
    }
    $filtros_ativos[] = "Negócio: " . $negocio_label;
}
            if ($destaque) $filtros_ativos[] = "Em destaque";
            if (!empty($busca)) $filtros_ativos[] = "Busca: " . $busca;
            
            if (!empty($filtros_ativos)):
            ?>
            <div class="alert alert-info mb-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2"><i class="fas fa-filter me-2"></i>Filtros Aplicados:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($filtros_ativos as $filtro): ?>
                                <span class="badge bg-primary"><?= htmlspecialchars($filtro) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <a href="?page=imoveis" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Limpar Filtros
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Resultados -->
            <?php if (empty($imoveis)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>Nenhum imóvel encontrado</h4>
                    <p class="text-muted">Tente ajustar os filtros ou <a href="?page=imoveis">limpar todos os filtros</a></p>
                </div>
            <?php else: ?>
                <div class="row" id="imoveisGrid">
                    <?php foreach ($imoveis as $imovel): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card h-100 shadow-sm hover-shadow">
                                <div class="position-relative">
                                    <?php if ($imovel['foto_principal']): ?>
                                        <?php 
                                        $image_url = getUploadPath($imovel['foto_principal']);
                                        if ($image_url): 
                                        ?>
                                            <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                                 class="card-img-top" alt="<?php echo htmlspecialchars($imovel['titulo']); ?>"
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
                                        <span class="badge bg-<?= $imovel['status'] === 'disponivel' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($imovel['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Botão Favorito -->
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <button class="btn btn-sm btn-light rounded-circle" 
                                                onclick="toggleFavorito(<?= $imovel['id'] ?>)" 
                                                id="favorito-<?= $imovel['id'] ?>">
                                            <i class="fas fa-heart text-muted"></i>
                                        </button>
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
                                    
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Área Total</small>
                                            <div class="fw-bold"><?= $imovel['area_total'] ?> m²</div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Área Construída</small>
                                            <div class="fw-bold"><?= $imovel['area_construida'] ?> m²</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-auto">
                                        <!-- Preços e Tipo de Negócio -->
                                        <div class="mb-3">
                                            <!-- Preço de Venda -->
                                            <?php if ($imovel['tipo_negocio'] == 'venda' || $imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="h5 text-primary mb-0"><?= formatPrice($imovel['preco']) ?></span>
                                                    <small class="text-muted"><?= formatDate($imovel['data_criacao']) ?></small>
                                                </div>
                                                <div class="mb-2">
                                                    <span class="badge bg-primary">Venda</span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Preço de Locação -->
                                            <?php if ($imovel['tipo_negocio'] == 'locacao' || $imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                                <?php if ($imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                                    <hr class="my-2">
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="h5 text-success mb-0"><?= formatPrice($imovel['preco_locacao']) ?>/mês</span>
                                                    <small class="text-muted"><?= formatDate($imovel['data_criacao']) ?></small>
                                                </div>
                                                <div class="mb-2">
                                                    <span class="badge bg-success">Locação</span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Badge Combinado -->
                                            <?php if ($imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                                <div class="mb-2">
                                                    <span class="badge bg-primary me-1">Venda</span>
                                                    <span class="badge bg-success">Locação</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <a href="<?= getPagePath('imovel', ['id' => $imovel['id']]) ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> Ver Detalhes
                                            </a>
                                            <?php if (!empty($contactPhoneDigits)): ?>
                                                <button class="btn btn-outline-success btn-sm" 
                                                        onclick="TemplateSite.openWhatsApp('<?php echo $contactPhoneDigits; ?>', '<?php echo htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8'); ?>')">
                                                    <i class="fas fa-phone"></i> Falar com um Especialista
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small text-center">Configure o telefone de contato no painel.</span>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-info btn-sm" 
                                                    onclick="adicionarAoComparador(<?= $imovel['id'] ?>)">
                                                <i class="fas fa-balance-scale"></i> Comparar
                                            </button>
                                            <a href="<?= getPagePath('historico-precos', ['imovel_id' => $imovel['id']]) ?>" 
                                               class="btn btn-outline-warning btn-sm">
                                                <i class="fas fa-chart-line"></i> Histórico
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Carregar favoritos salvos
document.addEventListener('DOMContentLoaded', function() {
    carregarFavoritos();
    carregarBairros();
});

// Carregar bairros baseado na cidade selecionada
function carregarBairros() {
    const cidadeSelect = document.getElementById('cidadeSelect');
    const bairroSelect = document.getElementById('bairroSelect');
    
    cidadeSelect.addEventListener('change', function() {
        const cidade = this.value;
        bairroSelect.innerHTML = '<option value="">Todos os bairros</option>';
        
        if (cidade) {
            // Aqui você faria uma requisição AJAX para buscar os bairros da cidade
            // Por enquanto, vamos simular com dados estáticos
            const bairros = ['Centro', 'Jardim', 'Vila Nova', 'Bairro Industrial'];
            bairros.forEach(bairro => {
                const option = document.createElement('option');
                option.value = bairro;
                option.textContent = bairro;
                bairroSelect.appendChild(option);
            });
        }
    });
}

// Sistema de Favoritos
function toggleFavorito(imovelId) {
    const btn = document.getElementById(`favorito-${imovelId}`);
    const icon = btn.querySelector('i');
    
    let favoritos = JSON.parse(localStorage.getItem('favoritos') || '[]');
    
    if (favoritos.includes(imovelId)) {
        // Remover dos favoritos
        favoritos = favoritos.filter(id => id !== imovelId);
        icon.classList.remove('text-danger');
        icon.classList.add('text-muted');
        mostrarNotificacao('Imóvel removido dos favoritos', 'warning');
    } else {
        // Adicionar aos favoritos
        favoritos.push(imovelId);
        icon.classList.remove('text-muted');
        icon.classList.add('text-danger');
        mostrarNotificacao('Imóvel adicionado aos favoritos', 'success');
    }
    
    localStorage.setItem('favoritos', JSON.stringify(favoritos));
}

function carregarFavoritos() {
    const favoritos = JSON.parse(localStorage.getItem('favoritos') || '[]');
    
    favoritos.forEach(imovelId => {
        const btn = document.getElementById(`favorito-${imovelId}`);
        if (btn) {
            const icon = btn.querySelector('i');
            icon.classList.remove('text-muted');
            icon.classList.add('text-danger');
        }
    });
}

// Limpar filtros
function limparFiltros() {
    window.location.href = '<?= getPagePath('imoveis') ?>';
}

// Toggle entre visualizações
function toggleView() {
    const grid = document.getElementById('imoveisGrid');
    const icon = document.getElementById('viewIcon');
    
    if (grid.classList.contains('row')) {
        grid.classList.remove('row');
        grid.classList.add('row-cols-1');
        icon.classList.remove('fa-th');
        icon.classList.add('fa-list');
    } else {
        grid.classList.remove('row-cols-1');
        grid.classList.add('row');
        icon.classList.remove('fa-list');
        icon.classList.add('fa-th');
    }
}

// Exportar resultados
function exportarResultados() {
    // Implementar exportação para CSV/PDF
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
    
    // Atualizar contador no header (se existir)
    atualizarContadorComparador();
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
    
    // Remover após 3 segundos
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>


