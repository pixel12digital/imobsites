-- Migration: Add cancel fields to orders table
-- Adiciona campos para registrar cancelamento interno de pedidos
ALTER TABLE orders 
ADD COLUMN canceled_at DATETIME DEFAULT NULL AFTER paid_at,
ADD COLUMN cancel_reason VARCHAR(255) DEFAULT NULL AFTER canceled_at;

CREATE INDEX idx_orders_canceled_at ON orders (canceled_at);


