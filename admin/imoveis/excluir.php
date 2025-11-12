<?php
// Desabilitar exibição de erros para evitar HTML na resposta JSON
error_reporting(0);
ini_set('display_errors', 0);

// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configurações
require_once '../../config/paths.php';
require_once '../../config/database.php';
require_once '../../config/tenant.php';
require_once '../../config/config.php';

// Iniciar sessão
session_start();

// Definir headers para resposta JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['tenant_id']) || (int)$_SESSION['tenant_id'] !== TENANT_ID) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados JSON da requisição
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !isset($input['confirmacao'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$imovel_id = (int)$input['id'];
$confirmacao = $input['confirmacao'];

// Verificar confirmação
if ($confirmacao !== 'EXCLUIR') {
    echo json_encode(['success' => false, 'message' => 'Confirmação inválida']);
    exit;
}

try {
    // Verificar se a conexão PDO está funcionando
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Conexão com banco de dados não disponível');
    }
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // 1. Buscar informações do imóvel antes de excluir
    $stmt = $pdo->prepare("SELECT titulo FROM imoveis WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$imovel_id, TENANT_ID]);
    $imovel = $stmt->fetch();
    
    if (!$imovel) {
        throw new Exception('Imóvel não encontrado');
    }
    
    // 2. Buscar fotos para excluir arquivos físicos
    $stmt = $pdo->prepare("SELECT arquivo FROM fotos_imovel WHERE imovel_id = ? AND tenant_id = ?");
    $stmt->execute([$imovel_id, TENANT_ID]);
    $fotos = $stmt->fetchAll();
    
    // 3. Excluir interesses relacionados
    $stmt = $pdo->prepare("DELETE FROM interesses WHERE imovel_id = ? AND tenant_id = ?");
    $stmt->execute([$imovel_id, TENANT_ID]);
    
    // 4. Excluir histórico de preços
    $stmt = $pdo->prepare("DELETE FROM historico_precos WHERE imovel_id = ? AND tenant_id = ?");
    $stmt->execute([$imovel_id, TENANT_ID]);
    
    // 5. Excluir características associadas
    $stmt = $pdo->prepare("DELETE FROM imovel_caracteristicas WHERE imovel_id = ? AND tenant_id = ?");
    $stmt->execute([$imovel_id, TENANT_ID]);
    
    // 6. Excluir fotos do banco
    $stmt = $pdo->prepare("DELETE FROM fotos_imovel WHERE imovel_id = ? AND tenant_id = ?");
    $stmt->execute([$imovel_id, TENANT_ID]);
    
    // 7. Excluir o imóvel principal
    $stmt = $pdo->prepare("DELETE FROM imoveis WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$imovel_id, TENANT_ID]);
    
    // 8. Excluir arquivos físicos das fotos
    $upload_dir = '../../uploads/imoveis/' . $imovel_id . '/';
    if (is_dir($upload_dir)) {
        foreach ($fotos as $foto) {
            $arquivo_path = $upload_dir . $foto['arquivo'];
            if (file_exists($arquivo_path)) {
                unlink($arquivo_path);
            }
        }
        
        // Remover diretório vazio
        if (is_dir($upload_dir)) {
            rmdir($upload_dir);
        }
    }
    
    // Confirmar transação
    $pdo->commit();
    
    // Log da exclusão
    error_log("IMOVEL EXCLUIDO: ID {$imovel_id}, Título: {$imovel['titulo']}, Usuário: {$_SESSION['admin_nome']}");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Imóvel excluído com sucesso',
        'imovel_id' => $imovel_id,
        'titulo' => $imovel['titulo']
    ]);
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    $pdo->rollBack();
    
    error_log("ERRO AO EXCLUIR IMOVEL: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao excluir imóvel: ' . $e->getMessage()
    ]);
}
?>
