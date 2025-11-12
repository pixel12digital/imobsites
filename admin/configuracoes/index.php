<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configuraÃ§Ãµes ANTES de iniciar a sessÃ£o
require_once '../../config/paths.php';
require_once '../../config/database.php';
require_once '../../config/config.php';

// Agora iniciar a sessÃ£o
session_start();

// Verificar se o usuÃ¡rio estÃ¡ logado
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: ../login.php');
    exit;
}

// Verificar se o usuÃ¡rio tem nÃ­vel de administrador
if ($_SESSION['admin_nivel'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';

// Processar formulÃ¡rio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_general'])) {
        // Atualizar configuraÃ§Ãµes gerais
        $site_name = cleanInput($_POST['site_name']);
        $site_description = cleanInput($_POST['site_description']);
        $site_keywords = cleanInput($_POST['site_keywords']);
        $site_author = cleanInput($_POST['site_author']);
        
        if (empty($site_name)) {
            $error = 'O nome do site Ã© obrigatÃ³rio.';
        } else {
            // Atualizar configuraÃ§Ãµes na tabela configuracoes
            $configs = [
                'site_name' => $site_name,
                'site_description' => $site_description,
                'site_keywords' => $site_keywords,
                'site_author' => $site_author
            ];
            
            $success_count = 0;
            foreach ($configs as $key => $value) {
                // Verificar se a configuraÃ§Ã£o jÃ¡ existe
                $existing = fetch('configuracoes', 'chave = ?', [$key]);
                if ($existing) {
                    if (update('configuracoes', ['valor' => $value], 'chave = ?', [$key])) {
                        $success_count++;
                    }
                } else {
                    if (insert('configuracoes', ['chave' => $key, 'valor' => $value])) {
                        $success_count++;
                    }
                }
            }
            
            if ($success_count === count($configs)) {
                $success = 'ConfiguraÃ§Ãµes gerais atualizadas com sucesso!';
            } else {
                $error = 'Erro ao atualizar algumas configuraÃ§Ãµes.';
            }
        }
    } elseif (isset($_POST['update_contact'])) {
        // Atualizar informaÃ§Ãµes de contato
        $company_address = cleanInput($_POST['company_address']);
        $company_phone = cleanInput($_POST['company_phone']);
        $company_email = cleanInput($_POST['company_email']);
        $company_whatsapp = cleanInput($_POST['company_whatsapp']);
        $company_instagram = cleanInput($_POST['company_instagram']);
        $company_facebook = cleanInput($_POST['company_facebook']);
        
        $contact_configs = [
            'company_address' => $company_address,
            'company_phone' => $company_phone,
            'company_email' => $company_email,
            'company_whatsapp' => $company_whatsapp,
            'company_instagram' => $company_instagram,
            'company_facebook' => $company_facebook
        ];
        
        $success_count = 0;
        foreach ($contact_configs as $key => $value) {
            $existing = fetch('configuracoes', 'chave = ?', [$key]);
            if ($existing) {
                if (update('configuracoes', ['valor' => $value], 'chave = ?', [$key])) {
                    $success_count++;
                }
            } else {
                if (insert('configuracoes', ['chave' => $key, 'valor' => $value])) {
                    $success_count++;
                }
            }
        }
        
        if ($success_count === count($contact_configs)) {
            $success = 'InformaÃ§Ãµes de contato atualizadas com sucesso!';
        } else {
            $error = 'Erro ao atualizar algumas informaÃ§Ãµes de contato.';
        }
    } elseif (isset($_POST['update_system'])) {
        // Atualizar configuraÃ§Ãµes do sistema
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $max_upload_size = (int)$_POST['max_upload_size'];
        $items_per_page = (int)$_POST['items_per_page'];
        $enable_notifications = isset($_POST['enable_notifications']) ? 1 : 0;
        
        if ($max_upload_size < 1 || $max_upload_size > 100) {
            $error = 'O tamanho mÃ¡ximo de upload deve estar entre 1 e 100 MB.';
        } elseif ($items_per_page < 5 || $items_per_page > 100) {
            $error = 'Itens por pÃ¡gina deve estar entre 5 e 100.';
        } else {
            $system_configs = [
                'maintenance_mode' => $maintenance_mode,
                'max_upload_size' => $max_upload_size,
                'items_per_page' => $items_per_page,
                'enable_notifications' => $enable_notifications
            ];
            
            $success_count = 0;
            foreach ($system_configs as $key => $value) {
                $existing = fetch('configuracoes', 'chave = ?', [$key]);
                if ($existing) {
                    if (update('configuracoes', ['valor' => $value], 'chave = ?', [$key])) {
                        $success_count++;
                    }
                } else {
                    if (insert('configuracoes', ['chave' => $key, 'valor' => $value])) {
                        $success_count++;
                    }
                }
            }
            
            if ($success_count === count($system_configs)) {
                $success = 'ConfiguraÃ§Ãµes do sistema atualizadas com sucesso!';
            } else {
                $error = 'Erro ao atualizar algumas configuraÃ§Ãµes do sistema.';
            }
        }
    }
}

// Buscar configuraÃ§Ãµes existentes
function getConfig($key, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['valor'] : $default;
}

// Buscar todas as configuraÃ§Ãµes
$configuracoes = [];
$stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
while ($row = $stmt->fetch()) {
    $configuracoes[$row['chave']] = $row['valor'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConfiguraÃ§Ãµes - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../imoveis/">
                                <i class="fas fa-home me-2"></i>
                                ImÃ³veis
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../usuarios/">
                                <i class="fas fa-users me-2"></i>
                                UsuÃ¡rios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../contatos/">
                                <i class="fas fa-envelope me-2"></i>
                                Contatos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="../configuracoes/">
                                <i class="fas fa-cog me-2"></i>
                                ConfiguraÃ§Ãµes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../perfil.php">
                                <i class="fas fa-user me-2"></i>
                                Meu Perfil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Sair
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cog me-2"></i>
                        ConfiguraÃ§Ãµes do Sistema
                    </h1>
                </div>

                <!-- Alertas -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Abas de ConfiguraÃ§Ã£o -->
                <ul class="nav nav-tabs" id="configTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                            <i class="fas fa-globe me-2"></i>
                            Geral
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                            <i class="fas fa-address-book me-2"></i>
                            Contato
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                            <i class="fas fa-server me-2"></i>
                            Sistema
                        </button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="configTabsContent">
                    <!-- ConfiguraÃ§Ãµes Gerais -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-globe me-2"></i>
                                    ConfiguraÃ§Ãµes Gerais do Site
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="site_name" class="form-label">
                                                <i class="fas fa-tag me-1"></i>
                                                Nome do Site *
                                            </label>
                                            <input type="text" class="form-control" id="site_name" name="site_name" 
                                                   value="<?php echo htmlspecialchars($configuracoes['site_name'] ?? SITE_NAME); ?>" 
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="site_author" class="form-label">
                                                <i class="fas fa-user me-1"></i>
                                                Autor do Site
                                            </label>
                                            <input type="text" class="form-control" id="site_author" name="site_author" 
                                                   value="<?php echo htmlspecialchars($configuracoes['site_author'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="site_description" class="form-label">
                                            <i class="fas fa-align-left me-1"></i>
                                            DescriÃ§Ã£o do Site
                                        </label>
                                        <textarea class="form-control" id="site_description" name="site_description" rows="3" 
                                                  placeholder="DescriÃ§Ã£o que aparecerÃ¡ nos motores de busca..."><?php echo htmlspecialchars($configuracoes['site_description'] ?? ''); ?></textarea>
                                        <div class="form-text">MÃ¡ximo 160 caracteres para SEO</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="site_keywords" class="form-label">
                                            <i class="fas fa-key me-1"></i>
                                            Palavras-chave
                                        </label>
                                        <input type="text" class="form-control" id="site_keywords" name="site_keywords" 
                                               value="<?php echo htmlspecialchars($configuracoes['site_keywords'] ?? ''); ?>"
                                               placeholder="palavra1, palavra2, palavra3">
                                        <div class="form-text">Separe as palavras-chave por vÃ­rgula</div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" name="update_general" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Salvar ConfiguraÃ§Ãµes Gerais
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- InformaÃ§Ãµes de Contato -->
                    <div class="tab-pane fade" id="contact" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-address-book me-2"></i>
                                    InformaÃ§Ãµes de Contato da Empresa
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="company_address" class="form-label">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                EndereÃ§o
                                            </label>
                                            <textarea class="form-control" id="company_address" name="company_address" rows="3"
                                                      placeholder="EndereÃ§o completo da empresa..."><?php echo htmlspecialchars($configuracoes['company_address'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="company_phone" class="form-label">
                                                <i class="fas fa-phone me-1"></i>
                                                Telefone
                                            </label>
                                            <input type="tel" class="form-control" id="company_phone" name="company_phone" 
                                                   value="<?php echo htmlspecialchars($configuracoes['company_phone'] ?? ''); ?>"
                                                   placeholder="(11) 99999-9999">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="company_email" class="form-label">
                                                <i class="fas fa-envelope me-1"></i>
                                                Email
                                            </label>
                                            <input type="email" class="form-control" id="company_email" name="company_email" 
                                                   value="<?php echo htmlspecialchars($configuracoes['company_email'] ?? ''); ?>"
                                                   placeholder="contato@empresa.com">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="company_whatsapp" class="form-label">
                                                <i class="fab fa-whatsapp me-1"></i>
                                                WhatsApp
                                            </label>
                                            <input type="tel" class="form-control" id="company_whatsapp" name="company_whatsapp" 
                                                   value="<?php echo htmlspecialchars($configuracoes['company_whatsapp'] ?? ''); ?>"
                                                   placeholder="(11) 99999-9999">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="company_instagram" class="form-label">
                                                <i class="fab fa-instagram me-1"></i>
                                                Instagram
                                            </label>
                                            <input type="text" class="form-control" id="company_instagram" name="company_instagram" 
                                                   value="<?php echo htmlspecialchars($configuracoes['company_instagram'] ?? ''); ?>"
                                                   placeholder="@empresa">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="company_facebook" class="form-label">
                                                <i class="fab fa-facebook me-1"></i>
                                                Facebook
                                            </label>
                                            <input type="text" class="form-control" id="company_facebook" name="company_facebook" 
                                                   value="<?php echo htmlspecialchars($configuracoes['company_facebook'] ?? ''); ?>"
                                                   placeholder="facebook.com/empresa">
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" name="update_contact" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Salvar InformaÃ§Ãµes de Contato
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ConfiguraÃ§Ãµes do Sistema -->
                    <div class="tab-pane fade" id="system" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-server me-2"></i>
                                    ConfiguraÃ§Ãµes do Sistema
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                                       <?php echo ($configuracoes['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="maintenance_mode">
                                                    <i class="fas fa-tools me-1"></i>
                                                    Modo ManutenÃ§Ã£o
                                                </label>
                                                <div class="form-text">Ativa uma pÃ¡gina de manutenÃ§Ã£o para visitantes</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="enable_notifications" name="enable_notifications" 
                                                       <?php echo ($configuracoes['enable_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_notifications">
                                                    <i class="fas fa-bell me-1"></i>
                                                    NotificaÃ§Ãµes
                                                </label>
                                                <div class="form-text">Habilita notificaÃ§Ãµes do sistema</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="max_upload_size" class="form-label">
                                                <i class="fas fa-upload me-1"></i>
                                                Tamanho MÃ¡ximo de Upload (MB)
                                            </label>
                                            <input type="number" class="form-control" id="max_upload_size" name="max_upload_size" 
                                                   value="<?php echo htmlspecialchars($configuracoes['max_upload_size'] ?? 10); ?>" 
                                                   min="1" max="100" required>
                                            <div class="form-text">Entre 1 e 100 MB</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="items_per_page" class="form-label">
                                                <i class="fas fa-list me-1"></i>
                                                Itens por PÃ¡gina
                                            </label>
                                            <input type="number" class="form-control" id="items_per_page" name="items_per_page" 
                                                   value="<?php echo htmlspecialchars($configuracoes['items_per_page'] ?? 20); ?>" 
                                                   min="5" max="100" required>
                                            <div class="form-text">Entre 5 e 100 itens</div>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" name="update_system" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Salvar ConfiguraÃ§Ãµes do Sistema
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- InformaÃ§Ãµes do Sistema -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    InformaÃ§Ãµes do Sistema
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>VersÃ£o do PHP:</strong><br>
                                        <span class="text-muted"><?php echo PHP_VERSION; ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>VersÃ£o do MySQL:</strong><br>
                                        <span class="text-muted"><?php echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Limite de Upload:</strong><br>
                                        <span class="text-muted"><?php echo ini_get('upload_max_filesize'); ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Limite de MemÃ³ria:</strong><br>
                                        <span class="text-muted"><?php echo ini_get('memory_limit'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Contador de caracteres para descriÃ§Ã£o
        document.getElementById('site_description').addEventListener('input', function() {
            const maxLength = 160;
            const currentLength = this.value.length;
            const remaining = maxLength - currentLength;
            
            const formText = this.nextElementSibling;
            if (remaining < 0) {
                formText.className = 'form-text text-danger';
                formText.textContent = `Excedeu ${Math.abs(remaining)} caracteres`;
            } else {
                formText.className = 'form-text';
                formText.textContent = `${remaining} caracteres restantes`;
            }
        });
        
        // MÃ¡scara para telefone
        function applyPhoneMask(input) {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                
                if (value.length > 0) {
                    if (value.length <= 2) {
                        value = `(${value}`;
                    } else if (value.length <= 6) {
                        value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
                    } else if (value.length <= 10) {
                        value = `(${value.slice(0, 2)}) ${value.slice(2, 6)}-${value.slice(6)}`;
                    } else {
                        value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
                    }
                }
                
                e.target.value = value;
            });
        }
        
        // Aplicar mÃ¡scara aos campos de telefone
        applyPhoneMask(document.getElementById('company_phone'));
        applyPhoneMask(document.getElementById('company_whatsapp'));
    </script>
</body>
</html>
