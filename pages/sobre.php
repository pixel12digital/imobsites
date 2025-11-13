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
                <h1 class="h2 mb-2">Sobre a <?php echo SITE_NAME ?: 'sua imobiliária'; ?></h1>
                <p class="mb-0">Utilize esta página para contar a trajetória e os diferenciais da sua empresa</p>
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
                        Apresente aqui a história da sua imobiliária: como tudo começou, quais conquistas já foram alcançadas e de que forma você apoia seus clientes.
                    </p>
                    <p class="mb-4">
                        Use este espaço para destacar diferenciais, especialidades e locais de atuação. Ao ativar um tenant, substitua os textos por informações reais e mostre como sua equipe atende compradores, vendedores e investidores.
                    </p>
                    <p class="mb-4">
                        <strong>Imóveis residenciais:</strong> descreva perfis, regiões e serviços oferecidos.<br>
                        <strong>Imóveis comerciais:</strong> destaque oportunidades e o suporte prestado a empresários e investidores.
                    </p>
                    <div class="mt-4">
                        <h5 class="mb-3">Por que escolher nossa equipe?</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Destaque benefícios reais da sua imobiliária</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mostre certificações, cases ou áreas de atuação</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Explique como o atendimento é conduzido</li>
                            <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Apresente soluções para diferentes perfis de clientes</li>
                        </ul>
                    </div>
                    <p class="mb-0 mt-4">
                        Personalize este parágrafo com um convite direto para que visitantes entrem em contato com sua equipe.
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
                            Descreva de forma objetiva qual é o propósito da sua imobiliária, quais clientes atende e que tipo de experiência deseja entregar.
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
                            Utilize esta seção para compartilhar onde a empresa deseja chegar e como pretende evoluir nos próximos anos.
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
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Liste valores que orientam sua operação</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Compartilhe compromissos com clientes e parceiros</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Inclua diferenciais culturais ou estratégicos</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mostre como a equipe mantém a qualidade do serviço</li>
                            <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Adapte este conteúdo às prioridades da sua marca</li>
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
        <h3 class="mb-3">Personalize sua chamada para ação</h3>
        <p class="lead mb-4">
            Use esta área para convidar visitantes a falar com sua equipe comercial, agendar uma visita ou cadastrar um imóvel.
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="<?php echo getPagePath('contato'); ?>" class="btn btn-light btn-lg">
                <i class="fas fa-phone me-2"></i>Fale Conosco
            </a>
            <a href="<?php echo getPagePath('imoveis'); ?>" class="btn btn-outline-light btn-lg">
                <i class="fas fa-search me-2"></i>Ver Imóveis
            </a>
            <?php if (!empty(PHONE_WHATSAPP_VENDA)): ?>
                <a href="https://wa.me/<?php echo PHONE_WHATSAPP_VENDA; ?>?text=Olá! Gostaria de conversar com a equipe de vendas." 
                   target="_blank" class="btn btn-success btn-lg">
                    <i class="fab fa-whatsapp me-2"></i>WhatsApp
                </a>
            <?php else: ?>
                <span class="text-white-50 d-inline-flex align-items-center">
                    <i class="fab fa-whatsapp me-2"></i>Configure o WhatsApp de contato para exibir aqui
                </span>
            <?php endif; ?>
        </div>
    </div>
</section>



