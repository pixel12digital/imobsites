<?php
// Endpoint AJAX para buscar bairros por cidade
header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/tenant.php';
require_once __DIR__ . '/config/config.php';

try {
    $cidade = isset($_GET['cidade']) ? trim($_GET['cidade']) : '';

    if ($cidade === '') {
        echo json_encode([
            'success' => true,
            'bairros' => []
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT DISTINCT bairro 
        FROM localizacoes 
        WHERE cidade = ?
          AND tenant_id = ?
          AND bairro IS NOT NULL
          AND bairro <> ''
        ORDER BY bairro
    ");
    $stmt->execute([$cidade, TENANT_ID]);

    $bairros = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'bairros' => $bairros
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

