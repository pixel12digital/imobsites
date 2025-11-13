<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        $pageTitle = SITE_NAME ?: 'Portal Imobiliário';
        if (!empty(SITE_TAGLINE)) {
            $pageTitle .= ' - ' . SITE_TAGLINE;
        }

        $metaDescription = SITE_META_DESCRIPTION ?: 'Adicione a descrição da sua imobiliária.';
        $metaKeywords = SITE_META_KEYWORDS ?: 'imobiliária, imóveis, personalize';
        $metaAuthor = SITE_META_AUTHOR ?: (SITE_NAME ?: 'Imobiliária');
        $highlightPhone = PHONE_VENDA ?: 'Defina o telefone principal da imobiliária no painel.';

        $logoSetting = tenantSetting('logo_path', '');
        $logoUrl = $logoSetting ? getUploadPath($logoSetting) : false;
        if (!$logoUrl) {
            $logoUrl = getAssetPath('logo-imobsites-full.svg');
        }

        $facebookUrl = tenantSetting('facebook_url', '');
        $instagramUrl = tenantSetting('instagram_url', '');
        $linkedinUrl = tenantSetting('linkedin_url', '');
    ?>
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo getAssetPath('css/style.css'); ?>" rel="stylesheet">
    
    <!-- Meta tags SEO -->
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($metaKeywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($metaAuthor, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo getBaseUrl(); ?>">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <!-- Top Bar -->
        <div class="top-bar bg-logo-green text-white py-2">
            <div class="container">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-phone me-2"></i>
                        <span><?php echo htmlspecialchars($highlightPhone, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="social-links d-flex align-items-center">
                        <?php if ($facebookUrl): ?>
                            <a href="<?php echo htmlspecialchars($facebookUrl, ENT_QUOTES, 'UTF-8'); ?>" class="text-white me-3" target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($instagramUrl): ?>
                            <a href="<?php echo htmlspecialchars($instagramUrl, ENT_QUOTES, 'UTF-8'); ?>" class="text-white me-3" target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-instagram"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($linkedinUrl): ?>
                            <a href="<?php echo htmlspecialchars($linkedinUrl, ENT_QUOTES, 'UTF-8'); ?>" class="text-white me-3" target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!$facebookUrl && !$instagramUrl && !$linkedinUrl): ?>
                            <span class="text-white-50 small">Configure suas redes sociais no painel.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Header -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container">
                <div class="row align-items-center w-100">
                    <div class="col-lg-6 col-md-6 col-6">
                        <a class="navbar-brand" href="<?php echo getPagePath('home'); ?>">
                            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(SITE_NAME ?: 'Logo da Imobiliária', ENT_QUOTES, 'UTF-8'); ?>" class="logo-img">
                        </a>
                    </div>
                    
                    <div class="col-lg-6 col-md-6 col-6 d-flex justify-content-end">
                        <!-- Menu mobile -->
                        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Menu de Navegação -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getPagePath('home'); ?>">Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getPagePath('imoveis'); ?>">Imóveis</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getPagePath('sobre'); ?>">Sobre</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getPagePath('contato'); ?>">Contato</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getPagePath('admin'); ?>">Login</a>
                    </li>
                </ul>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
