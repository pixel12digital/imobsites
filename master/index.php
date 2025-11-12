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

$error = '';
$success = '';
$generated_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tenant'])) {
    $name = cleanInput($_POST['name'] ?? '');
    $slug = cleanInput($_POST['slug'] ?? '');
    $primary_domain = cleanInput($_POST['primary_domain'] ?? '');
    $status = cleanInput($_POST['status'] ?? 'active');

    $admin_name = cleanInput($_POST['admin_name'] ?? '');
    $admin_email = cleanInput($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';

    $site_name = cleanInput($_POST['site_name'] ?? $name);
    $site_email = cleanInput($_POST['site_email'] ?? '');
    $primary_color = cleanInput($_POST['primary_color'] ?? '#023A8D');
    $secondary_color = cleanInput($_POST['secondary_color'] ?? '#F7931E');
    $phone_venda = cleanInput($_POST['phone_venda'] ?? '');
    $phone_locacao = cleanInput($_POST['phone_locacao'] ?? '');
    $whatsapp_venda = cleanInput($_POST['whatsapp_venda'] ?? '');
    $whatsapp_locacao = cleanInput($_POST['whatsapp_locacao'] ?? '');
    $instagram_url = cleanInput($_POST['instagram_url'] ?? '');
    $facebook_url = cleanInput($_POST['facebook_url'] ?? '');

    try {
        if (empty($name) || empty($primary_domain) || empty($admin_email) || empty($admin_name)) {
            throw new Exception('Preencha todos os campos obrigatórios.');
        }

        if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('E-mail do administrador inválido.');
        }

        if (empty($slug)) {
            $slug = generateSlug($name);
        }

        // Validar unicidade de slug e domínio
        $exists_slug = fetch("SELECT id FROM tenants WHERE slug = ?", [$slug]);
        if ($exists_slug) {
            throw new Exception('Slug já existe. Escolha outro identificador.');
        }

        $exists_domain = fetch("SELECT id FROM tenant_domains WHERE domain = ?", [$primary_domain]);
        if ($exists_domain) {
            throw new Exception('Domínio primário já está vinculado a outro cliente.');
        }

        if (empty($admin_password)) {
            $admin_password = generateRandomPassword(12);
            $generated_password = $admin_password;
        }

        $pdo->beginTransaction();

        $tenant_id = insert('tenants', [
            'name' => $name,
            'slug' => $slug,
            'status' => in_array($status, ['active', 'suspended']) ? $status : 'active'
        ]);

        insert('tenant_domains', [
            'tenant_id' => $tenant_id,
            'domain' => $primary_domain,
            'is_primary' => 1
        ]);

        insert('tenant_settings', [
            'tenant_id' => $tenant_id,
            'site_name' => $site_name ?: $name,
            'site_email' => $site_email,
            'primary_color' => $primary_color ?: '#023A8D',
            'secondary_color' => $secondary_color ?: '#F7931E',
            'phone_venda' => $phone_venda,
            'phone_locacao' => $phone_locacao,
            'whatsapp_venda' => $whatsapp_venda,
            'whatsapp_locacao' => $whatsapp_locacao,
            'instagram_url' => $instagram_url,
            'facebook_url' => $facebook_url
        ]);

        insert('usuarios', [
            'nome' => $admin_name,
            'email' => $admin_email,
            'senha' => password_hash($admin_password, PASSWORD_DEFAULT),
            'nivel' => 'admin',
            'ativo' => 1,
            'data_criacao' => date('Y-m-d H:i:s'),
            'tenant_id' => $tenant_id
        ]);

        $pdo->commit();

        $success = 'Tenant criado com sucesso!';

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$summary = fetch("
    SELECT 
        COUNT(*) as total_tenants,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_tenants,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_tenants
    FROM tenants
");

$tenants = fetchAll("
    SELECT 
        t.*,
        ts.site_name,
        ts.site_email,
        (SELECT domain FROM tenant_domains WHERE tenant_id = t.id AND is_primary = 1 LIMIT 1) as primary_domain,
        (SELECT COUNT(*) FROM usuarios WHERE tenant_id = t.id) as total_users,
        (SELECT COUNT(*) FROM imoveis WHERE tenant_id = t.id) as total_properties,
        (SELECT COUNT(*) FROM contatos WHERE tenant_id = t.id AND status = 'nao_lido') as total_leads_nao_lidos,
        (SELECT COUNT(*) FROM tenant_domains WHERE tenant_id = t.id) as total_domains
    FROM tenants t
    LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
    ORDER BY t.created_at DESC
");

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Visão Geral</h1>
        <p class="text-muted mb-0">Gerencie clientes, domínios e configurações da plataforma.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTenantModal">
        <i class="fas fa-plus me-2"></i>Novo Tenant
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="fas fa-circle-exclamation me-2"></i>
        <div><?php echo $error; ?></div>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <?php if ($generated_password): ?>
            <div class="mt-2">
                <strong>Senha do administrador:</strong>
                <span class="fw-bold text-primary"><?php echo $generated_password; ?></span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 me-3">
                        <i class="fas fa-building fa-lg"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1">Total de Tenants</p>
                        <h4 class="mb-0"><?php echo $summary['total_tenants'] ?? 0; ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 me-3">
                        <i class="fas fa-play-circle fa-lg"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1">Ativos</p>
                        <h4 class="mb-0"><?php echo $summary['active_tenants'] ?? 0; ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-3 me-3">
                        <i class="fas fa-pause-circle fa-lg"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-1">Suspensos</p>
                        <h4 class="mb-0"><?php echo $summary['suspended_tenants'] ?? 0; ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Tenants cadastrados</h5>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($tenants)): ?>
            <div class="text-center py-5">
                <i class="fas fa-building-circle-xmark fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">Nenhum cliente cadastrado até o momento.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Domínio</th>
                        <th>Usuários</th>
                        <th>Imóveis</th>
                        <th>Leads pendentes</th>
                        <th>Status</th>
                        <th>Desde</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tenants as $tenant): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($tenant['name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($tenant['site_email'] ?? ''); ?></small>
                            </td>
                            <td>
                                <i class="fas fa-globe text-muted me-1"></i>
                                <?php echo htmlspecialchars($tenant['primary_domain']); ?>
                                <br>
                                <small class="text-muted"><?php echo $tenant['total_domains']; ?> domínios</small>
                            </td>
                            <td>
                                <span class="badge text-bg-primary"><?php echo $tenant['total_users']; ?></span>
                            </td>
                            <td>
                                <span class="badge text-bg-secondary"><?php echo $tenant['total_properties']; ?></span>
                            </td>
                            <td>
                                <?php if ((int)$tenant['total_leads_nao_lidos'] > 0): ?>
                                    <span class="badge text-bg-danger">
                                        <?php echo $tenant['total_leads_nao_lidos']; ?> novos
                                    </span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Em dia</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tenant['status'] === 'active'): ?>
                                    <span class="badge text-bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning text-dark">Suspenso</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo formatDateTime($tenant['created_at']); ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="tenant.php?id=<?php echo $tenant['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-gear me-1"></i>Configurar
                                    </a>
                                    <a href="../admin/login.php?tenant=<?php echo urlencode($tenant['slug']); ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-user-secret me-1"></i>Impersonar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal criação tenant -->
<div class="modal fade" id="createTenantModal" tabindex="-1" aria-labelledby="createTenantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTenantModalLabel">
                        <i class="fas fa-plus-circle me-2 text-primary"></i>Novo Tenant
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome do cliente *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Slug (identificador)</label>
                            <input type="text" name="slug" class="form-control" placeholder="ex: imobiliaria-abc">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Domínio principal *</label>
                            <input type="text" name="primary_domain" class="form-control" placeholder="cliente.exemplo.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Ativo</option>
                                <option value="suspended">Suspenso</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <hr class="text-muted">
                            <h6 class="text-uppercase text-muted small">Administrador principal</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="admin_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">E-mail *</label>
                            <input type="email" name="admin_email" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Senha (opcional)</label>
                            <input type="text" name="admin_password" class="form-control" placeholder="Deixe em branco para gerar automaticamente">
                        </div>

                        <div class="col-12">
                            <hr class="text-muted">
                            <h6 class="text-uppercase text-muted small">Configurações iniciais</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nome do site</label>
                            <input type="text" name="site_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail do site</label>
                            <input type="email" name="site_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cor primária</label>
                            <input type="color" name="primary_color" class="form-control form-control-color" value="#023A8D">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cor secundária</label>
                            <input type="color" name="secondary_color" class="form-control form-control-color" value="#F7931E">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefone (venda)</label>
                            <input type="text" name="phone_venda" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefone (locação)</label>
                            <input type="text" name="phone_locacao" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp (venda)</label>
                            <input type="text" name="whatsapp_venda" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp (locação)</label>
                            <input type="text" name="whatsapp_locacao" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Instagram</label>
                            <input type="text" name="instagram_url" class="form-control" placeholder="https://instagram.com/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Facebook</label>
                            <input type="text" name="facebook_url" class="form-control" placeholder="https://facebook.com/...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="create_tenant" value="1" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Salvar Tenant
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

