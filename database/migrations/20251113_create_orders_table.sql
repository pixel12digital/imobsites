-- Migration: create orders table for checkout flow
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(191) NOT NULL,
    customer_email VARCHAR(191) NOT NULL,
    customer_whatsapp VARCHAR(191) DEFAULT NULL,
    plan_code VARCHAR(50) NOT NULL,
    billing_cycle VARCHAR(20) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    max_installments INT DEFAULT 1,
    status ENUM('pending','paid','canceled','expired') DEFAULT 'pending',
    payment_provider VARCHAR(50) NOT NULL,
    provider_payment_id VARCHAR(100) DEFAULT NULL,
    payment_url VARCHAR(512) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    tenant_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_orders_status ON orders (status);
CREATE INDEX idx_orders_provider_payment ON orders (provider_payment_id);
CREATE INDEX idx_orders_tenant ON orders (tenant_id);


