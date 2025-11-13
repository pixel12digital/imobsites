-- Criação do banco de dados de exemplo
CREATE DATABASE IF NOT EXISTS imobsites_template CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE imobsites_template;

-- Tabela de usuários (administradores)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nivel ENUM('admin', 'corretor') DEFAULT 'corretor',
    ativo BOOLEAN DEFAULT TRUE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de tipos de imóveis
CREATE TABLE tipos_imovel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    ativo BOOLEAN DEFAULT TRUE
);

-- Tabela de bairros/cidades
CREATE TABLE localizacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cidade VARCHAR(100) NOT NULL,
    bairro VARCHAR(100),
    estado VARCHAR(2) NOT NULL,
    cep VARCHAR(10)
);

-- Tabela principal de imóveis
CREATE TABLE imoveis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    tipo_id INT NOT NULL,
    localizacao_id INT NOT NULL,
    preco DECIMAL(12,2) NOT NULL,
    area_total DECIMAL(8,2),
    area_construida DECIMAL(8,2),
    quartos INT DEFAULT 0,
    banheiros INT DEFAULT 0,
    vagas INT DEFAULT 0,
    suites INT DEFAULT 0,
    endereco VARCHAR(200),
    numero VARCHAR(20),
    complemento VARCHAR(100),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    status ENUM('disponivel', 'vendido', 'alugado', 'reservado') DEFAULT 'disponivel',
    destaque BOOLEAN DEFAULT FALSE,
    usuario_id INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_id) REFERENCES tipos_imovel(id),
    FOREIGN KEY (localizacao_id) REFERENCES localizacoes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de fotos dos imóveis
CREATE TABLE fotos_imovel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imovel_id INT NOT NULL,
    arquivo VARCHAR(255) NOT NULL,
    legenda VARCHAR(200),
    principal BOOLEAN DEFAULT FALSE,
    ordem INT DEFAULT 0,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (imovel_id) REFERENCES imoveis(id) ON DELETE CASCADE
);

-- Tabela de características dos imóveis
CREATE TABLE caracteristicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    categoria VARCHAR(50),
    ativo BOOLEAN DEFAULT TRUE
);

-- Tabela de relacionamento imóveis-características
CREATE TABLE imovel_caracteristicas (
    imovel_id INT NOT NULL,
    caracteristica_id INT NOT NULL,
    PRIMARY KEY (imovel_id, caracteristica_id),
    FOREIGN KEY (imovel_id) REFERENCES imoveis(id) ON DELETE CASCADE,
    FOREIGN KEY (caracteristica_id) REFERENCES caracteristicas(id)
);

-- Tabela de clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefone VARCHAR(20),
    cpf_cnpj VARCHAR(20),
    tipo ENUM('comprador', 'vendedor', 'ambos') DEFAULT 'comprador',
    observacoes TEXT,
    usuario_id INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de interesses dos clientes
CREATE TABLE interesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    imovel_id INT NOT NULL,
    tipo_interesse ENUM('compra', 'aluguel') NOT NULL,
    observacoes TEXT,
    status ENUM('ativo', 'inativo', 'realizado') DEFAULT 'ativo',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (imovel_id) REFERENCES imoveis(id)
);

-- Tabela de contatos/leads
CREATE TABLE contatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    assunto VARCHAR(200),
    mensagem TEXT NOT NULL,
    origem VARCHAR(50) DEFAULT 'site',
    status ENUM('novo', 'em_contato', 'respondido', 'finalizado') DEFAULT 'novo',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela para histórico de preços
CREATE TABLE historico_precos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imovel_id INT NOT NULL,
    preco_anterior DECIMAL(12,2) NOT NULL,
    preco_novo DECIMAL(12,2) NOT NULL,
    data_alteracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    motivo VARCHAR(255),
    usuario_id INT,
    FOREIGN KEY (imovel_id) REFERENCES imoveis(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Índices para performance
CREATE INDEX idx_historico_imovel ON historico_precos(imovel_id);
CREATE INDEX idx_historico_data ON historico_precos(data_alteracao);

-- Inserir dados iniciais
INSERT INTO usuarios (nome, email, senha, nivel) VALUES 
('Administrador', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO tipos_imovel (nome, descricao) VALUES 
('Casa', 'Casa térrea ou sobrado'),
('Apartamento', 'Apartamento em condomínio'),
('Chácara', 'Chácara ou sítio'),
('Terreno', 'Terreno para construção'),
('Comercial', 'Imóvel comercial'),
('Rural', 'Propriedade rural');

INSERT INTO localizacoes (cidade, bairro, estado) VALUES 
('Cidade Exemplo', 'Bairro Central', 'EX'),
('Cidade Exemplo', 'Bairro Jardim', 'EX'),
('Região Modelo', 'Área Comercial', 'EX'),
('Região Modelo', 'Área Residencial', 'EX'),
('Cidade Referência', 'Centro', 'EX');

INSERT INTO caracteristicas (nome, categoria) VALUES 
('Piscina', 'Lazer'),
('Churrasqueira', 'Lazer'),
('Academia', 'Lazer'),
('Portaria 24h', 'Segurança'),
('Elevador', 'Infraestrutura'),
('Vista para o mar', 'Localização'),
('Mobiliado', 'Mobília'),
('Novo', 'Estado'),
('Reformado', 'Estado');
