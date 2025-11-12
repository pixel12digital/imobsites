<?php
// Arquivo para salvar localização via AJAX
header('Content-Type: application/json');

// Carregar configurações
$config_path = dirname(__DIR__) . '/../config/';
require_once $config_path . 'paths.php';
require_once $config_path . 'database.php';
require_once $config_path . 'tenant.php';
require_once $config_path . 'config.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['tenant_id']) || (int)$_SESSION['tenant_id'] !== TENANT_ID) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Validar dados obrigatórios
    $cidade = cleanInput($_POST['cidade']);
    $estado = cleanInput($_POST['estado']);
    $bairro = !empty($_POST['bairro']) ? cleanInput($_POST['bairro']) : null;
    $cep = !empty($_POST['cep']) ? cleanInput($_POST['cep']) : null;
    
    // Validações
    if (empty($cidade)) {
        throw new Exception('Cidade é obrigatória.');
    }
    
    if (empty($estado)) {
        throw new Exception('Estado é obrigatório.');
    }
    
    // Validar CEP se fornecido
    if ($cep && !preg_match('/^\d{5}-?\d{3}$/', $cep)) {
        throw new Exception('CEP deve estar no formato 00000-000 ou 00000000.');
    }
    
    // Verificar se já existe uma localização com cidade + bairro + estado
    $existing_location = fetch(
        "SELECT id FROM localizacoes WHERE cidade = ? AND bairro = ? AND estado = ? AND tenant_id = ?", 
        [$cidade, $bairro, $estado, TENANT_ID]
    );
    
    if ($existing_location) {
        echo json_encode([
            'success' => false, 
            'message' => 'Já existe uma localização com esta cidade, bairro e estado.',
            'localizacao_id' => $existing_location['id']
        ]);
        exit;
    }
    
    // Preparar dados para inserção
    $dados_localizacao = [
        'cidade' => $cidade,
        'bairro' => $bairro,
        'estado' => $estado,
        'cep' => $cep,
        'tenant_id' => TENANT_ID
    ];
    
    // Inserir localização
    $localizacao_id = insert("localizacoes", $dados_localizacao);
    
    if ($localizacao_id) {
        echo json_encode([
            'success' => true, 
            'message' => 'Localização cadastrada com sucesso!',
            'localizacao_id' => $localizacao_id,
            'localizacao' => $dados_localizacao
        ]);
    } else {
        throw new Exception('Erro ao cadastrar localização.');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

