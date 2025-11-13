<?php
/**
 * API pública - listagem de planos ativos.
 *
 * Endpoint consumido pela landing (Repo A). Não depende de sessão do painel
 * e deve retornar somente JSON.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não suportado. Utilize GET.',
    ]);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../master/includes/PlanService.php';

try {
    $plans = getAllPlans(true);

    $response = array_map(
        static function (array $plan): array {
            $features = [];
            if (!empty($plan['features_json'])) {
                $decoded = json_decode((string)$plan['features_json'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $features = array_values(array_map('trim', $decoded));
                } else {
                    error_log('[api.plans.public-list] features_json inválido para plano ' . ($plan['code'] ?? 'sem_code'));
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
        },
        $plans
    );

    $json = json_encode(
        [
            'success' => true,
            'plans' => $response,
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    echo $json;
} catch (Throwable $e) {
    error_log('[api.plans.public-list] Erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível carregar os planos.',
    ]);
}

