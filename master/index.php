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
    $phone_venda_input = $_POST['phone_venda'] ?? '';
    $phone_locacao_input = $_POST['phone_locacao'] ?? '';
    $whatsapp_venda_input = $_POST['whatsapp_venda'] ?? '';
    $whatsapp_locacao_input = $_POST['whatsapp_locacao'] ?? '';
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

        $phone_venda = cleanInput(validateAndFormatPhone($phone_venda_input, 'Telefone (venda)'));
        $phone_locacao = cleanInput(validateAndFormatPhone($phone_locacao_input, 'Telefone (locação)'));
        $whatsapp_venda = cleanInput(validateAndFormatPhone($whatsapp_venda_input, 'WhatsApp (venda)'));
        $whatsapp_locacao = cleanInput(validateAndFormatPhone($whatsapp_locacao_input, 'WhatsApp (locação)'));

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

        $success = 'Cliente criado com sucesso!';

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

$loadingTenants = $loadingTenants ?? false;

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 page-header">
    <div>
        <h1 class="page-title mb-1">Visão Geral</h1>
        <p class="page-subtitle mb-0">Gerencie clientes, domínios e configurações da plataforma.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTenantModal">
        <i class="fas fa-plus me-2"></i>Novo Cliente
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

<div class="row g-3 mb-4 kpi-grid">
    <div class="col-md-4 col-sm-6">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center">
                <div class="kpi-icon kpi-icon--brand me-3">
                    <i class="fas fa-building"></i>
                </div>
                <div class="kpi-content">
                    <p class="kpi-label">Total de Clientes</p>
                    <span class="kpi-value"><?php echo $summary['total_tenants'] ?? 0; ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center">
                <div class="kpi-icon kpi-icon--success me-3">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="kpi-content">
                    <p class="kpi-label">Ativos</p>
                    <span class="kpi-value"><?php echo $summary['active_tenants'] ?? 0; ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="card kpi-card">
            <div class="card-body d-flex align-items-center">
                <div class="kpi-icon kpi-icon--warning me-3">
                    <i class="fas fa-pause-circle"></i>
                </div>
                <div class="kpi-content">
                    <p class="kpi-label">Suspensos</p>
                    <span class="kpi-value"><?php echo $summary['suspended_tenants'] ?? 0; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card data-card mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Clientes cadastrados</h5>
        </div>
    </div>
    <div class="card-body">
        <?php if ($loadingTenants): ?>
            <div class="table-skeleton" aria-hidden="true">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="skeleton-row">
                        <?php for ($c = 0; $c < 5; $c++): ?>
                            <span class="skeleton-cell"></span>
                        <?php endfor; ?>
                    </div>
                <?php endfor; ?>
            </div>
        <?php elseif (empty($tenants)): ?>
            <div class="empty-state">
                <div class="empty-state__icon">
                    <i class="fas fa-building-circle-exclamation"></i>
                </div>
                <h3 class="empty-state__title">Nenhum cliente cadastrado</h3>
                <p class="empty-state__description">Comece criando um cliente para acompanhar domínios e usuários da plataforma.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTenantModal">
                    <i class="fas fa-plus me-2"></i>Novo Cliente
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive tenants-table">
                <table class="table align-middle table-modern">
                    <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Domínio</th>
                        <th class="text-end col-optional-md">Usuários</th>
                        <th class="text-end col-optional-md">Imóveis</th>
                        <th class="col-optional">Leads pendentes</th>
                        <th>Status</th>
                        <th class="col-optional">Desde</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tenants as $tenant): ?>
                        <tr>
                            <td data-label="Cliente" class="tenant-name">
                                <a href="tenant.php?id=<?php echo $tenant['id']; ?>" class="tenant-link">
                                    <strong><?php echo htmlspecialchars($tenant['name']); ?></strong>
                                </a>
                                <span class="tenant-meta text-muted"><?php echo htmlspecialchars($tenant['site_email'] ?? ''); ?></span>
                            </td>
                            <td data-label="Domínio" class="domain-cell">
                                <span class="domain-main">
                                    <i class="fas fa-globe text-muted"></i>
                                    <?php echo htmlspecialchars($tenant['primary_domain']); ?>
                                </span>
                                <small class="text-muted"><?php echo $tenant['total_domains']; ?> domínios</small>
                            </td>
                            <td class="col-nums col-optional-md" data-label="Usuários">
                                <?php echo $tenant['total_users']; ?>
                            </td>
                            <td class="col-nums col-optional-md" data-label="Imóveis">
                                <?php echo $tenant['total_properties']; ?>
                            </td>
                            <td class="col-optional" data-label="Leads pendentes">
                                <?php if ((int)$tenant['total_leads_nao_lidos'] > 0): ?>
                                    <span class="status-chip status-chip--danger">
                                        <?php echo $tenant['total_leads_nao_lidos']; ?> novos
                                    </span>
                                <?php else: ?>
                                    <span class="status-chip status-chip--success">Em dia</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status">
                                <?php if ($tenant['status'] === 'active'): ?>
                                    <span class="status-chip status-chip--success">Ativo</span>
                                <?php else: ?>
                                    <span class="status-chip status-chip--warning">Suspenso</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-optional" data-label="Desde">
                                <small class="text-muted">
                                    <?php echo formatDateTime($tenant['created_at']); ?>
                                </small>
                            </td>
                            <td class="text-end" data-label="Ações">
                                <div class="table-actions d-flex">
                                    <a href="tenant.php?id=<?php echo $tenant['id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-gear me-1"></i>Configurar
                                    </a>
                                    <a href="../admin/login.php?tenant=<?php echo urlencode($tenant['slug']); ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-user-check me-1"></i>Acessar como cliente
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

<!-- Modal criação cliente -->
<div class="modal fade" id="createTenantModal" tabindex="-1" aria-labelledby="createTenantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTenantModalLabel">
                        <i class="fas fa-plus-circle me-2 text-primary"></i>Novo Cliente
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
                        <i class="fas fa-save me-1"></i>Salvar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

