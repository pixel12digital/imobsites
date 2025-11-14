-- Migration: Add billing_mode and max_installments to plans table
ALTER TABLE plans 
ADD COLUMN billing_mode ENUM('recurring_monthly', 'prepaid_parceled') NOT NULL DEFAULT 'prepaid_parceled' AFTER billing_cycle,
ADD COLUMN max_installments INT NOT NULL DEFAULT 1 AFTER total_amount;

-- Update existing plans to have default values
UPDATE plans SET billing_mode = 'prepaid_parceled' WHERE billing_mode IS NULL;
UPDATE plans SET max_installments = 1 WHERE max_installments IS NULL OR max_installments <= 0;

CREATE INDEX idx_plans_billing_mode ON plans (billing_mode);

