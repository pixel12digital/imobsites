-- Migration: Add payment details fields to orders table
-- Adiciona campo boleto_line (linha digitável) e ajusta pix_qr_code_image para LONGTEXT
-- Esses campos enriquecem os dados de pagamento para PIX e Boleto no checkout

-- Verificar e modificar pix_qr_code_image para LONGTEXT (caso precise armazenar base64 completo)
-- Nota: Se a coluna já for LONGTEXT, o comando será ignorado
ALTER TABLE orders
MODIFY COLUMN pix_qr_code_image LONGTEXT NULL COMMENT 'QR Code PIX em base64 ou URL da imagem';

-- Adiciona campo boleto_line (linha digitável do boleto)
ALTER TABLE orders
ADD COLUMN boleto_line VARCHAR(255) NULL COMMENT 'Linha digitável do boleto (identificationField)' AFTER boleto_barcode;

-- Índice para facilitar buscas por boleto_line (opcional, mas útil)
CREATE INDEX idx_orders_boleto_line ON orders (boleto_line);

