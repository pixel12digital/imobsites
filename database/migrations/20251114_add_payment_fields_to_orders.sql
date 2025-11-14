-- Migration: Add subscription and payment method fields to orders table
ALTER TABLE orders 
ADD COLUMN payment_method ENUM('credit_card', 'pix', 'boleto') DEFAULT NULL AFTER payment_provider,
ADD COLUMN payment_installments INT DEFAULT 1 AFTER max_installments,
ADD COLUMN provider_subscription_id VARCHAR(100) DEFAULT NULL AFTER provider_payment_id,
ADD COLUMN subscription_status VARCHAR(50) DEFAULT NULL AFTER status,
ADD COLUMN pix_payload TEXT DEFAULT NULL AFTER payment_url,
ADD COLUMN pix_qr_code_image TEXT DEFAULT NULL AFTER pix_payload,
ADD COLUMN boleto_url VARCHAR(512) DEFAULT NULL AFTER pix_qr_code_image,
ADD COLUMN boleto_barcode VARCHAR(255) DEFAULT NULL AFTER boleto_url,
ADD COLUMN asaas_customer_id VARCHAR(100) DEFAULT NULL AFTER customer_email,
ADD COLUMN customer_cpf_cnpj VARCHAR(20) DEFAULT NULL AFTER customer_whatsapp;

CREATE INDEX idx_orders_subscription ON orders (provider_subscription_id);
CREATE INDEX idx_orders_customer_asaas ON orders (asaas_customer_id);
CREATE INDEX idx_orders_payment_method ON orders (payment_method);

