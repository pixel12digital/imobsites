<?php
/**
 * Script para verificar se CPF está sendo salvo no banco
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Verificando últimos pedidos e CPF ===\n\n";

$orders = fetchAll("SELECT id, customer_name, customer_email, customer_cpf_cnpj, payment_method, created_at FROM orders ORDER BY id DESC LIMIT 5");

foreach ($orders as $order) {
    echo "ID: {$order['id']}\n";
    echo "Nome: {$order['customer_name']}\n";
    echo "Email: {$order['customer_email']}\n";
    echo "CPF/CNPJ: " . ($order['customer_cpf_cnpj'] ?? 'NULL') . "\n";
    echo "Método: " . ($order['payment_method'] ?? 'NULL') . "\n";
    echo "Criado: {$order['created_at']}\n";
    echo "---\n\n";
}

