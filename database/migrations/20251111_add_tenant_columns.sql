-- Migration: add tenant_id to existing tables
ALTER TABLE usuarios ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;
ALTER TABLE imoveis ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;
ALTER TABLE fotos_imovel ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;
ALTER TABLE clientes ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;
ALTER TABLE contatos ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;
ALTER TABLE tipos_imovel ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;
ALTER TABLE localizacoes ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;
ALTER TABLE historico_precos ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;
ALTER TABLE interesses ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;
ALTER TABLE imovel_caracteristicas ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;

ALTER TABLE usuarios ADD INDEX idx_usuarios_tenant (tenant_id);
ALTER TABLE imoveis ADD INDEX idx_imoveis_tenant (tenant_id);
ALTER TABLE fotos_imovel ADD INDEX idx_fotos_tenant (tenant_id);
ALTER TABLE clientes ADD INDEX idx_clientes_tenant (tenant_id);
ALTER TABLE contatos ADD INDEX idx_contatos_tenant (tenant_id);
ALTER TABLE tipos_imovel ADD INDEX idx_tipos_tenant (tenant_id);
ALTER TABLE localizacoes ADD INDEX idx_localizacoes_tenant (tenant_id);
ALTER TABLE historico_precos ADD INDEX idx_hist_precos_tenant (tenant_id);
ALTER TABLE interesses ADD INDEX idx_interesses_tenant (tenant_id);
ALTER TABLE imovel_caracteristicas ADD INDEX idx_imovel_caracs_tenant (tenant_id);

