<?php
// Página Sobre - imobsites

// Buscar estatísticas para mostrar na página
$total_imoveis = fetch("SELECT COUNT(*) as total FROM imoveis")['total'];
$total_vendidos = fetch("SELECT COUNT(*) as total FROM imoveis WHERE status = 'vendido'")['total'];
$total_alugados = fetch("SELECT COUNT(*) as total FROM imoveis WHERE status = 'alugado'")['total'];
$total_clientes = fetch("SELECT COUNT(*) as total FROM clientes")['total'];
?>

<!-- Hero Section -->
<section class="hero-section-small bg-primary text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-12 text-center">
                <h1 class="h2 mb-2">Sobre a <?php echo SITE_NAME; ?></h1>
                <p class="mb-0">Conheça nossa história, missão e valores</p>
            </div>
        </div>
    </div>
</section>

<!-- História da Empresa -->
<section class="company-history py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4">
                <div class="history-content">
                    <h2 class="mb-4">Nossa História</h2>
                    <p class="lead mb-4">
                        <strong>Construtora Rossi e Rossi e Imobsites Incorporadora em São José dos Campos</strong>
                    </p>
                    <p class="mb-4">
                        Desde 1991, a Imobsites transforma projetos em realidade, ajudando famílias a conquistarem a casa própria e empresários a encontrarem o espaço ideal para seus negócios.
                    </p>
                    <p class="mb-4">
                        Somos uma empresa familiar, com mais de 30 anos de experiência em construção e incorporação de imóveis residenciais e comerciais, sempre com qualidade, segurança e valorização.
                    </p>
                    <p class="mb-4">
                        <strong>Residenciais:</strong> casas e apartamentos planejados para oferecer conforto, bem-estar e a realização do sonho da casa própria.<br>
                        <strong>Comerciais:</strong> empreendimentos que unem localização estratégica e estrutura ideal para o crescimento dos negócios.
                    </p>
                    <div class="mt-4">
                        <h5 class="mb-3">Por que escolher a Imobsites?</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Tradição e credibilidade desde 1991</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Empreendimentos que valorizam ao longo do tempo</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Atendimento próximo e personalizado</li>
                            <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Soluções para quem deseja morar, investir ou empreender</li>
                        </ul>
                    </div>
                    <p class="mb-0 mt-4">
                        Entre em contato e descubra como podemos ajudar você a conquistar seu imóvel residencial ou comercial.
                    </p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="history-image text-center">
                    <div class="image-placeholder bg-light d-flex align-items-center justify-content-center" 
                         style="height: 400px; border-radius: 10px;">
                        <div class="text-center">
                            <i class="fas fa-building fa-5x text-primary mb-3"></i>
                            <h5 class="text-muted">Nossos Projetos</h5>
                            <p class="text-muted">Desenvolvimento e Construção</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Missão, Visão e Valores -->
<section class="mission-vision-values py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        <div class="icon-wrapper mb-3">
                            <i class="fas fa-bullseye fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Nossa Missão</h4>
                        <p class="card-text">
                            Transformar projetos em realidade, ajudando famílias a conquistarem a casa própria 
                            e empresários a encontrarem o espaço ideal para seus negócios, sempre com qualidade, 
                            segurança e valorização em cada empreendimento.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        <div class="icon-wrapper mb-3">
                            <i class="fas fa-eye fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Nossa Visão</h4>
                        <p class="card-text">
                            Continuar sendo referência em São José dos Campos como empresa familiar de tradição 
                            e credibilidade, expandindo nossa atuação em empreendimentos residenciais e comerciais 
                            que valorizam ao longo do tempo.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        <div class="icon-wrapper mb-3">
                            <i class="fas fa-heart fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Nossos Valores</h4>
                        <ul class="list-unstyled text-start">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Tradição e Credibilidade (desde 1991)</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Qualidade e Segurança na Construção</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Atendimento Próximo e Personalizado</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Valorização dos Empreendimentos</li>
                            <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Compromisso com Famílias e Empresários</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>





<!-- CTA -->
<section class="cta-section py-5 bg-primary text-white">
    <div class="container text-center">
        <h3 class="mb-3">Conheça Nossos Imóveis</h3>
        <p class="lead mb-4">
            Agende uma visita e descubra nossos projetos desenvolvidos com qualidade e seriedade!
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="<?php echo getPagePath('contato'); ?>" class="btn btn-light btn-lg">
                <i class="fas fa-phone me-2"></i>Fale Conosco
            </a>
            <a href="<?php echo getPagePath('imoveis'); ?>" class="btn btn-outline-light btn-lg">
                <i class="fas fa-search me-2"></i>Ver Imóveis
            </a>
            <a href="https://wa.me/5511999999999?text=Olá! Gostaria de agendar uma visita." 
               target="_blank" class="btn btn-success btn-lg">
                <i class="fab fa-whatsapp me-2"></i>WhatsApp
            </a>
        </div>
    </div>
</section>



