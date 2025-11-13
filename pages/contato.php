<?php
// Página de Contato - imobsites

// Processar mensagens de sucesso/erro
$mensagem_enviada = false;
$erro = '';
$tipo_operacao = '';

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $mensagem_enviada = true;
    $tipo_operacao = isset($_GET['tipo']) ? $_GET['tipo'] : '';
}

if (isset($_GET['error'])) {
    $erro = urldecode($_GET['error']);
}

// Buscar estatísticas de imóveis
try {
    $stats_sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'disponivel' THEN 1 ELSE 0 END) as disponiveis,
        SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) as vendidos,
        SUM(CASE WHEN status = 'alugado' THEN 1 ELSE 0 END) as alugados
    FROM imoveis
    WHERE tenant_id = ?";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([TENANT_ID]);
    $stats = $stats_stmt->fetch();
    
    $total_imoveis = $stats['disponiveis'] ?? 0;
    $total_vendidos = $stats['vendidos'] ?? 0;
    $total_alugados = $stats['alugados'] ?? 0;
} catch (Exception $e) {
    $total_imoveis = 0;
    $total_vendidos = 0;
    $total_alugados = 0;
}

$phoneVendaDigits = PHONE_VENDA ? preg_replace('/\D+/', '', PHONE_VENDA) : '';
$phoneLocacaoDigits = PHONE_LOCACAO ? preg_replace('/\D+/', '', PHONE_LOCACAO) : '';
$whatsappVenda = PHONE_WHATSAPP_VENDA ?: '';
$whatsappLocacao = PHONE_WHATSAPP_LOCACAO ?: '';
$phoneVendaDisplay = PHONE_VENDA ? htmlspecialchars(PHONE_VENDA, ENT_QUOTES, 'UTF-8') : '';
$phoneLocacaoDisplay = PHONE_LOCACAO ? htmlspecialchars(PHONE_LOCACAO, ENT_QUOTES, 'UTF-8') : '';
$siteEmailDisplay = SITE_EMAIL ? htmlspecialchars(SITE_EMAIL, ENT_QUOTES, 'UTF-8') : '';
$siteNameDisplay = SITE_NAME ? htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') : 'Nome da Imobiliária';
?>

<!-- Hero Section -->
<section class="hero-section-small bg-primary text-white py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-12 text-center">
                <h1 class="h2 mb-2">Entre em Contato</h1>
                <p class="mb-0">Estamos aqui para ajudar você a encontrar o imóvel ideal</p>
            </div>
        </div>
    </div>
</section>

<!-- Números de Contato Destacados -->
<section class="contact-numbers-highlight py-4 bg-light" role="region" aria-labelledby="contact-numbers-title">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="contact-numbers-title" class="visually-hidden">Números de Contato Principais</h2>
                <div class="row g-3">
                    <!-- Vendas -->
                    <div class="col-md-6">
                        <div class="card border-success h-100" role="article" aria-labelledby="vendas-title">
                            <div class="card-body text-center p-3">
                                <div class="mb-2">
                                    <i class="fas fa-home fa-2x text-success" aria-hidden="true"></i>
                                </div>
                                <h3 id="vendas-title" class="card-title text-success mb-2 h6">
                                    <i class="fas fa-star me-1" aria-hidden="true"></i>Vendas
                                </h3>
                                <?php if (!empty($phoneVendaDigits)): ?>
                                    <p class="card-text mb-2">
                                        <a href="tel:<?php echo $phoneVendaDigits; ?>" 
                                           class="btn btn-success btn-sm"
                                           aria-label="Ligar para vendas: <?php echo $phoneVendaDisplay; ?>"
                                           title="Ligar para vendas">
                                            <i class="fas fa-phone me-1" aria-hidden="true"></i><?php echo $phoneVendaDisplay; ?>
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <p class="card-text mb-2 text-muted">Defina um telefone de vendas para habilitar este botão.</p>
                                <?php endif; ?>

                                <?php if (!empty($whatsappVenda)): ?>
                                    <p class="card-text mb-2">
                                        <a href="https://wa.me/<?php echo $whatsappVenda; ?>?text=Olá! Gostaria de saber mais sobre imóveis para compra." 
                                           target="_blank" 
                                           class="btn btn-outline-success btn-sm"
                                           aria-label="Abrir WhatsApp para vendas. Número: <?php echo $phoneVendaDisplay ?: 'Configurar número'; ?>"
                                           title="WhatsApp Vendas">
                                            <i class="fab fa-whatsapp me-1" aria-hidden="true"></i>WhatsApp Vendas
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <p class="card-text mb-2 text-muted">Informe um número de WhatsApp de vendas para exibir aqui.</p>
                                <?php endif; ?>
                                <small class="text-muted">Compra e venda de imóveis</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Locação -->
                    <div class="col-md-6">
                        <div class="card border-info h-100" role="article" aria-labelledby="locacao-title">
                            <div class="card-body text-center p-3">
                                <div class="mb-2">
                                    <i class="fas fa-key fa-2x text-info" aria-hidden="true"></i>
                                </div>
                                <h3 id="locacao-title" class="card-title text-info mb-2 h6">
                                    <i class="fas fa-star me-1" aria-hidden="true"></i>Locação
                                </h3>
                                <?php if (!empty($phoneLocacaoDigits)): ?>
                                    <p class="card-text mb-2">
                                        <a href="tel:<?php echo $phoneLocacaoDigits; ?>" 
                                           class="btn btn-info btn-sm"
                                           aria-label="Ligar para locação: <?php echo $phoneLocacaoDisplay; ?>"
                                           title="Ligar para locação">
                                            <i class="fas fa-phone me-1" aria-hidden="true"></i><?php echo $phoneLocacaoDisplay; ?>
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <p class="card-text mb-2 text-muted">Defina um telefone de locação para habilitar este botão.</p>
                                <?php endif; ?>
                                <?php if (!empty($whatsappLocacao)): ?>
                                    <p class="card-text mb-2">
                                        <a href="https://wa.me/<?php echo $whatsappLocacao; ?>?text=Olá! Gostaria de saber mais sobre imóveis para aluguel." 
                                           target="_blank" 
                                           class="btn btn-outline-info btn-sm"
                                           aria-label="Abrir WhatsApp para locação. Número: <?php echo $phoneLocacaoDisplay ?: 'Configurar número'; ?>"
                                           title="WhatsApp Locação">
                                            <i class="fab fa-whatsapp me-1" aria-hidden="true"></i>WhatsApp Locação
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <p class="card-text mb-2 text-muted">Informe um número de WhatsApp de locação para exibir aqui.</p>
                                <?php endif; ?>
                                <small class="text-muted">Aluguel de imóveis</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Informações de Contato -->
<section class="contact-info-section py-5">
    <div class="container">
        <div class="row">
            <!-- Informações da Empresa -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="contact-icon mb-3">
                            <i class="fas fa-building fa-3x text-primary"></i>
                        </div>
                        <h5 class="card-title"><?php echo $siteNameDisplay; ?></h5>
                        <p class="card-text text-muted">
                            Personalize este texto para apresentar rapidamente os serviços e diferenciais da sua imobiliária.
                        </p>
                        
                        <!-- Estatísticas -->
                        <div class="row text-center mt-4">
                            <div class="col-4">
                                <div class="stat-item">
                                    <h6 class="text-primary mb-1"><?php echo $total_imoveis; ?></h6>
                                    <small class="text-muted">Disponíveis</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-item">
                                    <h6 class="text-success mb-1"><?php echo $total_vendidos; ?></h6>
                                    <small class="text-muted">Vendidos</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-item">
                                    <h6 class="text-info mb-1"><?php echo $total_alugados; ?></h6>
                                    <small class="text-muted">Alugados</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informações de Contato -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-address-book me-2 text-primary"></i>Informações de Contato
                        </h5>
                        
                        <!-- Telefone Geral -->
                        <div class="contact-item mb-3">
                            <div class="d-flex align-items-center">
                                <div class="contact-icon-small me-3">
                                    <i class="fas fa-phone fa-lg text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Telefone Geral</h6>
                                    <?php if (!empty($phoneVendaDigits)): ?>
                                        <p class="mb-0">
                                            <a href="tel:<?php echo $phoneVendaDigits; ?>" class="text-decoration-none">
                                                <?php echo $phoneVendaDisplay; ?>
                                            </a>
                                        </p>
                                    <?php else: ?>
                                        <p class="mb-0 text-muted">Defina o telefone principal para exibir aqui.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Vendas - Destaque -->
                        <div class="contact-item mb-3 p-3 bg-success bg-opacity-10 rounded" role="article" aria-labelledby="vendas-detail-title">
                            <div class="d-flex align-items-center">
                                <div class="contact-icon-small me-3">
                                    <i class="fas fa-home fa-lg text-success" aria-hidden="true"></i>
                                </div>
                                <div>
                                    <h6 id="vendas-detail-title" class="mb-1 text-success">
                                        <i class="fas fa-star me-1" aria-hidden="true"></i>Vendas
                                    </h6>
                                    <?php if (!empty($phoneVendaDigits)): ?>
                                        <p class="mb-0">
                                            <a href="tel:<?php echo $phoneVendaDigits; ?>" 
                                               class="text-decoration-none fw-bold"
                                               aria-label="Ligar para vendas: <?php echo $phoneVendaDisplay; ?>"
                                               title="Ligar para vendas">
                                                <?php echo $phoneVendaDisplay; ?>
                                            </a>
                                        </p>
                                        <small class="text-muted">Compra e venda de imóveis</small>
                                    <?php else: ?>
                                        <p class="mb-0 text-muted">Adicione o telefone de vendas nas configurações.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Locação - Destaque -->
                        <div class="contact-item mb-3 p-3 bg-info bg-opacity-10 rounded" role="article" aria-labelledby="locacao-detail-title">
                            <div class="d-flex align-items-center">
                                <div class="contact-icon-small me-3">
                                    <i class="fas fa-key fa-lg text-info" aria-hidden="true"></i>
                                </div>
                                <div>
                                    <h6 id="locacao-detail-title" class="mb-1 text-info">
                                        <i class="fas fa-star me-1" aria-hidden="true"></i>Locação
                                    </h6>
                                    <?php if (!empty($phoneLocacaoDigits)): ?>
                                        <p class="mb-0">
                                            <a href="tel:<?php echo $phoneLocacaoDigits; ?>" 
                                               class="text-decoration-none fw-bold"
                                               aria-label="Ligar para locação: <?php echo $phoneLocacaoDisplay; ?>"
                                               title="Ligar para locação">
                                                <?php echo $phoneLocacaoDisplay; ?>
                                            </a>
                                        </p>
                                        <small class="text-muted">Aluguel de imóveis</small>
                                    <?php else: ?>
                                        <p class="mb-0 text-muted">Adicione o telefone de locação nas configurações.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="contact-item mb-3">
                            <div class="d-flex align-items-center">
                                <div class="contact-icon-small me-3">
                                    <i class="fas fa-envelope fa-lg text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">E-mail</h6>
                                    <?php if (!empty(SITE_EMAIL)): ?>
                                        <p class="mb-0">
                                            <a href="mailto:<?php echo SITE_EMAIL; ?>" class="text-decoration-none">
                                                <?php echo $siteEmailDisplay; ?>
                                            </a>
                                        </p>
                                    <?php else: ?>
                                        <p class="mb-0 text-muted">Informe o e-mail de contato nas configurações.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>



                        <div class="contact-item">
                            <div class="d-flex align-items-center">
                                <div class="contact-icon-small me-3">
                                    <i class="fas fa-clock fa-lg text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Horário de Atendimento</h6>
                                    <p class="mb-0">Segunda a Sexta: 8h às 18h<br>Sábado: 8h às 12h</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Redes Sociais e WhatsApp -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-share-alt me-2 text-primary"></i>Redes Sociais
                        </h5>
                        
                        <div class="social-links mb-4">
                            <a href="#" class="btn btn-outline-primary btn-lg me-2 mb-2">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="btn btn-outline-danger btn-lg me-2 mb-2">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="btn btn-outline-success btn-lg me-2 mb-2">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <a href="#" class="btn btn-outline-info btn-lg mb-2">
                                <i class="fab fa-linkedin"></i>
                            </a>
                        </div>

                        <div class="whatsapp-cta">
                            <h6 class="mb-3">Atendimento via WhatsApp</h6>
                            
                            <div class="mb-3">
                                <?php if (!empty($whatsappVenda)): ?>
                                    <a href="https://wa.me/<?php echo $whatsappVenda; ?>?text=Olá! Gostaria de saber mais sobre imóveis para compra." 
                                       target="_blank" class="btn btn-success btn-sm w-100 mb-2">
                                        <i class="fab fa-whatsapp me-2"></i>Vendas
                                    </a>
                                <?php else: ?>
                                    <span class="btn btn-outline-secondary btn-sm w-100 mb-2 disabled">Configurar WhatsApp de vendas</span>
                                <?php endif; ?>
                                <?php if (!empty($whatsappLocacao)): ?>
                                    <a href="https://wa.me/<?php echo $whatsappLocacao; ?>?text=Olá! Gostaria de saber mais sobre imóveis para aluguel." 
                                       target="_blank" class="btn btn-info btn-sm w-100">
                                        <i class="fab fa-whatsapp me-2"></i>Locação
                                    </a>
                                <?php else: ?>
                                    <span class="btn btn-outline-secondary btn-sm w-100 disabled">Configurar WhatsApp de locação</span>
                                <?php endif; ?>
                            </div>
                            
                            <small class="text-muted d-block mt-2">
                                Ajuste os números de WhatsApp para personalizar o atendimento.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Formulário de Contato -->
<section class="contact-form-section py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow">
                                         <div class="card-header bg-primary text-white text-center">
                         <h4 id="contact-form-title" class="mb-0">
                             <i class="fas fa-paper-plane me-2" aria-hidden="true"></i>Envie sua Mensagem
                         </h4>
                         <p class="mb-0 mt-2">Preencha o formulário abaixo e entraremos em contato</p>
                     </div>
                    <div class="card-body p-4">
                        <?php if ($mensagem_enviada): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Mensagem enviada com sucesso!</strong> Entraremos em contato em breve.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($erro): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Erro:</strong> <?php echo $erro; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                                                 <form id="contact-form" method="POST" action="process_contact.php" role="form" aria-labelledby="contact-form-title">
                             <div class="row">
                                 <div class="col-md-6 mb-3">
                                     <label for="nome" class="form-label">
                                         <i class="fas fa-user me-2 text-primary" aria-hidden="true"></i>Nome Completo <span class="text-danger" aria-label="obrigatório">*</span>
                                     </label>
                                     <input type="text" 
                                            class="form-control" 
                                            id="nome" 
                                            name="nome" 
                                            value="<?php echo isset($nome) ? htmlspecialchars($nome) : ''; ?>" 
                                            required
                                            aria-required="true"
                                            aria-describedby="nome-help"
                                            placeholder="Digite seu nome completo">
                                     <div id="nome-help" class="form-text visually-hidden">Campo obrigatório para identificação</div>
                                 </div>
                                 <div class="col-md-6 mb-3">
                                     <label for="email" class="form-label">
                                         <i class="fas fa-envelope me-2 text-primary" aria-hidden="true"></i>E-mail <span class="text-danger" aria-label="obrigatório">*</span>
                                     </label>
                                     <input type="email" 
                                            class="form-control" 
                                            id="email" 
                                            name="email" 
                                            value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" 
                                            required
                                            aria-required="true"
                                            aria-describedby="email-help"
                                            placeholder="Digite seu e-mail válido">
                                     <div id="email-help" class="form-text visually-hidden">Campo obrigatório para contato</div>
                                 </div>
                             </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telefone" class="form-label">
                                        <i class="fas fa-phone me-2 text-primary"></i>Telefone
                                    </label>
                                                                        <input type="tel" class="form-control" id="telefone" name="telefone" 
                                           value="<?php echo isset($telefone) ? htmlspecialchars($telefone) : ''; ?>" 
                                           placeholder="(00) 00000-0000">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="assunto" class="form-label">
                                        <i class="fas fa-tag me-2 text-primary"></i>Assunto
                                    </label>
                                    <select class="form-select" id="assunto" name="assunto">
                                        <option value="">Selecione um assunto</option>
                                        <option value="Compra de Imóvel" <?php echo (isset($assunto) && $assunto == 'Compra de Imóvel') ? 'selected' : ''; ?>>
                                            Compra de Imóvel
                                        </option>
                                        <option value="Venda de Imóvel" <?php echo (isset($assunto) && $assunto == 'Venda de Imóvel') ? 'selected' : ''; ?>>
                                            Venda de Imóvel
                                        </option>
                                        <option value="Aluguel" <?php echo (isset($assunto) && $assunto == 'Aluguel') ? 'selected' : ''; ?>>
                                            Aluguel
                                        </option>
                                        <option value="Financiamento" <?php echo (isset($assunto) && $assunto == 'Financiamento') ? 'selected' : ''; ?>>
                                            Financiamento
                                        </option>
                                        <option value="Outros" <?php echo (isset($assunto) && $assunto == 'Outros') ? 'selected' : ''; ?>>
                                            Outros
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="mensagem" class="form-label">
                                    <i class="fas fa-comment me-2 text-primary"></i>Mensagem *
                                </label>
                                <textarea class="form-control" id="mensagem" name="mensagem" rows="5" 
                                          placeholder="Conte-nos como podemos ajudar você..." required><?php echo isset($mensagem) ? htmlspecialchars($mensagem) : ''; ?></textarea>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="concordo" name="concordo" required>
                                    <label class="form-check-label" for="concordo">
                                        Concordo com a <a href="#" class="text-decoration-none">política de privacidade</a> 
                                        e autorizo o contato através dos dados fornecidos.
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="enviar_contato" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Enviar Mensagem
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>



<!-- FAQ -->
<section class="faq-section py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h3 class="text-center mb-5">
                    <i class="fas fa-question-circle me-2 text-primary"></i>Perguntas Frequentes
                </h3>
                
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq1">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                Como funciona o processo de compra de um imóvel?
                            </button>
                        </h2>
                        <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                O processo de compra envolve várias etapas: escolha do imóvel, análise de documentação, 
                                negociação, financiamento (se necessário), assinatura do contrato e escritura. 
                                Nossa equipe acompanha você em todo o processo.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                Quais documentos são necessários para financiamento?
                            </button>
                        </h2>
                        <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Para financiamento imobiliário são necessários: RG, CPF, comprovante de renda, 
                                comprovante de residência, extrato bancário e outros documentos específicos 
                                solicitados pela instituição financeira.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq3">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                Vocês atendem outras regiões além da principal área de atuação?
                            </button>
                        </h2>
                        <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Personalize esta resposta com as regiões, cidades ou estados onde sua imobiliária atua. 
                                Aproveite para orientar o cliente sobre como checar disponibilidade de atendimento.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq4">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                Qual a taxa de corretagem?
                            </button>
                        </h2>
                        <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                A taxa de corretagem varia conforme o tipo de imóvel e serviço. Geralmente é de 5% 
                                a 6% sobre o valor da transação. Entre em contato para uma consulta personalizada 
                                e sem compromisso.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Final -->
<section class="cta-section py-5 bg-primary text-white">
    <div class="container text-center">
        <h3 class="mb-3">Personalize o chamado para ação final</h3>
        <p class="lead mb-4">Adicione um convite direto para que visitantes conversem com a sua equipe ou façam um cadastro.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="<?php echo getPagePath('imoveis'); ?>" class="btn btn-light btn-lg">
                <i class="fas fa-search me-2"></i>Ver Imóveis
            </a>
            <?php if (!empty($whatsappVenda)): ?>
                <a href="https://wa.me/<?php echo $whatsappVenda; ?>?text=Olá! Gostaria de agendar uma visita para compra." 
                   target="_blank" class="btn btn-success btn-lg">
                    <i class="fab fa-whatsapp me-2"></i>Agendar Visita - Vendas
                </a>
            <?php endif; ?>
            <?php if (!empty($whatsappLocacao)): ?>
                <a href="https://wa.me/<?php echo $whatsappLocacao; ?>?text=Olá! Gostaria de agendar uma visita para locação." 
                   target="_blank" class="btn btn-info btn-lg">
                    <i class="fab fa-whatsapp me-2"></i>Agendar Visita - Locação
                </a>
            <?php endif; ?>
            <?php if (empty($whatsappVenda) && empty($whatsappLocacao)): ?>
                <span class="text-white-50 d-inline-flex align-items-center">
                    <i class="fab fa-whatsapp me-2"></i>Configure os números de WhatsApp para mostrar os botões de contato rápido.
                </span>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
// Validação e funcionalidades do formulário
document.getElementById('contact-form').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevenir envio padrão
    
    const nome = document.getElementById('nome').value.trim();
    const email = document.getElementById('email').value.trim();
    const mensagem = document.getElementById('mensagem').value.trim();
    const concordo = document.getElementById('concordo').checked;
    
    if (!nome || !email || !mensagem) {
        showNotification('Por favor, preencha todos os campos obrigatórios.', 'warning');
        return false;
    }
    
    if (!concordo) {
        showNotification('Você deve concordar com a política de privacidade.', 'warning');
        return false;
    }
    
    // Mostrar loading no botão
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
    submitBtn.disabled = true;
    
    // Criar FormData para envio AJAX
    const formData = new FormData(this);
    
    // Enviar via AJAX
    fetch('process_contact.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            showNotification(data.mensagem, 'success');
            
            // Mostrar informações específicas baseadas no tipo de operação
            if (data.tipo_operacao) {
                const tipoText = data.tipo_operacao === 'venda' ? 'Vendas' : 'Locação';
                const telefone = data.telefone_operacao;
                
                setTimeout(() => {
                    showNotification(`Sua mensagem foi direcionada para o setor de ${tipoText}. Telefone: ${telefone}`, 'info');
                }, 2000);
            }
            
            // Limpar formulário
            this.reset();
        } else {
            showNotification(data.mensagem, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao enviar mensagem. Tente novamente.', 'error');
    })
    .finally(() => {
        // Restaurar botão
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Função para mostrar notificações
function showNotification(message, type = 'info') {
    // Verificar se existe a função global TemplateSite
    if (typeof TemplateSite !== 'undefined' && TemplateSite.showNotification) {
        TemplateSite.showNotification(message, type);
    } else {
        // Fallback para notificação simples
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Inserir no topo do formulário
        const form = document.getElementById('contact-form');
        form.insertAdjacentHTML('beforebegin', alertHtml);
        
        // Auto-remover após 5 segundos
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.remove());
        }, 5000);
    }
}

// Máscara para telefone
document.getElementById('telefone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 0) {
        value = '(' + value;
        if (value.length > 3) {
            value = value.substring(0, 3) + ') ' + value.substring(3);
        }
        if (value.length > 10) {
            value = value.substring(0, 10) + '-' + value.substring(10);
        }
        if (value.length > 15) {
            value = value.substring(0, 15);
        }
    }
    e.target.value = value;
});


</script>


