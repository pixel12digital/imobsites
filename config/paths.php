<?php
/**
 * Configuração de Caminhos - imobsites
 * Centraliza todas as definições de paths para funcionar em qualquer ambiente
 */

// Detectar o caminho raiz de forma mais robusta
function detectRootPath() {
    // Tentar diferentes métodos para detectar o caminho raiz
    $possible_paths = [
        // Método 1: Usar SCRIPT_NAME (mais confiável)
        dirname($_SERVER['SCRIPT_NAME']),
        // Método 2: Usar REQUEST_URI
        dirname($_SERVER['REQUEST_URI']),
        // Método 3: Usar PHP_SELF
        dirname($_SERVER['PHP_SELF']),
        // Método 4: Fallback para diretório atual
        '.'
    ];
    
    foreach ($possible_paths as $path) {
        if ($path && $path !== '.' && $path !== '/') {
            return $path;
        }
    }
    
    // Se nada funcionar, usar diretório atual
    return '.';
}

// Definir constantes de caminho de forma relativa
$root_path = detectRootPath();
define('ROOT_PATH', $root_path);
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('PAGES_PATH', ROOT_PATH . '/pages');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('DATABASE_PATH', ROOT_PATH . '/database');
define('MASTER_PATH', ROOT_PATH . '/master');

// Função para obter caminho absoluto (usar com cuidado)
function getAbsolutePath($relativePath = '') {
    // Em produção, preferir caminhos relativos
    if (!isDevelopment()) {
        return ROOT_PATH . '/' . ltrim($relativePath, '/');
    }
    
    // Em desenvolvimento, usar caminho absoluto do sistema
    $current_dir = __DIR__;
    $parent_dir = dirname($current_dir);
    return $parent_dir . '/' . ltrim($relativePath, '/');
}

// Função para obter caminho relativo à raiz do site
function getRelativePath($path = '') {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path_info = pathinfo($script_name);
    $base_path = $path_info['dirname'];
    
    // Detectar se estamos em uma subpágina (como pages/, admin/, etc.)
    $is_subpage = false;
    
    // Verificar se estamos em uma subpágina
    if (strpos($script_name, '/pages/') !== false) {
        $is_subpage = true;
    } elseif (strpos($script_name, '/admin/') !== false) {
        $is_subpage = true;
    } elseif (strpos($script_name, '/master/') !== false) {
        $is_subpage = true;
    }
    
    // Se estiver na raiz, retorna apenas o path
    if ($base_path === '/') {
        return '/' . ltrim($path, '/');
    }
    
    // Se estivermos em uma subpágina, precisamos voltar um nível
    if ($is_subpage) {
        // Para assets, sempre voltar um nível
        if (strpos($path, 'assets/') === 0) {
            return '../' . $path;
        }
        // Para outros caminhos, usar lógica normal
        return $base_path . '/' . ltrim($path, '/');
    }
    
    return $base_path . '/' . ltrim($path, '/');
}

// Função para obter URL base
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path_info = pathinfo($script_name);
    $base_path = $path_info['dirname'];
    
    // Detectar se estamos em uma subpágina
    $is_subpage = false;
    if (strpos($script_name, '/pages/') !== false || strpos($script_name, '/admin/') !== false || strpos($script_name, '/master/') !== false) {
        $is_subpage = true;
    }
    
    // Se estiver na raiz, retorna apenas o protocolo + host
    if ($base_path === '/') {
        return $protocol . '://' . $host;
    }
    
    // Se estivermos em uma subpágina, voltar um nível
    if ($is_subpage) {
        $path_parts = explode('/', trim($base_path, '/'));
        if (count($path_parts) > 1) {
            array_pop($path_parts); // Remove o último elemento (pages ou admin)
            $corrected_base = '/' . implode('/', $path_parts);
            return $protocol . '://' . $host . $corrected_base;
        }
    }
    
    return $protocol . '://' . $host . $base_path;
}

// Função para obter caminho de assets
function getAssetPath($assetPath) {
    $path = getRelativePath('assets/' . ltrim($assetPath, '/'));
    
    // Debug: log do caminho gerado (apenas em desenvolvimento)
    if (isDevelopment() && isDebugMode()) {
        error_log('[imobsites] Asset path: ' . $assetPath . ' -> ' . $path);
    }
    
    return $path;
}

// Função para debug de caminhos
function debugPaths() {
    if (!isDevelopment()) {
        return false;
    }
    
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path_info = pathinfo($script_name);
    $base_path = $path_info['dirname'];
    
    $debug_info = [
        'script_name' => $script_name,
        'base_path' => $base_path,
        'is_subpage' => strpos($script_name, '/pages/') !== false || strpos($script_name, '/admin/') !== false,
        'asset_path_css' => getAssetPath('css/style.css'),
        'asset_path_js' => getAssetPath('js/main.js'),
        'relative_path' => getRelativePath(),
        'base_url' => getBaseUrl()
    ];
    
    return $debug_info;
}

// Função para obter caminho de uploads
function getUploadPath($filename) {
    if (empty($filename)) {
        return false;
    }
    
    // Verificar se deve usar uploads remotos
    if (function_exists('shouldUseRemoteUploads') && shouldUseRemoteUploads()) {
        if (function_exists('getRemoteUploadUrl')) {
            return getRemoteUploadUrl($filename);
        }
    }
    
    // Fallback para verificação local
    // Verificar se estamos em ambiente web (não CLI)
    if (php_sapi_name() !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
        $absolutePath = getAbsolutePath('uploads/' . ltrim($filename, '/'));
        if (file_exists($absolutePath)) {
            return getRelativePath('uploads/' . ltrim($filename, '/'));
        }
    }
    
    return false; // Retorna false se a imagem não existir
}

// Função para verificar se uma imagem existe
function imageExists($filename) {
    if (empty($filename)) {
        return false;
    }
    
    // Se estiver usando uploads remotos, considerar que a imagem existe
    if (function_exists('shouldUseRemoteUploads') && shouldUseRemoteUploads()) {
        return true;
    }
    
    // Verificação local
    $absolutePath = getAbsolutePath('uploads/' . ltrim($filename, '/'));
    return file_exists($absolutePath);
}

// Função para obter caminho de páginas
function getPagePath($page, $params = []) {
    // Mapear páginas para arquivos corretos
    $page_mapping = [
        'imovel' => 'imovel',
        'home' => 'home',
        'imoveis' => 'imoveis',
        'sobre' => 'sobre',
        'contato' => 'contato',
        'admin' => 'admin',
        'comparador' => 'comparador',
        'historico-precos' => 'historico-precos',
        'filtros-avancados' => 'filtros-avancados'
    ];
    
    // Obter o nome correto da página
    $correct_page = $page_mapping[$page] ?? $page;
    
    // Se estamos em uma subpágina (pages/, admin/), usar caminho relativo
    $script_name = $_SERVER['SCRIPT_NAME'];
    if (strpos($script_name, '/pages/') !== false || strpos($script_name, '/admin/') !== false) {
        // Estamos em uma subpágina, usar caminho relativo
        if ($page === 'imovel') {
            // Para imóveis, voltar dois níveis e usar o sistema de roteamento
            $query = http_build_query($params);
            $url = '../../index.php?page=' . $correct_page;
            if (!empty($query)) {
                $url .= '&' . $query;
            }
            return $url;
        } else {
            // Para outras páginas, voltar um nível e usar o sistema de roteamento
            $query = http_build_query($params);
            $url = '../index.php?page=' . $correct_page;
            if (!empty($query)) {
                $url .= '&' . $query;
            }
            return $url;
        }
    } else {
        // Estamos na raiz, usar o sistema de roteamento normal
        $query = http_build_query($params);
        $url = 'index.php?page=' . $correct_page;
        if (!empty($query)) {
            $url .= '&' . $query;
        }
        return $url;
    }
}

// Função para verificar se o arquivo existe
function fileExists($path) {
    return file_exists(getAbsolutePath($path));
}

// Função para incluir arquivo com caminho absoluto
function includeFile($relativePath) {
    // Em produção, usar caminhos relativos
    if (!isDevelopment()) {
        $filePath = ROOT_PATH . '/' . ltrim($relativePath, '/');
        if (file_exists($filePath)) {
            // Usar require para garantir que as variáveis sejam passadas corretamente
            // E garantir que as variáveis globais sejam acessíveis
            global $pdo;
            return require $filePath;
        }
        return false;
    }
    
    // Em desenvolvimento, usar caminho absoluto
    $absolutePath = getAbsolutePath($relativePath);
    if (file_exists($absolutePath)) {
        // Usar require para garantir que as variáveis sejam passadas corretamente
        // E garantir que as variáveis globais sejam acessíveis
        global $pdo;
        return require $absolutePath;
    }
    return false;
}

// Função para verificar ambiente
function isDevelopment() {
    return in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']) || 
           strpos($_SERVER['HTTP_HOST'], '.local') !== false ||
           strpos($_SERVER['HTTP_HOST'], '.test') !== false;
}

// Função para obter configuração de debug
function isDebugMode() {
    return isDevelopment() && (isset($_GET['debug']) || isset($_COOKIE['debug_mode']));
}

// Função para log de erros (apenas em desenvolvimento)
function logError($message, $context = []) {
    if (isDevelopment()) {
        error_log('[imobsites] ' . $message . ' ' . json_encode($context));
    }
}

// Função para obter informações do ambiente
function getEnvironmentInfo() {
    return [
        'is_development' => isDevelopment(),
        'is_debug' => isDebugMode(),
        'root_path' => ROOT_PATH,
        'base_url' => getBaseUrl(),
        'relative_path' => getRelativePath(),
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ];
}
?>
