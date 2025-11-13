<?php
/**
 * PlanService
 *
 * Catálogo de planos disponível para o painel master e APIs públicas.
 *
 * Tabela: plans
 * Campos principais:
 *  - code (identificador único)
 *  - name, billing_cycle, months, price_per_month, total_amount
 *  - description_short, features_json (JSON array), is_active, is_featured, sort_order
 *
 * Rotas relacionadas:
 *  - Tela de gestão: /master/planos.php (menu "Planos" no painel master)
 *  - API pública: /api/plans/public-list.php (retorna planos ativos para a página de vendas)
 *
 * Integração com pedidos:
 *  - orders.plan_code deve referenciar plans.code para garantir consistência dos valores.
 *
 * TODO: validar melhor a estrutura de features_json (schema) quando definirmos UI avançada.
 */

declare(strict_types=1);

/**
 * Retorna todos os planos ordenados.
 *
 * @return array<int,array<string,mixed>>
 */
function getAllPlans(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM plans';
    $params = [];

    if ($onlyActive) {
        $sql .= ' WHERE is_active = 1';
    }

    $sql .= ' ORDER BY sort_order ASC, id ASC';

    return fetchAll($sql, $params);
}

/**
 * Busca plano pelo código (unique).
 *
 * @return array<string,mixed>|null
 */
function getPlanByCode(string $code): ?array
{
    if ($code === '') {
        return null;
    }

    $plan = fetch('SELECT * FROM plans WHERE code = ? LIMIT 1', [$code]);

    return $plan ?: null;
}

/**
 * Retorna plano por ID.
 *
 * @return array<string,mixed>|null
 */
function getPlanById(int $id): ?array
{
    $plan = fetch('SELECT * FROM plans WHERE id = ? LIMIT 1', [$id]);

    return $plan ?: null;
}

/**
 * Normaliza payload para inserção/atualização.
 *
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function buildPlanPayload(array $data): array
{
    $now = date('Y-m-d H:i:s');
    $months = isset($data['months']) ? (int)$data['months'] : 1;
    if ($months <= 0) {
        $months = 1;
    }

    $pricePerMonth = isset($data['price_per_month']) ? (float)$data['price_per_month'] : 0.0;
    $totalAmount = $months * $pricePerMonth;

    $features = $data['features_json'] ?? null;
    if (is_array($features)) {
        $featuresJson = json_encode(array_values($features), JSON_UNESCAPED_UNICODE);
    } else {
        $features = is_string($features) ? trim($features) : '';
        if ($features === '') {
            $featuresJson = null;
        } else {
            // Tenta interpretar como JSON já formatado; se falhar, armazena como string simples.
            $decoded = json_decode($features, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $featuresJson = json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE);
            } else {
                $lines = preg_split('/\r\n|\r|\n/', $features);
                $lines = array_filter(array_map('trim', $lines));
                $featuresJson = !empty($lines)
                    ? json_encode(array_values($lines), JSON_UNESCAPED_UNICODE)
                    : null;
            }
        }
    }

    return [
        'code' => strtoupper(trim((string)($data['code'] ?? ''))),
        'name' => trim((string)($data['name'] ?? '')),
        'billing_cycle' => strtolower(trim((string)($data['billing_cycle'] ?? 'mensal'))),
        'months' => $months,
        'price_per_month' => $pricePerMonth,
        'total_amount' => $totalAmount,
        'description_short' => isset($data['description_short']) ? trim((string)$data['description_short']) : null,
        'features_json' => $featuresJson,
        'is_active' => isset($data['is_active']) ? (int)!empty($data['is_active']) : 1,
        'is_featured' => isset($data['is_featured']) ? (int)!empty($data['is_featured']) : 0,
        'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
        'updated_at' => $now,
    ];
}

/**
 * Cria ou atualiza um plano.
 *
 * @param array<string,mixed> $data
 * @param int|null $id
 * @return int Plano ID
 */
function createOrUpdatePlan(array $data, ?int $id = null): int
{
    global $pdo;

    if ($pdo instanceof PDO === false) {
        throw new RuntimeException('Conexão PDO indisponível para PlanService.');
    }

    $payload = buildPlanPayload($data);

    if ($payload['code'] === '' || $payload['name'] === '') {
        throw new InvalidArgumentException('Código e nome do plano são obrigatórios.');
    }

    if ($id === null) {
        $payload['created_at'] = date('Y-m-d H:i:s');

        $planId = insert('plans', $payload);

        return (int)$planId;
    }

    update('plans', $payload, 'id = ?', [$id]);

    return (int)$id;
}

/**
 * Ativa ou desativa um plano.
 */
function togglePlanActive(int $id, bool $active): void
{
    update('plans', [
        'is_active' => $active ? 1 : 0,
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$id]);
}

/**
 * Marca um plano como destaque e remove destaque dos demais.
 */
function markPlanAsFeatured(int $id): void
{
    update('plans', [
        'is_featured' => 0,
        'updated_at' => date('Y-m-d H:i:s'),
    ], '1 = 1', []);

    update('plans', [
        'is_featured' => 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$id]);
}


