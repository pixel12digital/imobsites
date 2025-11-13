<?php
// Página Home - imobsites

// Processar filtros se foram enviados
$filtros_aplicados = false;
$imoveis_filtrados = [];

// Sempre carregar imóveis (com ou sem filtros)
$carregar_imoveis = true;
$contactName = SITE_NAME ?: 'Especialista';
$contactPhoneDigits = PHONE_VENDA ? preg_replace('/\D+/', '', PHONE_VENDA) : '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $filtros_aplicados = true;
    
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
        if ($tipo_negocio === 'locacao') {
            // Para locação, incluir tanto 'locacao' quanto 'venda_locacao'
            $sql .= " AND (i.tipo_negocio = ? OR i.tipo_negocio = 'venda_locacao')";
            $params[] = $tipo_negocio;
        } elseif ($tipo_negocio === 'venda') {
            // Para venda, incluir tanto 'venda' quanto 'venda_locacao'
            $sql .= " AND (i.tipo_negocio = ? OR i.tipo_negocio = 'venda_locacao')";
            $params[] = $tipo_negocio;
        } else {
            // Para outros tipos, usar filtro exato
            $sql .= " AND i.tipo_negocio = ?";
            $params[] = $tipo_negocio;
        }
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
    $imoveis_filtrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Sem filtros aplicados, carregar imóveis padrão (mais recentes)
    $sql = "SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro, l.estado,
                   CONCAT('imoveis/', i.id, '/', (SELECT arquivo FROM fotos_imovel WHERE imovel_id = i.id ORDER BY ordem ASC LIMIT 1)) as foto_principal
            FROM imoveis i 
            INNER JOIN tipos_imovel t ON i.tipo_id = t.id 
            INNER JOIN localizacoes l ON i.localizacao_id = l.id 
            WHERE i.status = 'disponivel'
            ORDER BY i.data_criacao DESC
            LIMIT 6";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $imoveis_filtrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- Hero Section -->
<section class="hero-section bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Personalize o destaque da sua imobiliária</h1>
                <p class="lead mb-4">Utilize esta área para comunicar o principal diferencial do seu negócio. Edite os textos e conecte seus serviços ao ativar o tenant.</p>
                <a href="<?php echo getPagePath('imoveis'); ?>" class="btn btn-light btn-lg px-4">Ver catálogo de imóveis</a>
            </div>
            <div class="col-lg-6">
                <div class="hero-image text-center">
                    <i class="fas fa-home fa-10x text-white-50"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Filtros Rápidos em Destaque -->
<section class="quick-filters-section py-5 bg-white">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-4">Busca Rápida</h2>
                <p class="text-center text-muted mb-5">Encontre seu imóvel ideal com nossos filtros inteligentes</p>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4">
                        <form id="quickSearchForm" method="GET">
                            <input type="hidden" name="destaque" id="destaque" value="">
                            <div class="row g-3">
                                <!-- Tipo de Negócio -->
                                <div class="col-md-3">
                                    <label for="tipo_negocio" class="form-label fw-bold">
                                        <i class="fas fa-tag me-2"></i>Tipo de Negócio
                                    </label>
                                    <select class="form-select" id="tipo_negocio" name="tipo_negocio">
                                        <option value="">Todos</option>
                                        <option value="venda">Venda</option>
                                        <option value="locacao">Locação</option>
                                        <option value="venda_locacao">Venda + Locação</option>
                                    </select>
                                </div>
                                
                                <!-- Tipo de Imóvel -->
                                <div class="col-md-3">
                                    <label for="tipo_imovel" class="form-label fw-bold">
                                        <i class="fas fa-home me-2"></i>Tipo de Imóvel
                                    </label>
                                    <select class="form-select" id="tipo_imovel" name="tipo_imovel">
                                        <option value="">Todos</option>
                                        <?php
                                        $tipos = fetchAll("SELECT id, nome FROM tipos_imovel ORDER BY nome");
                                        if ($tipos) {
                                            foreach ($tipos as $tipo) {
                                                echo '<option value="' . $tipo['id'] . '">' . htmlspecialchars($tipo['nome']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Cidade -->
                                <div class="col-md-3">
                                    <label for="cidade" class="form-label fw-bold">
                                        <i class="fas fa-map-marker-alt me-2"></i>Cidade
                                    </label>
                                    <select class="form-select" id="cidade" name="cidade">
                                        <option value="">Todas</option>
                                        <?php
                                        $cidades = fetchAll("SELECT DISTINCT cidade FROM localizacoes ORDER BY cidade");
                                        if ($cidades) {
                                            foreach ($cidades as $cidade) {
                                                echo '<option value="' . htmlspecialchars($cidade['cidade']) . '">' . htmlspecialchars($cidade['cidade']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Preço -->
                                <div class="col-md-3">
                                    <label for="preco_max" class="form-label fw-bold">
                                        <i class="fas fa-dollar-sign me-2"></i>Preço Máximo
                                    </label>
                                    <select class="form-select" id="preco_max" name="preco_max">
                                        <option value="">Qualquer</option>
                                        <option value="100000">Até R$ 100.000</option>
                                        <option value="200000">Até R$ 200.000</option>
                                        <option value="300000">Até R$ 300.000</option>
                                        <option value="500000">Até R$ 500.000</option>
                                        <option value="750000">Até R$ 750.000</option>
                                        <option value="1000000">Até R$ 1.000.000</option>
                                        <option value="1500000">Até R$ 1.500.000</option>
                                        <option value="2000000">Até R$ 2.000.000</option>
                                        <option value="5000000">Até R$ 5.000.000</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row g-3 mt-3">
                                <!-- Quartos -->
                                <div class="col-md-2">
                                    <label for="quartos" class="form-label fw-bold">
                                        <i class="fas fa-bed me-2"></i>Quartos
                                    </label>
                                    <select class="form-select" id="quartos" name="quartos">
                                        <option value="">Qualquer</option>
                                        <option value="1">1+</option>
                                        <option value="2">2+</option>
                                        <option value="3">3+</option>
                                        <option value="4">4+</option>
                                        <option value="5">5+</option>
                                    </select>
                                </div>
                                
                                <!-- Banheiros -->
                                <div class="col-md-2">
                                    <label for="banheiros" class="form-label fw-bold">
                                        <i class="fas fa-bath me-2"></i>Banheiros
                                    </label>
                                    <select class="form-select" id="banheiros" name="banheiros">
                                        <option value="">Qualquer</option>
                                        <option value="1">1+</option>
                                        <option value="2">2+</option>
                                        <option value="3">3+</option>
                                        <option value="4">4+</option>
                                    </select>
                                </div>
                                
                                <!-- Vagas -->
                                <div class="col-md-2">
                                    <label for="vagas" class="form-label fw-bold">
                                        <i class="fas fa-car me-2"></i>Vagas
                                    </label>
                                    <select class="form-select" id="vagas" name="vagas">
                                        <option value="">Qualquer</option>
                                        <option value="1">1+</option>
                                        <option value="2">2+</option>
                                        <option value="3">3+</option>
                                        <option value="4">4+</option>
                                        <option value="5">5+</option>
                                    </select>
                                </div>
                                
                                <!-- Área Mínima -->
                                <div class="col-md-3">
                                    <label for="area_min" class="form-label fw-bold">
                                        <i class="fas fa-ruler-combined me-2"></i>Área Mínima (m²)
                                    </label>
                                    <select class="form-select" id="area_min" name="area_min">
                                        <option value="">Qualquer</option>
                                        <option value="50">50m²+</option>
                                        <option value="80">80m²+</option>
                                        <option value="100">100m²+</option>
                                        <option value="150">150m²+</option>
                                        <option value="200">200m²+</option>
                                        <option value="300">300m²+</option>
                                        <option value="500">500m²+</option>
                                    </select>
                                </div>
                                
                                <!-- Ordenação -->
                                <div class="col-md-3">
                                    <label for="ordenacao" class="form-label fw-bold">
                                        <i class="fas fa-sort me-2"></i>Ordenar por
                                    </label>
                                    <select class="form-select" id="ordenacao" name="ordenacao">
                                        <option value="recentes">Mais Recentes</option>
                                        <option value="preco_menor">Menor Preço</option>
                                        <option value="preco_maior">Maior Preço</option>
                                        <option value="area_maior">Maior Área</option>
                                        <option value="destaque">Em Destaque</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Botões de Ação -->
                            <div class="row mt-4">
                                <div class="col-12 text-center">
                                    <button type="submit" class="btn btn-primary btn-lg px-5 me-3">
                                        <i class="fas fa-search me-2"></i>Buscar Imóveis
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-lg px-4" onclick="limparFiltros()">
                                        <i class="fas fa-times me-2"></i>Limpar Filtros
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Filtros Rápidos -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="d-flex flex-wrap justify-content-center gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="aplicarFiltroRapido('destaque', '1')">
                                            <i class="fas fa-star me-1"></i>Em Destaque
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="aplicarFiltroRapido('tipo_negocio', 'venda')">
                                            <i class="fas fa-tag me-1"></i>Para Venda
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="aplicarFiltroRapido('tipo_negocio', 'locacao')">
                                            <i class="fas fa-key me-1"></i>Para Alugar
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="aplicarFiltroRapido('preco_max', '300000')">
                                            <i class="fas fa-dollar-sign me-1"></i>Até R$ 300k
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="aplicarFiltroRapido('quartos', '3')">
                                            <i class="fas fa-bed me-1"></i>3+ Quartos
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="aplicarFiltroRapido('vagas', '2')">
                                            <i class="fas fa-car me-1"></i>2+ Vagas
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- Resultados dos Filtros -->
<section class="filter-results py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0"><?php echo $filtros_aplicados ? 'Resultados da Busca' : 'Imóveis Disponíveis'; ?></h2>
                    <span class="badge bg-primary fs-6"><?= count($imoveis_filtrados) ?> imóveis encontrados</span>
                </div>
            </div>
        </div>
        
        <?php if (empty($imoveis_filtrados)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>Nenhum imóvel encontrado</h4>
                        <p class="text-muted">Tente ajustar os filtros ou limpar todos os filtros</p>
                        <button class="btn btn-outline-primary" onclick="limparFiltros()">
                            <i class="fas fa-times"></i> Limpar Filtros
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row" id="imoveisFiltrados">
                <?php foreach ($imoveis_filtrados as $imovel): ?>
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
                                
                                <!-- Preços e Tipo de Negócio -->
                                <div class="property-price position-absolute top-0 end-0 m-2">
                                    <?php if ($imovel['tipo_negocio'] == 'venda' || $imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                        <span class="badge bg-primary fs-6 mb-1 d-block"><?php echo formatPrice($imovel['preco']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($imovel['tipo_negocio'] == 'locacao' || $imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                        <span class="badge bg-success fs-6 mb-1 d-block"><?php echo formatPrice($imovel['preco_locacao']); ?>/mês</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Badges do Tipo de Negócio -->
                                <div class="position-absolute top-0 start-0 m-2">
                                    <?php if ($imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                        <span class="badge bg-primary me-1">Venda</span>
                                        <span class="badge bg-success">Locação</span>
                                    <?php elseif ($imovel['tipo_negocio'] == 'venda'): ?>
                                        <span class="badge bg-primary">Venda</span>
                                    <?php elseif ($imovel['tipo_negocio'] == 'locacao'): ?>
                                        <span class="badge bg-success">Locação</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($imovel['titulo']); ?></h5>
                                <p class="card-text text-muted small mb-2">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($imovel['cidade']); ?>
                                    <?php if ($imovel['bairro']): ?>
                                        - <?php echo htmlspecialchars($imovel['bairro']); ?>
                                    <?php endif; ?>
                                </p>
                                
                                <div class="property-details mb-3">
                                    <div class="row text-center">
                                        <?php if ($imovel['quartos'] > 0): ?>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Quartos</small>
                                                <strong><?php echo $imovel['quartos']; ?></strong>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($imovel['banheiros'] > 0): ?>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Banheiros</small>
                                                <strong><?php echo $imovel['banheiros']; ?></strong>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($imovel['vagas'] > 0): ?>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Vagas</small>
                                                <strong><?php echo $imovel['vagas']; ?></strong>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mt-auto">
                                    <div class="d-grid gap-2">
                                        <a href="<?php echo getPagePath('imovel', ['id' => $imovel['id']]); ?>" 
                                           class="btn btn-primary">
                                            <i class="fas fa-eye me-1"></i> Ver Detalhes
                                        </a>
                                        <?php if (!empty($contactPhoneDigits)): ?>
                                            <button class="btn btn-outline-success btn-sm"
                                                    onclick="TemplateSite.openWhatsApp('<?php echo $contactPhoneDigits; ?>', '<?php echo htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8'); ?>')">
                                                <i class="fas fa-phone"></i> Falar com um Especialista
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small text-center d-block">Configure o telefone de contato no painel.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Imóveis em Destaque -->
<?php if (!$filtros_aplicados): ?>
<section class="featured-properties py-5">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-5">Imóveis em Destaque</h2>
            </div>
        </div>
        
        <div class="row">
            <?php
            // Buscar imóveis em destaque
            $featured_properties = fetchAll("
                SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro, 
                       CONCAT('imoveis/', i.id, '/', (SELECT arquivo FROM fotos_imovel WHERE imovel_id = i.id ORDER BY ordem ASC LIMIT 1)) as foto_principal
                FROM imoveis i
                LEFT JOIN tipos_imovel t ON i.tipo_id = t.id
                LEFT JOIN localizacoes l ON i.localizacao_id = l.id
                WHERE i.destaque = 1 AND i.status = 'disponivel'
                ORDER BY i.data_criacao DESC
                LIMIT 6
            ");

            if ($featured_properties) {
                foreach ($featured_properties as $property) {
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="property-card card h-100 shadow-sm">
                            <div class="property-image" style="position: relative;">
                                <?php if ($property['foto_principal']): ?>
                                    <?php 
                                    $image_url = getUploadPath($property['foto_principal']);
                                    if ($image_url): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                             class="card-img-top" alt="<?php echo htmlspecialchars($property['titulo']); ?>"
                                             style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px 8px 0 0;">
                                    <?php else: ?>
                                        <div class="no-image bg-light d-flex align-items-center justify-content-center" 
                                             style="height: 200px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px 8px 0 0;">
                                            <div class="text-center">
                                                <i class="fas fa-home fa-3x text-muted mb-2"></i>
                                                <p class="text-muted small mb-0">Foto não disponível</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="no-image bg-light d-flex align-items-center justify-content-center" 
                                         style="height: 200px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px 8px 0 0;">
                                        <div class="text-center">
                                            <i class="fas fa-home fa-3x text-muted mb-2"></i>
                                            <p class="text-muted small mb-0">Foto não disponível</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <!-- Preços e Tipo de Negócio -->
                                <div class="property-price position-absolute top-0 end-0 m-2">
                                    <?php if ($property['tipo_negocio'] == 'venda' || $property['tipo_negocio'] == 'venda_locacao'): ?>
                                        <span class="badge bg-primary fs-6 mb-1 d-block"><?php echo formatPrice($property['preco']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($property['tipo_negocio'] == 'locacao' || $property['tipo_negocio'] == 'venda_locacao'): ?>
                                        <span class="badge bg-success fs-6 mb-1 d-block"><?php echo formatPrice($property['preco_locacao']); ?>/mês</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Badges do Tipo de Negócio -->
                                <div class="position-absolute top-0 start-0 m-2">
                                    <?php if ($property['tipo_negocio'] == 'venda_locacao'): ?>
                                        <span class="badge bg-primary me-1">Venda</span>
                                        <span class="badge bg-success">Locação</span>
                                    <?php elseif ($property['tipo_negocio'] == 'locacao'): ?>
                                        <span class="badge bg-success">Locação</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Venda</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $property['titulo']; ?></h5>
                                <p class="card-text text-muted">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo $property['bairro'] . ', ' . $property['cidade']; ?>
                                </p>
                                <div class="property-features">
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-bed me-1"></i><?php echo $property['quartos']; ?> Quartos
                                    </span>
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-bath me-1"></i><?php echo $property['banheiros']; ?> Banheiros
                                    </span>
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-car me-1"></i><?php echo $property['vagas']; ?> Vagas
                                    </span>
                                </div>
                                <div class="property-area mt-2">
                                    <small class="text-muted">
                                        Área: <?php echo $property['area_total']; ?>m²
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="<?php echo getPagePath('imovel', ['id' => $property['id']]); ?>" 
                                   class="btn btn-primary w-100">Ver Imóvel</a>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                // Imóveis de exemplo quando não há dados no banco
                $example_properties = [
                    [
                        'titulo' => 'Imóvel Residencial Exemplo',
                        'preco' => 0.00,
                        'bairro' => 'Bairro Exemplo',
                        'cidade' => 'Cidade Exemplo',
                        'quartos' => 3,
                        'banheiros' => 4,
                        'vagas' => 4,
                        'area_total' => 200
                    ],
                    [
                        'titulo' => 'Apartamento Exemplo',
                        'preco' => 0.00,
                        'bairro' => 'Bairro Exemplo',
                        'cidade' => 'Cidade Exemplo',
                        'quartos' => 2,
                        'banheiros' => 2,
                        'vagas' => 2,
                        'area_total' => 85
                    ],
                    [
                        'titulo' => 'Imóvel Comercial Exemplo',
                        'preco' => 0.00,
                        'bairro' => 'Bairro Exemplo',
                        'cidade' => 'Cidade Exemplo',
                        'quartos' => 3,
                        'banheiros' => 3,
                        'vagas' => 5,
                        'area_total' => 235
                    ]
                ];

                foreach ($example_properties as $property) {
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="property-card card h-100 shadow-sm">
                            <div class="property-image">
                                <div class="no-image bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 200px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px 8px 0 0;">
                                    <div class="text-center">
                                        <i class="fas fa-home fa-3x text-muted mb-2"></i>
                                        <p class="text-muted small mb-0">Foto não disponível</p>
                                    </div>
                                </div>
                                <div class="property-price">
                                    <span class="badge bg-primary fs-6"><?php echo formatPrice($property['preco']); ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $property['titulo']; ?></h5>
                                <p class="card-text text-muted">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo $property['bairro'] . ', ' . $property['cidade']; ?>
                                </p>
                                <div class="property-features">
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-bed me-1"></i><?php echo $property['quartos']; ?> Quartos
                                    </span>
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-bath me-1"></i><?php echo $property['banheiros']; ?> Banheiros
                                    </span>
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-car me-1"></i><?php echo $property['vagas']; ?> Vagas
                                    </span>
                                </div>
                                <div class="property-area mt-2">
                                    <small class="text-muted">
                                        Área: <?php echo $property['area_total']; ?>m²
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="#" class="btn btn-primary w-100">Ver Imóvel</a>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="<?php echo getPagePath('imoveis'); ?>" class="btn btn-outline-primary btn-lg">Ver Todos os Imóveis</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Seção de Serviços -->
<section class="services-section py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-5">Nossos Serviços</h2>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="service-card text-center p-4">
                    <div class="service-icon mb-3">
                        <i class="fas fa-search fa-3x text-primary"></i>
                    </div>
                    <h5>Busca Personalizada</h5>
                    <p class="text-muted">Encontre o imóvel ideal com nossos filtros avançados e busca inteligente.</p>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="service-card text-center p-4">
                    <div class="service-icon mb-3">
                        <i class="fas fa-handshake fa-3x text-primary"></i>
                    </div>
                    <h5>Assessoria Completa</h5>
                    <p class="text-muted">Acompanhamento em todo o processo de compra, venda ou aluguel.</p>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="service-card text-center p-4">
                    <div class="service-icon mb-3">
                        <i class="fas fa-calculator fa-3x text-primary"></i>
                    </div>
                    <h5>Simulação de Financiamento</h5>
                    <p class="text-muted">Simule seu financiamento e encontre as melhores condições.</p>
                </div>
            </div>
        </div>
    </div>
</section>


