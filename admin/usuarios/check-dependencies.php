<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configurações ANTES de iniciar a sessão
require_once '../../config/paths.php';
require_once '../../config/database.php';
require_once '../../config/tenant.php';
require_once '../../config/config.php';

// Verificar se o usuário está logado
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in'] || !isset($_SESSION['tenant_id']) || (int)$_SESSION['tenant_id'] !== TENANT_ID) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Verificar se o usuário tem nível de administrador
if ($_SESSION['admin_nivel'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

// Verificar se o ID do usuário foi fornecido
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do usuário inválido']);
    exit;
}

$user_id = (int)$_GET['user_id'];

try {
    // Verificar se o usuário existe
    $usuario = fetch("SELECT id FROM usuarios WHERE id = ? AND tenant_id = ?", [$user_id, TENANT_ID]);
    if (!$usuario) {
        echo json_encode(['error' => 'Usuário não encontrado']);
        exit;
    }
    
    // Verificar dependências
    $check_sql = "SELECT 
        (SELECT COUNT(*) FROM imoveis WHERE usuario_id = ? AND tenant_id = ?) as total_imoveis,
        (SELECT COUNT(*) FROM clientes WHERE usuario_id = ? AND tenant_id = ?) as total_clientes";
    
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$user_id, TENANT_ID, $user_id, TENANT_ID]);
    $dependencies = $check_stmt->fetch();
    
    $hasDependencies = ($dependencies['total_imoveis'] > 0 || $dependencies['total_clientes'] > 0);
    
    // Retornar resposta em JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'hasDependencies' => $hasDependencies,
        'totalImoveis' => (int)$dependencies['total_imoveis'],
        'totalClientes' => (int)$dependencies['total_clientes'],
        'canDelete' => !$hasDependencies,
        'action' => $hasDependencies ? 'desativar' : 'excluir'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>
