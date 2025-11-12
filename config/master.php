<?php
/**
 * Configurações do painel master (revenda)
 * Ajuste as credenciais conforme necessário.
 */

define('MASTER_PANEL_NAME', 'Painel Master - JTR Platform');

// Credenciais de acesso ao painel master
define('MASTER_EMAIL', 'master@jtrplatform.com');
// Gere o hash com password_hash('sua_senha', PASSWORD_DEFAULT)
define('MASTER_PASSWORD_HASH', '$2y$10$qaeWeUDMSfzRTQMK2PZv7.7VHVXaqRkxwOoHDxmapWBTCn3YRmLhW'); // senha padrão: Master@123

// Função auxiliar para verificar senha master
if (!function_exists('verifyMasterPassword')) {
    function verifyMasterPassword(string $password): bool
    {
        return password_verify($password, MASTER_PASSWORD_HASH);
    }
}

