<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Realizando Sonhos</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo getAssetPath('css/style.css'); ?>" rel="stylesheet">
    
    <!-- Meta tags SEO -->
    <meta name="description" content="imobsites - Encontre seu lar dos sonhos. Imóveis em São Paulo e região com as melhores condições.">
    <meta name="keywords" content="imóveis, casas, apartamentos, São Paulo, compra, venda, aluguel">
    <meta name="author" content="imobsites">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo SITE_NAME; ?>">
    <meta property="og:description" content="Encontre seu lar dos sonhos com a imobsites">
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
                        <span><?php echo PHONE_VENDA; ?></span>
                    </div>
                    <div class="social-links">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-whatsapp"></i></a>
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
                            <img src="<?php echo getAssetPath('logo-imob.png'); ?>" alt="Imobsites" class="logo-img">
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
