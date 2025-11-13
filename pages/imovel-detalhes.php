<?php
// Página de Detalhes do Imóvel - imobsites

// Verificar se foi passado um ID
$imovel_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$imovel_id) {
    // Redirecionar para página de imóveis se não houver ID
    header('Location: ' . getPagePath('imoveis'));
    exit;
}

// Buscar dados do imóvel
$imovel = fetch("
    SELECT i.*, t.nome as tipo_nome, t.descricao as tipo_descricao, 
           l.cidade, l.bairro, l.estado, l.cep, i.tipo_negocio,
           u.nome as corretor_nome, u.email as corretor_email, u.telefone as corretor_telefone
    FROM imoveis i
    LEFT JOIN tipos_imovel t ON i.tipo_id = t.id
    LEFT JOIN localizacoes l ON i.localizacao_id = l.id
    LEFT JOIN usuarios u ON i.usuario_id = u.id
    WHERE i.id = ?
", [$imovel_id]);

if (!$imovel) {
    // Redirecionar se imóvel não existir
    header('Location: ' . getPagePath('imoveis'));
    exit;
}

// Buscar fotos do imóvel
$fotos = fetchAll("
    SELECT * FROM fotos_imovel 
    WHERE imovel_id = ? 
    ORDER BY principal DESC, ordem ASC
", [$imovel_id]);

// Buscar características do imóvel
$caracteristicas = fetchAll("
    SELECT c.nome, c.categoria 
    FROM caracteristicas c
    INNER JOIN imovel_caracteristicas ic ON c.id = ic.caracteristica_id
    WHERE ic.imovel_id = ? AND c.ativo = 1
    ORDER BY c.categoria, c.nome
", [$imovel_id]);

// Buscar imóveis similares
$imoveis_similares = fetchAll("
    SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro, 
           CONCAT('imoveis/', i.id, '/', (SELECT arquivo FROM fotos_imovel WHERE imovel_id = i.id ORDER BY ordem ASC LIMIT 1)) as foto_principal
    FROM imoveis i
    LEFT JOIN tipos_imovel t ON i.tipo_id = t.id
    LEFT JOIN localizacoes l ON i.localizacao_id = l.id
    WHERE i.id != ? AND i.status = 'disponivel' 
    AND (i.tipo_id = ? OR l.cidade = ? OR i.preco BETWEEN ? AND ?)
    ORDER BY RAND()
    LIMIT 3
", [$imovel_id, $imovel['tipo_id'], $imovel['cidade'], 
     $imovel['preco'] * 0.7, $imovel['preco'] * 1.3]);

// Organizar características por categoria
$caracteristicas_por_categoria = [];
foreach ($caracteristicas as $carac) {
    $categoria = $carac['categoria'] ?: 'Geral';
    if (!isset($caracteristicas_por_categoria[$categoria])) {
        $caracteristicas_por_categoria[$categoria] = [];
    }
    $caracteristicas_por_categoria[$categoria][] = $carac['nome'];
}


?>

<!-- Hero Section -->
<section class="hero-section-small bg-primary text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo getPagePath('home'); ?>" class="text-white">Início</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo getPagePath('imoveis'); ?>" class="text-white">Imóveis</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo $imovel['titulo']; ?></li>
                    </ol>
                </nav>
                <h1 class="h2 mb-2 mt-2"><?php echo $imovel['titulo']; ?></h1>
                <p class="mb-0">
                    <i class="fas fa-map-marker-alt me-2"></i>
                    <?php echo $imovel['bairro'] . ', ' . $imovel['cidade'] . ' - ' . $imovel['estado']; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="property-status">
                    <span class="badge bg-<?php echo $imovel['status'] == 'disponivel' ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo ucfirst($imovel['status']); ?>
                    </span>
                    <?php if ($imovel['destaque']): ?>
                        <span class="badge bg-warning ms-2">Destaque</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Galeria de Fotos -->
<section class="gallery-section py-4">
    <div class="container">
        <?php if ($fotos && count($fotos) > 0): ?>
            <div class="row">
                <div class="col-12">
                    <div id="propertyGallery" class="carousel slide" data-bs-ride="carousel">
                        <!-- Indicadores -->
                        <div class="carousel-indicators">
                            <?php foreach ($fotos as $index => $foto): ?>
                                <button type="button" data-bs-target="#propertyGallery" 
                                        data-bs-slide-to="<?php echo $index; ?>" 
                                        class="<?php echo $index === 0 ? 'active' : ''; ?>"
                                        aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                                        aria-label="Slide <?php echo $index + 1; ?>">
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Slides -->
                        <div class="carousel-inner">
                            <?php foreach ($fotos as $index => $foto): ?>
                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <?php 
                                    $foto_path = 'imoveis/' . $imovel_id . '/' . $foto['arquivo'];
                                    $image_url = getUploadPath($foto_path);
                                    if ($image_url): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                             class="d-block w-100" alt="<?php echo $foto['legenda'] ?: $imovel['titulo']; ?>"
                                             style="height: 500px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="image-placeholder d-flex align-items-center justify-content-center" 
                                             style="height: 500px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                            <div class="text-center">
                                                <i class="fas fa-home fa-4x text-muted mb-3"></i>
                                                <h5 class="text-muted"><?php echo $foto['legenda'] ?: 'Foto do Imóvel'; ?></h5>
                                                <p class="text-muted small">Imagem não disponível</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($foto['legenda']): ?>
                                        <div class="carousel-caption d-none d-md-block">
                                            <h5><?php echo $foto['legenda']; ?></h5>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Controles -->
                        <button class="carousel-control-prev" type="button" data-bs-target="#propertyGallery" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Anterior</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#propertyGallery" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Próximo</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Miniaturas com Barra de Rolagem -->
            <div class="row mt-4">
                <div class="col-12">
                    <h6 class="text-muted mb-3">
                        <i class="fas fa-images me-2"></i>
                        Galeria de Fotos (<?php echo count($fotos); ?> imagens)
                    </h6>
                    <div class="thumbnails-container">
                        <div class="thumbnails-scroll">
                            <?php foreach ($fotos as $index => $foto): ?>
                                <div class="thumbnail-item" onclick="openLightbox(<?php echo $index; ?>)">
                                    <?php 
                                    $foto_path = 'imoveis/' . $imovel_id . '/' . $foto['arquivo'];
                                    $image_url = getUploadPath($foto_path);
                                    if ($image_url): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                             class="thumbnail-img" alt="Miniatura <?php echo $index + 1; ?>">
                                    <?php else: ?>
                                        <div class="thumbnail-placeholder">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="thumbnail-overlay">
                                        <span class="thumbnail-number"><?php echo $index + 1; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="scroll-indicator">
                            <i class="fas fa-arrows-alt-h text-muted"></i>
                            <small class="text-muted ms-2">Arraste para ver mais fotos</small>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Imagem padrão se não houver fotos -->
            <div class="row">
                <div class="col-12">
                    <div class="no-image-placeholder bg-light d-flex align-items-center justify-content-center" 
                         style="height: 400px; border-radius: 12px; border: 2px dashed #dee2e6;">
                        <div class="text-center">
                            <i class="fas fa-home fa-5x text-muted mb-3"></i>
                            <h5 class="text-muted">Nenhuma foto disponível</h5>
                            <p class="text-muted small">Este imóvel ainda não possui fotos cadastradas</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Informações Principais -->
<section class="property-info py-5">
    <div class="container">
        <div class="row">
            <!-- Coluna Principal -->
            <div class="col-lg-8">
                <!-- Preço e Ações -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <!-- Preço de Venda -->
                                <?php if ($imovel['tipo_negocio'] == 'venda' || $imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                    <h2 class="text-primary mb-2"><?php echo formatPrice($imovel['preco']); ?></h2>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-tag me-2"></i><?php echo $imovel['tipo_nome']; ?> - Venda
                                    </p>
                                <?php endif; ?>
                                
                                <!-- Preço de Locação -->
                                <?php if ($imovel['tipo_negocio'] == 'locacao' || $imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                    <?php if ($imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                        <hr class="my-2">
                                    <?php endif; ?>
                                    <h3 class="text-success mb-2"><?php echo formatPrice($imovel['preco_locacao']); ?>/mês</h3>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-key me-2"></i><?php echo $imovel['tipo_nome']; ?> - Locação
                                    </p>
                                <?php endif; ?>
                                
                                <!-- Badge do Tipo de Negócio -->
                                <div class="mt-2">
                                    <?php if ($imovel['tipo_negocio'] == 'venda_locacao'): ?>
                                        <span class="badge bg-primary me-2">Venda</span>
                                        <span class="badge bg-success">Locação</span>
                                    <?php elseif ($imovel['tipo_negocio'] == 'locacao'): ?>
                                        <span class="badge bg-success">Apenas Locação</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Apenas Venda</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <div class="d-grid gap-2 d-md-block">
                                    <button class="btn btn-primary btn-lg" onclick="openContactModal()">
                                        <i class="fas fa-phone me-2"></i>Falar com um Especialista
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="TemplateSite.toggleFavorite(<?php echo $imovel['id']; ?>)">
                                        <i class="far fa-heart me-2"></i>Favorito
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="TemplateSite.shareProperty(<?php echo $imovel['id']; ?>, 'whatsapp')">
                                        <i class="fab fa-whatsapp me-2"></i>Compartilhar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Características Principais -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Características Principais</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-3 mb-3">
                                <div class="feature-item">
                                    <i class="fas fa-bed fa-2x text-primary mb-2"></i>
                                    <h6 class="mb-1"><?php echo $imovel['quartos']; ?></h6>
                                    <small class="text-muted">Quartos</small>
                                </div>
                            </div>
                            <div class="col-3 mb-3">
                                <div class="feature-item">
                                    <i class="fas fa-bath fa-2x text-primary mb-2"></i>
                                    <h6 class="mb-1"><?php echo $imovel['banheiros']; ?></h6>
                                    <small class="text-muted">Banheiros</small>
                                </div>
                            </div>
                            <div class="col-3 mb-3">
                                <div class="feature-item">
                                    <i class="fas fa-car fa-2x text-primary mb-2"></i>
                                    <h6 class="mb-1"><?php echo $imovel['vagas']; ?></h6>
                                    <small class="text-muted">Vagas</small>
                                </div>
                            </div>
                            <div class="col-3 mb-3">
                                <div class="feature-item">
                                    <i class="fas fa-ruler-combined fa-2x text-primary mb-2"></i>
                                    <h6 class="mb-1"><?php echo $imovel['area_total']; ?>m²</h6>
                                    <small class="text-muted">Área Total</small>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($imovel['area_construida']): ?>
                            <div class="row text-center">
                                <div class="col-12">
                                    <div class="feature-item">
                                        <i class="fas fa-building fa-2x text-primary mb-2"></i>
                                        <h6 class="mb-1"><?php echo $imovel['area_construida']; ?>m²</h6>
                                        <small class="text-muted">Área Construída</small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Descrição -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-align-left me-2"></i>Descrição</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($imovel['descricao']): ?>
                            <p class="mb-0"><?php echo nl2br($imovel['descricao']); ?></p>
                        <?php else: ?>
                            <p class="text-muted mb-0">Descrição não disponível.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Condições de Locação -->
                <?php if (($imovel['tipo_negocio'] == 'locacao' || $imovel['tipo_negocio'] == 'venda_locacao') && $imovel['condicoes_locacao']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-key text-success me-2"></i>Condições de Locação
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <p class="mb-0"><?php echo nl2br($imovel['condicoes_locacao']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Características Detalhadas -->
                <?php if ($caracteristicas_por_categoria): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>Características Detalhadas</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($caracteristicas_por_categoria as $categoria => $caracs): ?>
                                <div class="mb-3">
                                    <h6 class="text-primary"><?php echo $categoria; ?></h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($caracs as $carac): ?>
                                            <span class="badge bg-light text-dark"><?php echo $carac; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Localização -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Localização</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <strong>Endereço:</strong><br>
                                    <?php echo $imovel['endereco']; ?>
                                    <?php if ($imovel['numero']): ?>, <?php echo $imovel['numero']; ?><?php endif; ?>
                                    <?php if ($imovel['complemento']): ?><br><?php echo $imovel['complemento']; ?><?php endif; ?>
                                </p>
                                <p class="mb-2">
                                    <strong>Bairro:</strong> <?php echo $imovel['bairro']; ?>
                                </p>
                                <p class="mb-2">
                                    <strong>Cidade:</strong> <?php echo $imovel['cidade']; ?> - <?php echo $imovel['estado']; ?>
                                </p>
                                <?php if ($imovel['cep']): ?>
                                    <p class="mb-0">
                                        <strong>CEP:</strong> <?php echo $imovel['cep']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <div class="map-placeholder bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 200px;">
                                    <div class="text-center">
                                        <i class="fas fa-map fa-3x text-muted mb-2"></i>
                                        <p class="text-muted">Mapa da localização</p>
                                        <small class="text-muted">Lat: <?php echo $imovel['latitude']; ?>, Long: <?php echo $imovel['longitude']; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Formulário de Contato -->
                <div class="card mb-4 sticky-top" style="top: 20px;">
                                         <div class="card-header bg-success text-white">
                         <h5 class="mb-0"><i class="fab fa-whatsapp me-2"></i>Interessado no Imóvel?</h5>
                         <small class="text-white-50">Preencha o formulário e será redirecionado para o WhatsApp correto</small>
                     </div>
                    <div class="card-body">
                        <form id="property-contact-form">
                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="nome" name="nome" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="telefone" class="form-label">Telefone</label>
                                <input type="tel" class="form-control" id="telefone" name="telefone">
                            </div>
                            <div class="mb-3">
                                <label for="mensagem" class="form-label">Mensagem</label>
                                <textarea class="form-control" id="mensagem" name="mensagem" rows="3" 
                                          placeholder="Gostaria de saber mais sobre este imóvel..."></textarea>
                            </div>
                                                         <button type="submit" class="btn btn-success w-100">
                                 <i class="fab fa-whatsapp me-2"></i>Enviar para WhatsApp
                             </button>
                        </form>
                    </div>
                </div>

                <!-- Informações do Corretor -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Especialista Responsável</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="corretor-info">
                            <i class="fas fa-user-circle fa-4x text-primary mb-3"></i>
                            <h6 class="mb-1"><?php echo $imovel['corretor_nome']; ?></h6>
                            <p class="text-muted mb-2">Especialista em Imóveis</p>
                            <div class="d-grid gap-2">
                                <a href="mailto:<?php echo $imovel['corretor_email']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-envelope me-2"></i>E-mail
                                </a>
                                <?php if ($imovel['corretor_telefone']): ?>
                                    <a href="tel:<?php echo $imovel['corretor_telefone']; ?>" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-phone me-2"></i>Telefone
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compartilhar -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-share-alt me-2"></i>Compartilhar</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-success" onclick="TemplateSite.shareProperty(<?php echo $imovel['id']; ?>, 'whatsapp')">
                                <i class="fab fa-whatsapp me-2"></i>WhatsApp
                            </button>
                            <button class="btn btn-primary" onclick="TemplateSite.shareProperty(<?php echo $imovel['id']; ?>, 'facebook')">
                                <i class="fab fa-facebook me-2"></i>Facebook
                            </button>
                            <button class="btn btn-info" onclick="TemplateSite.shareProperty(<?php echo $imovel['id']; ?>, 'twitter')">
                                <i class="fab fa-twitter me-2"></i>Twitter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Imóveis Similares -->
<?php if ($imoveis_similares): ?>
    <section class="similar-properties py-5 bg-light">
        <div class="container">
            <h3 class="text-center mb-4">Imóveis Similares</h3>
            <div class="row">
                <?php foreach ($imoveis_similares as $similar): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="property-card card h-100 shadow-sm">
                            <div class="property-image">
                                <?php if ($similar['foto_principal'] && imageExists($similar['foto_principal'])): ?>
                                    <img src="<?php echo getUploadPath($similar['foto_principal']); ?>" 
                                         class="card-img-top" alt="<?php echo $similar['titulo']; ?>">
                                <?php else: ?>
                                    <div class="no-image bg-light d-flex align-items-center justify-content-center" 
                                         style="height: 200px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px 8px 0 0;">
                                        <div class="text-center">
                                            <i class="fas fa-home fa-3x text-muted mb-2"></i>
                                            <p class="text-muted small mb-0">Foto não disponível</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="property-price">
                                    <span class="badge bg-primary fs-6"><?php echo formatPrice($similar['preco']); ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $similar['titulo']; ?></h5>
                                <p class="card-text text-muted">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo $similar['bairro'] . ', ' . $similar['cidade']; ?>
                                </p>
                                <div class="property-features">
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-bed me-1"></i><?php echo $similar['quartos']; ?> Quartos
                                    </span>
                                    <span class="badge bg-light text-dark me-2">
                                        <i class="fas fa-bath me-1"></i><?php echo $similar['banheiros']; ?> Banheiros
                                    </span>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="<?php echo getPagePath('imovel', ['id' => $similar['id']]); ?>" 
                                   class="btn btn-primary w-100">Ver Imóvel</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Modal de Contato -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalLabel">Falar com um Especialista</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-user-circle fa-4x text-primary"></i>
                    <h6 class="mt-2"><?php echo $imovel['corretor_nome']; ?></h6>
                    <p class="text-muted">Especialista Responsável</p>
                </div>
                <div class="d-grid gap-2">
                    <a href="mailto:<?php echo htmlspecialchars($imovel['corretor_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
                        <i class="fas fa-envelope me-2"></i>Enviar E-mail
                    </a>
                    <?php if (!empty($imovel['corretor_telefone'])): ?>
                        <a href="tel:<?php echo preg_replace('/\D+/', '', $imovel['corretor_telefone']); ?>" class="btn btn-success">
                            <i class="fas fa-phone me-2"></i>Ligar
                        </a>
                    <?php endif; ?>
                    <?php
                        $corretorWhatsapp = $imovel['corretor_whatsapp'] ?? '';
                        $fallbackWhatsapp = PHONE_WHATSAPP_VENDA ?: '';
                        $whatsappTarget = $corretorWhatsapp ?: $fallbackWhatsapp;
                        $whatsappDigits = $whatsappTarget ? preg_replace('/\D+/', '', $whatsappTarget) : '';
                    ?>
                    <?php if (!empty($whatsappDigits)): ?>
                        <a href="https://wa.me/<?php echo $whatsappDigits; ?>?text=Olá! Gostaria de saber mais sobre o imóvel: <?php echo urlencode($imovel['titulo']); ?>" 
                           target="_blank" class="btn btn-success">
                            <i class="fab fa-whatsapp me-2"></i>WhatsApp
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline-secondary disabled">
                            <i class="fab fa-whatsapp me-2"></i>Configure um número de WhatsApp para contato
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Funções JavaScript para a página
function openContactModal() {
    const modal = new bootstrap.Modal(document.getElementById('contactModal'));
    modal.show();
}

function openLightbox(index) {
    // Ativar o slide correspondente no carrossel principal
    const carousel = document.getElementById('propertyGallery');
    if (carousel) {
        const carouselInstance = bootstrap.Carousel.getInstance(carousel);
        if (carouselInstance) {
            carouselInstance.to(index);
        }
    }
    
    // Atualizar indicadores ativos
    const indicators = carousel.querySelectorAll('.carousel-indicators button');
    indicators.forEach((indicator, i) => {
        if (i === index) {
            indicator.classList.add('active');
            indicator.setAttribute('aria-current', 'true');
        } else {
            indicator.classList.remove('active');
            indicator.setAttribute('aria-current', 'false');
        }
    });
    
    // Atualizar classes dos slides
    const slides = carousel.querySelectorAll('.carousel-item');
    slides.forEach((slide, i) => {
        if (i === index) {
            slide.classList.add('active');
        } else {
            slide.classList.remove('active');
        }
    });
}

// Formulário de contato
document.getElementById('property-contact-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Obter dados do formulário
    const nome = document.getElementById('nome').value;
    const email = document.getElementById('email').value;
    const telefone = document.getElementById('telefone').value;
    const mensagem = document.getElementById('mensagem').value;
    
    // Determinar WhatsApp baseado no tipo de negócio do imóvel
    const tipoNegocio = '<?php echo $imovel['tipo_negocio']; ?>';
    let whatsappNumber;
    let whatsappText;
    
    if (tipoNegocio === 'locacao') {
        whatsappNumber = '<?php echo PHONE_WHATSAPP_LOCACAO; ?>';
        whatsappText = `Olá! Gostaria de saber mais sobre o imóvel para *LOCAÇÃO*: <?php echo addslashes($imovel['titulo']); ?>

*Dados do Interessado:*
Nome: ${nome}
E-mail: ${email}
Telefone: ${telefone}

*Mensagem:*
${mensagem}

*Detalhes do Imóvel:*
Preço: <?php echo formatPrice($imovel['preco']); ?>
Localização: <?php echo $imovel['cidade']; ?>
Quartos: <?php echo $imovel['quartos']; ?>
Banheiros: <?php echo $imovel['banheiros']; ?>
Área: <?php echo $imovel['area_total']; ?>m²`;
    } else {
        // Padrão para venda
        whatsappNumber = '<?php echo PHONE_WHATSAPP_VENDA; ?>';
        whatsappText = `Olá! Gostaria de saber mais sobre o imóvel para *VENDA*: <?php echo addslashes($imovel['titulo']); ?>

*Dados do Interessado:*
Nome: ${nome}
E-mail: ${email}
Telefone: ${telefone}

*Mensagem:*
${mensagem}

*Detalhes do Imóvel:*
Preço: <?php echo formatPrice($imovel['preco']); ?>
Localização: <?php echo $imovel['cidade']; ?>
Quartos: <?php echo $imovel['quartos']; ?>
Banheiros: <?php echo $imovel['banheiros']; ?>
Área: <?php echo $imovel['area_total']; ?>m²`;
    }
    
    // Redirecionar para WhatsApp com mensagem formatada
    const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(whatsappText)}`;
    window.open(whatsappUrl, '_blank');
    
    // Mostrar notificação de sucesso
    TemplateSite.showNotification('Redirecionando para WhatsApp!', 'success');
    
    // Resetar formulário
    this.reset();
});

// Sincronizar carrossel principal com miniaturas
document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.getElementById('propertyGallery');
    if (carousel) {
        carousel.addEventListener('slid.bs.carousel', function(event) {
            const activeIndex = event.to;
            updateThumbnailActive(activeIndex);
        });
        
        // Inicializar primeira miniatura como ativa
        updateThumbnailActive(0);
    }
});

function updateThumbnailActive(activeIndex) {
    // Remover classe ativa de todas as miniaturas
    const thumbnails = document.querySelectorAll('.thumbnail-item');
    thumbnails.forEach((thumbnail, index) => {
        if (index === activeIndex) {
            thumbnail.classList.add('active');
        } else {
            thumbnail.classList.remove('active');
        }
    });
}
</script>


