<?php
/**
 * API pública - listagem de planos ativos.
 *
 * TODO: ajustar política de CORS e autenticação leve quando integrarmos com o site oficial.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../master/includes/PlanService.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $plans = getAllPlans(true);

    $response = array_map(static function (array $plan): array {
        $features = [];
        if (!empty($plan['features_json'])) {
            $decoded = json_decode((string)$plan['features_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $features = array_values(array_map('trim', $decoded));
            }
        }

        return [
            'code' => $plan['code'],
            'name' => $plan['name'],
            'billing_cycle' => $plan['billing_cycle'],
            'months' => (int)$plan['months'],
            'price_per_month' => (float)$plan['price_per_month'],
            'total_amount' => (float)$plan['total_amount'],
            'description_short' => $plan['description_short'],
            'is_featured' => (bool)$plan['is_featured'],
            'sort_order' => (int)$plan['sort_order'],
            'features' => $features,
            'updated_at' => $plan['updated_at'],
        ];
    }, $plans);

    echo json_encode([
        'success' => true,
        'plans' => $response,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[api.plans.public-list] Erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível carregar os planos.',
    ]);
}

