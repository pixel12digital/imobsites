-- Migration: add activation columns to usuarios for onboarding flow
ALTER TABLE usuarios
    ADD COLUMN activation_token VARCHAR(64) DEFAULT NULL AFTER senha,
    ADD COLUMN activation_expires_at DATETIME DEFAULT NULL AFTER activation_token,
    ADD COLUMN activated_at DATETIME DEFAULT NULL AFTER activation_expires_at;

CREATE INDEX idx_usuarios_activation_token ON usuarios (activation_token);


