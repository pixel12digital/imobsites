<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['master_logged_in']) || $_SESSION['master_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo MASTER_PANEL_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f6fa;
        }
        .sidebar {
            min-height: 100vh;
            background: #0a1429;
        }
        .sidebar .nav-link {
            color: #d0d5e4;
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-radius: .375rem;
        }
        .navbar-brand span {
            font-weight: 600;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-layer-group me-2"></i>
            <span><?php echo MASTER_PANEL_NAME; ?></span>
        </a>
        <div class="d-flex align-items-center text-white">
            <span class="me-3">
                <i class="fas fa-user-shield me-2"></i>Master Admin
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">
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
                            <i class="fas fa-building me-2"></i>Tenants
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

