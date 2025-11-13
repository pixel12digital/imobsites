-- Migration: create plans catalog for checkout and master management
CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    billing_cycle VARCHAR(20) NOT NULL,
    months INT NOT NULL DEFAULT 1,
    price_per_month DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description_short VARCHAR(191) DEFAULT NULL,
    features_json TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_plans_active ON plans (is_active, sort_order);
CREATE INDEX idx_plans_billing_cycle ON plans (billing_cycle);


