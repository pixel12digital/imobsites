<?php
// Configurações de sessão (DEVEM vir ANTES de session_start())
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 86400); // 24 horas
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // 0 para desenvolvimento local, 1 para produção
    ini_set('session.use_strict_mode', 1);
}

// Configurações gerais do sistema
define('SITE_NAME', tenantSetting('site_name', ''));
define('SITE_URL', ''); // Será detectado automaticamente
define('SITE_EMAIL', tenantSetting('site_email', ''));

define('SITE_TAGLINE', tenantSetting('site_tagline', 'Personalize o slogan da sua imobiliária.'));
define('SITE_META_DESCRIPTION', tenantSetting('meta_description', 'Configure a descrição do seu portal imobiliário.'));
define('SITE_META_KEYWORDS', tenantSetting('meta_keywords', ''));
define('SITE_META_AUTHOR', tenantSetting('meta_author', SITE_NAME ?: ''));

// Números de telefone específicos por tipo de operação
define('PHONE_VENDA', tenantSetting('phone_venda', ''));
define('PHONE_LOCACAO', tenantSetting('phone_locacao', ''));
define('PHONE_WHATSAPP_VENDA', tenantSetting('whatsapp_venda', ''));
define('PHONE_WHATSAPP_LOCACAO', tenantSetting('whatsapp_locacao', ''));

// Configurações de upload
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Definir extensões permitidas como array
if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', serialize(['jpg', 'jpeg', 'png', 'gif', 'webp']));
}

// Função para obter extensões permitidas
function getAllowedExtensions() {
    if (defined('ALLOWED_EXTENSIONS')) {
        return unserialize(ALLOWED_EXTENSIONS);
    }
    return ['jpg', 'jpeg', 'png', 'gif', 'webp']; // fallback
}

// Configurações de paginação
define('ITEMS_PER_PAGE', 12);

// Função para limpar input
if (!function_exists('cleanInput')) {
    function cleanInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        // Usar ENT_QUOTES para codificar aspas corretamente
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

// Função para formatar preço
function formatPrice($price) {
    return 'R$ ' . number_format($price, 2, ',', '.');
}

// Função para converter preço do formato brasileiro para número
function convertBrazilianPriceToNumber($formattedPrice) {
    // Remover "R$ " se existir
    $cleanValue = str_replace('R$ ', '', $formattedPrice);
    // Remover pontos e substituir vírgula por ponto
    $cleanValue = str_replace('.', '', $cleanValue);
    $cleanValue = str_replace(',', '.', $cleanValue);
    return (float)$cleanValue;
}

// Função para formatar data
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Função para gerar slug
function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}

// ============================================================================
// CONFIGURAÇÃO DE IMAGENS REMOTAS
// ============================================================================

define('REMOTE_UPLOADS_BASE_URL', rtrim(tenantSetting('uploads_base_url', ''), '/'));

function getRemoteUploadUrl($image_path) {
    if (empty(REMOTE_UPLOADS_BASE_URL)) {
        return null;
    }

    // Se o caminho já é uma URL completa, retornar como está
    if (filter_var($image_path, FILTER_VALIDATE_URL)) {
        return $image_path;
    }
    
    $clean_path = ltrim(str_replace('\\', '/', preg_replace('/^uploads\//', '', $image_path)), '/');
    return REMOTE_UPLOADS_BASE_URL . '/' . $clean_path;
}

function shouldUseRemoteUploads() {
    return !empty(REMOTE_UPLOADS_BASE_URL);
}
