<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['master_logged_in']) || $_SESSION['master_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$headerTheme = isset($headerTheme) && $headerTheme === 'dark' ? 'dark' : 'light';
$navbarTone = $headerTheme === 'dark' ? 'navbar-dark' : 'navbar-light';
$headerClass = $headerTheme === 'dark' ? 'is-dark' : 'is-light';
$isDark = $headerTheme === 'dark';
$assetsBase = '../assets';
$logoFile = $isDark ? 'logo-imobsites-white.svg' : 'logo-imobsites-full.svg';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo MASTER_PANEL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/theme.css" rel="stylesheet">
    <link href="assets/css/master.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg app-header <?php echo $navbarTone . ' ' . $headerClass; ?>" data-header-theme="<?php echo $headerTheme; ?>">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img
                id="header-logo"
                class="brand-logo <?php echo $isDark ? 'brand-logo--dark' : 'brand-logo--light'; ?>"
                src="<?php echo $assetsBase . '/' . $logoFile; ?>"
                alt="Imobsites"
                width="118"
                height="32"
                decoding="async"
            >
            <span class="ms-2"><?php echo MASTER_PANEL_NAME; ?></span>
        </a>
        <div class="header-controls">
            <div class="header-account" tabindex="0" aria-label="Usuário Master Admin">
                <i class="fas fa-user-shield"></i>
                <span>Master Admin</span>
            </div>
            <a href="logout.php" class="btn btn-logout btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>Sair
            </a>
        </div>
    </div>
</nav>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar py-4">
            <div class="position-sticky">
                <div class="px-3 mb-4">
                    <h6 class="text-uppercase text-muted small">Navegação</h6>
                </div>
                <ul class="nav flex-column px-3 gap-1">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-chart-pie me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'tenant.php' ? 'active' : ''; ?>" href="tenant.php">
                            <i class="fas fa-building me-2"></i>Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'planos.php' ? 'active' : ''; ?>" href="planos.php">
                            <i class="fas fa-tags me-2"></i>Planos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : ''; ?>" href="pedidos.php">
                            <i class="fas fa-shopping-cart me-2"></i>Pedidos
                        </a>
                    </li>
                </ul>
                <div class="px-3 mb-4 mt-4">
                    <h6 class="text-uppercase text-muted small">E-mail</h6>
                </div>
                <ul class="nav flex-column px-3 gap-1">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'email_settings.php' ? 'active' : ''; ?>" href="email_settings.php">
                            <i class="fas fa-cog me-2"></i>Configurações de e-mail
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'email_templates.php' ? 'active' : ''; ?>" href="email_templates.php">
                            <i class="fas fa-envelope-open-text me-2"></i>Templates de e-mail
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

