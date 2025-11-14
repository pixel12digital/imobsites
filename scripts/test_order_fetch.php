<?php
require_once __DIR__ . '/../config/database.php';

$order = fetch('SELECT * FROM orders WHERE id = 18');

echo "=== Dados do pedido ID 18 ===\n";
echo "CPF/CNPJ: " . ($order['customer_cpf_cnpj'] ?? 'NULL') . "\n";
echo "Campos disponíveis: " . implode(', ', array_keys($order)) . "\n";

