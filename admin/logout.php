<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configurações ANTES de iniciar a sessão
require_once '../config/paths.php';
require_once '../config/database.php';
require_once '../config/tenant.php';
require_once '../config/config.php';

// Agora iniciar a sessão
session_start();

// Remover variáveis de sessão, incluindo impersonação
unset($_SESSION['tenant_override_id'], $_SESSION['tenant_override_notice']);
$_SESSION = array();

// Se desejar destruir a sessão completamente, apague também o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir a sessão
session_destroy();

// Redirecionar para a página de login
header('Location: login.php');
exit;
?>
