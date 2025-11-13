<?php
// Endpoint AJAX para buscar imóveis
header('Content-Type: application/json');

require_once 'config/database.php';
require_once 'config/tenant.php';
require_once 'config/config.php';
require_once 'config/paths.php';

try {
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

    $contactName = SITE_NAME ?: 'Especialista';
    $contactPhoneDigits = PHONE_VENDA ? preg_replace('/\D+/', '', PHONE_VENDA) : '';

    // Construir query SQL
    $sql = "SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro, l.estado,
                   CONCAT('imoveis/', i.id, '/', (SELECT arquivo FROM fotos_imovel WHERE imovel_id = i.id AND tenant_id = i.tenant_id ORDER BY ordem ASC LIMIT 1)) as foto_principal
            FROM imoveis i 
            INNER JOIN tipos_imovel t ON i.tipo_id = t.id 
            INNER JOIN localizacoes l ON i.localizacao_id = l.id 
            WHERE i.tenant_id = ?";

    $params = [TENANT_ID];

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
    $imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Gerar HTML dos cards
    $html = '';
    if (empty($imoveis)) {
        $html = '
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
        </div>';
    } else {
        $html = '<div class="row" id="imoveisFiltrados">';
        foreach ($imoveis as $imovel) {
            $image_url = '';
            if ($imovel['foto_principal']) {
                $image_url = getUploadPath($imovel['foto_principal']);
            }
            
            $html .= '
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100 shadow-sm hover-shadow">
                    <div class="position-relative">';
            
            if ($image_url) {
                $html .= '
                        <img src="' . htmlspecialchars($image_url) . '" 
                             class="card-img-top" alt="' . htmlspecialchars($imovel['titulo']) . '"
                             style="height: 200px; object-fit: cover;">';
            } else {
                $html .= '
                        <div class="no-image-placeholder d-flex align-items-center justify-content-center" 
                             style="height: 200px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px 8px 0 0;">
                            <div class="text-center">
                                <i class="fas fa-home fa-3x text-muted mb-2"></i>
                                <p class="text-muted small mb-0">Foto não disponível</p>
                            </div>
                        </div>';
            }
            
            $html .= '
                        <!-- Preços e Tipo de Negócio -->
                        <div class="property-price position-absolute top-0 end-0 m-2">';
            
            if ($imovel['tipo_negocio'] == 'venda' || $imovel['tipo_negocio'] == 'venda_locacao') {
                $html .= '<span class="badge bg-primary fs-6 mb-1 d-block">' . formatPrice($imovel['preco']) . '</span>';
            }
            
            if ($imovel['tipo_negocio'] == 'locacao' || $imovel['tipo_negocio'] == 'venda_locacao') {
                $html .= '<span class="badge bg-success fs-6 mb-1 d-block">' . formatPrice($imovel['preco_locacao']) . '/mês</span>';
            }
            
            $html .= '
                        </div>
                        
                        <!-- Badges do Tipo de Negócio -->
                        <div class="position-absolute top-0 start-0 m-2">';
            
            if ($imovel['tipo_negocio'] == 'venda_locacao') {
                $html .= '<span class="badge bg-primary me-1">Venda</span><span class="badge bg-success">Locação</span>';
            } elseif ($imovel['tipo_negocio'] == 'venda') {
                $html .= '<span class="badge bg-primary">Venda</span>';
            } elseif ($imovel['tipo_negocio'] == 'locacao') {
                $html .= '<span class="badge bg-success">Locação</span>';
            }
            
            $html .= '
                        </div>
                    </div>
                    
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">' . htmlspecialchars($imovel['titulo']) . '</h5>
                        <p class="card-text text-muted small mb-2">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            ' . htmlspecialchars($imovel['cidade']);
            
            if ($imovel['bairro']) {
                $html .= ' - ' . htmlspecialchars($imovel['bairro']);
            }
            
            $html .= '
                        </p>
                        
                        <div class="property-details mb-3">
                            <div class="row text-center">';
            
            if ($imovel['quartos'] > 0) {
                $html .= '
                                <div class="col-4">
                                    <small class="text-muted d-block">Quartos</small>
                                    <strong>' . $imovel['quartos'] . '</strong>
                                </div>';
            }
            
            if ($imovel['banheiros'] > 0) {
                $html .= '
                                <div class="col-4">
                                    <small class="text-muted d-block">Banheiros</small>
                                    <strong>' . $imovel['banheiros'] . '</strong>
                                </div>';
            }
            
            if ($imovel['vagas'] > 0) {
                $html .= '
                                <div class="col-4">
                                    <small class="text-muted d-block">Vagas</small>
                                    <strong>' . $imovel['vagas'] . '</strong>
                                </div>';
            }
            
            $html .= '
                            </div>
                        </div>
                        
                        <div class="mt-auto">
                            <div class="d-grid gap-2">
                                <a href="' . getPagePath('imovel', ['id' => $imovel['id']]) . '" 
                                   class="btn btn-primary">
                                    <i class="fas fa-eye me-1"></i> Ver Detalhes
                                </a>
                                ';

            if (!empty($contactPhoneDigits)) {
                $html .= '
                                <button class="btn btn-outline-success btn-sm"
                                        onclick="TemplateSite.openWhatsApp(\'' . $contactPhoneDigits . '\', \'' . addslashes($contactName) . '\')">
                                    <i class="fas fa-phone"></i> Falar com um Especialista
                                </button>';
            } else {
                $html .= '
                                <span class="text-muted small text-center d-block">Configure o telefone de contato no painel.</span>';
            }

            $html .= '
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }
        $html .= '</div>';
    }

    // Retornar resposta JSON
    echo json_encode([
        'success' => true,
        'count' => count($imoveis),
        'html' => $html
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
