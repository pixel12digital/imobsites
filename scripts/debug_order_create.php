<?php
/**
 * Script de diagnóstico para debug da criação de pedidos
 */

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../master/includes/PlanService.php';

$planCode = 'P02_ANUAL';

echo "=== Diagnóstico de Criação de Pedido ===\n\n";

// 1. Verificar se o plano existe
echo "1. Verificando plano '$planCode'...\n";
$plan = getPlanByCode($planCode);

if (!$plan) {
    echo "   ❌ ERRO: Plano '$planCode' não encontrado no banco!\n";
    echo "   Planos disponíveis:\n";
    $allPlans = getAllPlans(false);
    foreach ($allPlans as $p) {
        echo "   - {$p['code']} ({$p['name']}) - Ativo: " . ($p['is_active'] ? 'Sim' : 'Não') . "\n";
    }
    exit(1);
}

echo "   ✅ Plano encontrado!\n";
echo "   - ID: {$plan['id']}\n";
echo "   - Nome: {$plan['name']}\n";
echo "   - Ativo: " . ($plan['is_active'] ? 'Sim' : 'Não') . "\n";
echo "   - billing_cycle: " . ($plan['billing_cycle'] ?? 'NULL') . "\n";
echo "   - billing_mode: " . ($plan['billing_mode'] ?? 'NULL') . "\n";
echo "   - total_amount: " . ($plan['total_amount'] ?? 'NULL') . "\n";
echo "   - price_per_month: " . ($plan['price_per_month'] ?? 'NULL') . "\n";
echo "   - months: " . ($plan['months'] ?? 'NULL') . "\n\n";

// 2. Verificar campos obrigatórios do plano
echo "2. Verificando campos obrigatórios do plano...\n";
$requiredPlanFields = ['billing_cycle', 'total_amount'];
$missingFields = [];

foreach ($requiredPlanFields as $field) {
    if (!isset($plan[$field]) || (is_string($plan[$field]) && trim($plan[$field]) === '')) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    echo "   ❌ ERRO: Campos obrigatórios ausentes no plano: " . implode(', ', $missingFields) . "\n";
    exit(1);
}

echo "   ✅ Todos os campos obrigatórios do plano estão presentes!\n\n";

// 3. Simular dados do pedido
echo "3. Simulando dados do pedido...\n";
$orderData = [
    'customer_name' => 'Teste PIX Sandbox',
    'customer_email' => 'teste.pix@example.com',
    'customer_whatsapp' => '47999999999',
    'plan_code' => $planCode,
    'billing_cycle' => $plan['billing_cycle'],
    'total_amount' => (float)$plan['total_amount'],
    'payment_method' => 'pix',
    'payment_provider' => 'asaas',
    'max_installments' => 1,
    'payment_installments' => 1,
];

echo "   Dados do pedido:\n";
foreach ($orderData as $key => $value) {
    $displayValue = is_null($value) ? 'NULL' : (is_string($value) ? $value : var_export($value, true));
    echo "   - $key: $displayValue\n";
}
echo "\n";

// 4. Verificar estrutura da tabela orders
echo "4. Verificando estrutura da tabela orders...\n";
try {
    $columns = fetchAll("SHOW COLUMNS FROM orders");
    echo "   Colunas da tabela orders:\n";
    foreach ($columns as $col) {
        $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col['Default'] !== null ? " DEFAULT '{$col['Default']}'" : '';
        echo "   - {$col['Field']} ({$col['Type']}) $null$default\n";
    }
} catch (Exception $e) {
    echo "   ❌ ERRO ao verificar estrutura: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Tentar inserir um registro de teste (sem commit)
echo "5. Testando inserção (sem commit)...\n";
try {
    global $pdo;
    $pdo->beginTransaction();
    
    $now = date('Y-m-d H:i:s');
    $testPayload = [
        'customer_name' => $orderData['customer_name'],
        'customer_email' => $orderData['customer_email'],
        'customer_whatsapp' => $orderData['customer_whatsapp'],
        'plan_code' => $orderData['plan_code'],
        'billing_cycle' => $orderData['billing_cycle'],
        'total_amount' => $orderData['total_amount'],
        'payment_method' => $orderData['payment_method'],
        'payment_provider' => $orderData['payment_provider'],
        'max_installments' => $orderData['max_installments'],
        'payment_installments' => $orderData['payment_installments'],
        'status' => 'pending',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    
    $columns = implode(', ', array_keys($testPayload));
    $placeholders = ':' . implode(', :', array_keys($testPayload));
    $sql = "INSERT INTO orders ({$columns}) VALUES ({$placeholders})";
    
    echo "   SQL: " . substr($sql, 0, 200) . "...\n";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($testPayload);
    
    if ($result) {
        $testId = $pdo->lastInsertId();
        echo "   ✅ Inserção de teste bem-sucedida! ID: $testId\n";
        $pdo->rollBack();
        echo "   (Rollback executado - registro não foi salvo)\n";
    } else {
        $errorInfo = $stmt->errorInfo();
        echo "   ❌ ERRO na inserção:\n";
        echo "   - SQLSTATE: " . ($errorInfo[0] ?? 'UNKNOWN') . "\n";
        echo "   - Código: " . ($errorInfo[1] ?? 'UNKNOWN') . "\n";
        echo "   - Mensagem: " . ($errorInfo[2] ?? 'Unknown error') . "\n";
        $pdo->rollBack();
        exit(1);
    }
} catch (PDOException $e) {
    echo "   ❌ ERRO PDO:\n";
    echo "   - Mensagem: " . $e->getMessage() . "\n";
    echo "   - SQLSTATE: " . ($e->errorInfo[0] ?? 'UNKNOWN') . "\n";
    echo "   - Código: " . ($e->errorInfo[1] ?? 'UNKNOWN') . "\n";
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    exit(1);
} catch (Exception $e) {
    echo "   ❌ ERRO: " . $e->getMessage() . "\n";
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    exit(1);
}

echo "\n=== Diagnóstico concluído com sucesso! ===\n";

