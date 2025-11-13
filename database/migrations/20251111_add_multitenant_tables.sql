-- Migration: Multi-tenant base tables
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    status ENUM('active','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tenant_domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    domain VARCHAR(191) NOT NULL UNIQUE,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tenant_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    site_name VARCHAR(150) DEFAULT 'Portal Imobiliário',
    site_email VARCHAR(150) DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#023A8D',
    secondary_color VARCHAR(7) DEFAULT '#F7931E',
    phone_venda VARCHAR(30) DEFAULT NULL,
    phone_locacao VARCHAR(30) DEFAULT NULL,
    whatsapp_venda VARCHAR(30) DEFAULT NULL,
    whatsapp_locacao VARCHAR(30) DEFAULT NULL,
    facebook_url VARCHAR(255) DEFAULT NULL,
    instagram_url VARCHAR(255) DEFAULT NULL,
    linkedin_url VARCHAR(255) DEFAULT NULL,
    custom_domain VARCHAR(191) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

INSERT INTO tenants (name, slug) VALUES ('Tenant Padrão', 'tenant-padrao');
INSERT INTO tenant_domains (tenant_id, domain, is_primary) VALUES (1, 'localhost', 1);
INSERT INTO tenant_settings (tenant_id) VALUES (1);

