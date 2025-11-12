<?php
session_start();

require_once '../config/paths.php';
require_once '../config/database.php';
require_once '../config/master.php';
require_once 'utils.php';

if (!isset($_SESSION['master_logged_in']) || $_SESSION['master_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$tenants_all = fetchAll("SELECT id, name FROM tenants ORDER BY name ASC");

if (empty($tenants_all)) {
    header('Location: index.php');
    exit;
}

$tenant_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)$tenants_all[0]['id'];
$tenant = fetch("SELECT * FROM tenants WHERE id = ?", [$tenant_id]);

if (!$tenant) {
    header('Location: tenant.php?id=' . (int)$tenants_all[0]['id']);
    exit;
}

$settings = fetch("SELECT * FROM tenant_settings WHERE tenant_id = ?", [$tenant_id]) ?: [];
$domains = fetchAll("SELECT * FROM tenant_domains WHERE tenant_id = ? ORDER BY is_primary DESC, id ASC", [$tenant_id]);

$error = '';
$success = '';
$generated_password = '';

// Atualizar informações básicas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tenant'])) {
    $name = cleanInput($_POST['name'] ?? $tenant['name']);
    $slug = cleanInput($_POST['slug'] ?? $tenant['slug']);
    $status = cleanInput($_POST['status'] ?? $tenant['status']);

    try {
        if (empty($name)) {
            throw new Exception('Nome do tenant é obrigatório.');
        }
        if (empty($slug)) {
            $slug = generateSlug($name);
        }
        $exists = fetch("SELECT id FROM tenants WHERE slug = ? AND id != ?", [$slug, $tenant_id]);
        if ($exists) {
            throw new Exception('Slug já utilizado por outro tenant.');
        }
        update('tenants', [
            'name' => $name,
            'slug' => $slug,
            'status' => in_array($status, ['active', 'suspended']) ? $status : 'active',
            'updated_at' => date('Y-m-d H:i:s')
        ], "id = ?", [$tenant_id]);

        $tenant = fetch("SELECT * FROM tenants WHERE id = ?", [$tenant_id]);
        $success = 'Dados gerais atualizados com sucesso.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Atualizar configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $payload = [
        'site_name' => cleanInput($_POST['site_name'] ?? ''),
        'site_email' => cleanInput($_POST['site_email'] ?? ''),
        'primary_color' => cleanInput($_POST['primary_color'] ?? '#023A8D'),
        'secondary_color' => cleanInput($_POST['secondary_color'] ?? '#F7931E'),
        'phone_venda' => cleanInput($_POST['phone_venda'] ?? ''),
        'phone_locacao' => cleanInput($_POST['phone_locacao'] ?? ''),
        'whatsapp_venda' => cleanInput($_POST['whatsapp_venda'] ?? ''),
        'whatsapp_locacao' => cleanInput($_POST['whatsapp_locacao'] ?? ''),
        'instagram_url' => cleanInput($_POST['instagram_url'] ?? ''),
        'facebook_url' => cleanInput($_POST['facebook_url'] ?? '')
    ];

    if (empty($settings)) {
        $payload['tenant_id'] = $tenant_id;
        insert('tenant_settings', $payload);
    } else {
        update('tenant_settings', $payload, "tenant_id = ?", [$tenant_id]);
    }
    $settings = fetch("SELECT * FROM tenant_settings WHERE tenant_id = ?", [$tenant_id]) ?: [];
    $success = 'Configurações atualizadas.';
}

// Adicionar domínio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_domain'])) {
    $domain = cleanInput($_POST['domain'] ?? '');
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
    try {
        if (empty($domain)) {
            throw new Exception('Informe o domínio.');
        }
        $exists = fetch("SELECT id FROM tenant_domains WHERE domain = ?", [$domain]);
        if ($exists) {
            throw new Exception('Domínio já cadastrado em outro tenant.');
        }
        if ($is_primary) {
            query("UPDATE tenant_domains SET is_primary = 0 WHERE tenant_id = ?", [$tenant_id]);
        }
        insert('tenant_domains', [
            'tenant_id' => $tenant_id,
            'domain' => $domain,
            'is_primary' => $is_primary
        ]);
        $domains = fetchAll("SELECT * FROM tenant_domains WHERE tenant_id = ? ORDER BY is_primary DESC, id ASC", [$tenant_id]);
        $success = 'Domínio adicionado.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Remover domínio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_domain'])) {
    $domain_id = (int)($_POST['domain_id'] ?? 0);
    $domain = fetch("SELECT * FROM tenant_domains WHERE id = ? AND tenant_id = ?", [$domain_id, $tenant_id]);
    if ($domain) {
        if ($domain['is_primary']) {
            $error = 'Não é possível excluir o domínio principal.';
        } else {
            query("DELETE FROM tenant_domains WHERE id = ? AND tenant_id = ?", [$domain_id, $tenant_id]);
            $domains = fetchAll("SELECT * FROM tenant_domains WHERE tenant_id = ? ORDER BY is_primary DESC, id ASC", [$tenant_id]);
            $success = 'Domínio removido.';
        }
    }
}

// Resetar senha de usuário admin específico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_password'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $user = fetch("SELECT * FROM usuarios WHERE id = ? AND tenant_id = ?", [$user_id, $tenant_id]);
    if ($user) {
        $new_pass = generateRandomPassword(12);
        update('usuarios', [
            'senha' => password_hash($new_pass, PASSWORD_DEFAULT)
        ], "id = ?", [$user_id]);
        $generated_password = $new_pass;
        $success = 'Senha redefinida com sucesso.';
    } else {
        $error = 'Usuário não encontrado.';
    }
}

$users = fetchAll("SELECT id, nome, email, nivel, ativo, data_criacao FROM usuarios WHERE tenant_id = ? ORDER BY data_criacao DESC", [$tenant_id]);

$stats = fetch("
    SELECT 
        (SELECT COUNT(*) FROM imoveis WHERE tenant_id = ?) as total_imoveis,
        (SELECT COUNT(*) FROM contatos WHERE tenant_id = ?) as total_contatos,
        (SELECT COUNT(*) FROM contatos WHERE tenant_id = ? AND status = 'nao_lido') as contatos_pendentes,
        (SELECT COUNT(*) FROM usuarios WHERE tenant_id = ?) as total_usuarios
", [$tenant_id, $tenant_id, $tenant_id, $tenant_id]);

include 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="fas fa-building me-2 text-primary"></i>
            <?php echo htmlspecialchars($tenant['name']); ?>
        </h1>
        <p class="text-muted mb-0">Gerencie configurações específicas e domínios deste cliente.</p>
    </div>
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            Trocar tenant
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($tenants_all as $item): ?>
                <li>
                    <a class="dropdown-item <?php echo $item['id'] == $tenant_id ? 'active' : ''; ?>" href="tenant.php?id=<?php echo $item['id']; ?>">
                        <?php echo htmlspecialchars($item['name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center">
        <i class="fas fa-circle-exclamation me-2"></i>
        <div><?php echo $error; ?></div>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <?php if ($generated_password): ?>
            <div class="mt-2">
                <strong>Nova senha:</strong>
                <span class="fw-bold text-primary"><?php echo $generated_password; ?></span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1">Imóveis</p>
                <h4 class="mb-0"><?php echo $stats['total_imoveis'] ?? 0; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1">Usuários</p>
                <h4 class="mb-0"><?php echo $stats['total_usuarios'] ?? 0; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1">Leads</p>
                <h4 class="mb-0"><?php echo $stats['total_contatos'] ?? 0; ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1">Pendentes</p>
                <h4 class="mb-0 text-danger"><?php echo $stats['contatos_pendentes'] ?? 0; ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-file-signature me-2 text-primary"></i>Informações do Tenant</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Nome do tenant</label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($tenant['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($tenant['slug']); ?>" required>
                        <small class="text-muted">Usado em URLs e integrações.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?php echo $tenant['status'] === 'active' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="suspended" <?php echo $tenant['status'] === 'suspended' ? 'selected' : ''; ?>>Suspenso</option>
                        </select>
                    </div>
                    <button type="submit" name="update_tenant" value="1" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Salvar alterações
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-brush me-2 text-primary"></i>Configurações do Site</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome do site</label>
                            <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? $tenant['name']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail do site</label>
                            <input type="email" class="form-control" name="site_email" value="<?php echo htmlspecialchars($settings['site_email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cor primária</label>
                            <input type="color" class="form-control form-control-color" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#023A8D'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cor secundária</label>
                            <input type="color" class="form-control form-control-color" name="secondary_color" value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#F7931E'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefone vendas</label>
                            <input type="text" class="form-control" name="phone_venda" value="<?php echo htmlspecialchars($settings['phone_venda'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefone locação</label>
                            <input type="text" class="form-control" name="phone_locacao" value="<?php echo htmlspecialchars($settings['phone_locacao'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp vendas</label>
                            <input type="text" class="form-control" name="whatsapp_venda" value="<?php echo htmlspecialchars($settings['whatsapp_venda'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp locação</label>
                            <input type="text" class="form-control" name="whatsapp_locacao" value="<?php echo htmlspecialchars($settings['whatsapp_locacao'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Instagram</label>
                            <input type="text" class="form-control" name="instagram_url" value="<?php echo htmlspecialchars($settings['instagram_url'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facebook</label>
                            <input type="text" class="form-control" name="facebook_url" value="<?php echo htmlspecialchars($settings['facebook_url'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" name="update_settings" value="1" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Atualizar configurações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-globe me-2 text-primary"></i>Domínios</h5>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                        <i class="fas fa-plus me-1"></i>Adicionar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($domains)): ?>
                    <p class="text-muted mb-0">Nenhum domínio cadastrado.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($domains as $domain): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($domain['domain']); ?></strong>
                                    <?php if ($domain['is_primary']): ?>
                                        <span class="badge text-bg-success ms-2">Principal</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$domain['is_primary']): ?>
                                    <form method="POST" class="mb-0">
                                        <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" name="delete_domain" value="1">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-users-gear me-2 text-primary"></i>Usuários</h5>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <p class="text-muted mb-0">Nenhum usuário cadastrado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Nível</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars($user['nivel']); ?></span></td>
                                    <td>
                                        <?php if ($user['ativo']): ?>
                                            <span class="badge text-bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-danger">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo formatDateTime($user['data_criacao']); ?></small></td>
                                    <td class="text-end">
                                        <form method="POST" class="mb-0">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button class="btn btn-sm btn-outline-primary" name="reset_user_password" value="1">
                                                <i class="fas fa-key me-1"></i>Resetar senha
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal adicionar domínio -->
<div class="modal fade" id="addDomainModal" tabindex="-1" aria-labelledby="addDomainModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDomainModalLabel">Adicionar domínio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Domínio</label>
                        <input type="text" class="form-control" name="domain" placeholder="cliente.exemplo.com" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_primary" id="is_primary">
                        <label class="form-check-label" for="is_primary">
                            Definir como domínio principal
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="add_domain" value="1" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Adicionar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

