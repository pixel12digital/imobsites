-- Migration: add reminder control fields to orders table
-- Adiciona campos para controle de lembretes de cobrança pendente
-- Esses campos serão usados na Fase 2 para implementar o sistema de lembretes automáticos

-- Adiciona campos para controle de lembretes
ALTER TABLE orders
ADD COLUMN first_reminder_sent_at DATETIME NULL COMMENT 'Data/hora do primeiro lembrete enviado',
ADD COLUMN last_reminder_sent_at DATETIME NULL COMMENT 'Data/hora do último lembrete enviado',
ADD COLUMN reminder_count INT DEFAULT 0 COMMENT 'Contador de lembretes enviados';

-- Índice para consultas de pedidos pendentes que precisam de lembrete
-- Útil para queries como: SELECT * FROM orders WHERE status = 'pending' AND last_reminder_sent_at IS NULL ORDER BY created_at
CREATE INDEX idx_orders_reminder ON orders (status, created_at, last_reminder_sent_at);

