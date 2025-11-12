<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

require_once 'config/paths.php';
require_once 'config/master.php';

if (php_sapi_name() !== 'cli' && function_exists('isMasterDomain') && isMasterDomain()) {
    header('Location: /master/login.php');
    exit;
}

require_once 'config/database.php';
require_once 'config/tenant.php';
require_once 'config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Roteamento simples
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Verificar se é uma página administrativa e redirecionar se necessário
if ($page === 'admin') {
    header('Location: admin/login.php');
    exit;
}

// Header
includeFile('includes/header.php');

// Conteúdo da página
switch($page) {
    case 'home':
        includeFile('pages/home.php');
        break;
    case 'imoveis':
        includeFile('pages/imoveis.php');
        break;
    case 'imovel':
        includeFile('pages/imovel-detalhes.php');
        break;
    case 'sobre':
        includeFile('pages/sobre.php');
        break;
    case 'contato':
        includeFile('pages/contato.php');
        break;
    case 'comparador':
        includeFile('pages/comparador.php');
        break;
    case 'historico-precos':
        includeFile('pages/historico-precos.php');
        break;
    case 'filtros-avancados':
        includeFile('pages/filtros-avancados.php');
        break;
    default:
        includeFile('pages/home.php');
}

// Footer
includeFile('includes/footer.php');

// Finalizar output buffering
ob_end_flush();
?>
