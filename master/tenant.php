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

if (!function_exists('isAjaxRequest')) {
    function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse(array $payload, int $statusCode = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($payload);
        exit;
    }
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

if (!empty($settings)) {
    foreach (['phone_venda', 'phone_locacao', 'whatsapp_venda', 'whatsapp_locacao'] as $phoneField) {
        $settings[$phoneField] = formatPhoneIfPossible($settings[$phoneField] ?? '');
    }
}
$domains = fetchAll("SELECT * FROM tenant_domains WHERE tenant_id = ? ORDER BY is_primary DESC, id ASC", [$tenant_id]);

$error = '';
$success = '';
$generated_password = '';

// Atualizar informações do cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tenant'])) {
    try {
        $normalizeLine = static function (?string $value): string {
            $value = trim((string)$value);
            $value = preg_replace('/\s+/u', ' ', $value);
            return $value;
        };

        $toNullableLine = static function (?string $value) use ($normalizeLine) {
            $normalized = $normalizeLine($value);
            return $normalized === '' ? null : cleanInput($normalized);
        };

        $toNullableRaw = static function (?string $value) {
            $trimmed = trim((string)$value);
            return $trimmed === '' ? null : cleanInput($trimmed);
        };

        $digitsOnly = static function (?string $value): string {
            return preg_replace('/\D+/', '', (string)$value);
        };

        $clientType = strtolower($normalizeLine($_POST['client_type'] ?? ''));
        if (!in_array($clientType, ['pf', 'pj'], true)) {
            throw new Exception('Selecione se o cliente é Pessoa Física ou Jurídica.');
        }

        $nameLine = $normalizeLine($_POST['name'] ?? $tenant['name'] ?? '');
        if ($nameLine === '') {
            throw new Exception('Nome do cliente é obrigatório.');
        }
        $name = cleanInput($nameLine);

        $statusInput = strtolower($normalizeLine($_POST['status'] ?? $tenant['status'] ?? 'active'));
        $status = in_array($statusInput, ['active', 'suspended'], true) ? $statusInput : 'active';

        $slug = cleanInput($tenant['slug'] ?? '');
        if ($slug === '') {
            $slug = generateSlug($nameLine);
        }

        $exists = fetch("SELECT id FROM tenants WHERE slug = ? AND id != ?", [$slug, $tenant_id]);
        if ($exists) {
            throw new Exception('Slug já utilizado por outro cliente.');
        }

        $personFullName = $toNullableLine($_POST['person_full_name'] ?? null);
        $personCpfDigits = $digitsOnly($_POST['person_cpf'] ?? null);
        $personCpf = $personCpfDigits === '' ? null : cleanInput($personCpfDigits);
        $personCreci = $toNullableLine($_POST['person_creci'] ?? null);

        if ($clientType === 'pf') {
            if ($personFullName === null) {
                throw new Exception('Informe o nome completo do cliente (Pessoa Física).');
            }
            if ($personCpf === null) {
                throw new Exception('Informe o CPF do cliente (Pessoa Física).');
            }
        }

        $companyLegalName = $toNullableLine($_POST['company_legal_name'] ?? null);
        $companyTradeName = $toNullableLine($_POST['company_trade_name'] ?? null);
        $companyCnpjDigits = $digitsOnly($_POST['company_cnpj'] ?? null);
        $companyCnpj = $companyCnpjDigits === '' ? null : cleanInput($companyCnpjDigits);
        $companyCreci = $toNullableLine($_POST['company_creci'] ?? null);
        $companyResponsibleName = $toNullableLine($_POST['company_responsible_name'] ?? null);
        $companyResponsibleCpfDigits = $digitsOnly($_POST['company_responsible_cpf'] ?? null);
        $companyResponsibleCpf = $companyResponsibleCpfDigits === '' ? null : cleanInput($companyResponsibleCpfDigits);

        if ($clientType === 'pj') {
            if ($companyLegalName === null) {
                throw new Exception('Informe a razão social (Pessoa Jurídica).');
            }
            if ($companyTradeName === null) {
                throw new Exception('Informe o nome fantasia (Pessoa Jurídica).');
            }
            if ($companyCnpj === null) {
                throw new Exception('Informe o CNPJ (Pessoa Jurídica).');
            }
        }

        $contactEmailRaw = strtolower($normalizeLine($_POST['contact_email'] ?? ''));
        if ($contactEmailRaw !== '' && !filter_var($contactEmailRaw, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Informe um e-mail principal válido.');
        }
        $contactEmail = $contactEmailRaw === '' ? null : cleanInput($contactEmailRaw);

        $contactWhatsappDigits = $digitsOnly($_POST['contact_whatsapp'] ?? null);
        $contactWhatsapp = $contactWhatsappDigits === '' ? null : cleanInput($contactWhatsappDigits);

        $cepDigits = $digitsOnly($_POST['cep'] ?? null);
        if ($cepDigits === '') {
            throw new Exception('Informe o CEP.');
        }
        if (strlen($cepDigits) !== 8) {
            throw new Exception('CEP deve ter 8 dígitos no formato 00000-000.');
        }
        $cepFormatted = substr($cepDigits, 0, 5) . '-' . substr($cepDigits, 5);
        $cep = cleanInput($cepFormatted);

        $addressStreet = $toNullableLine($_POST['address_street'] ?? null);
        $addressNumber = $toNullableLine($_POST['address_number'] ?? null);
        $addressNeighborhood = $toNullableLine($_POST['address_neighborhood'] ?? null);

        $stateRaw = strtoupper($normalizeLine($_POST['state'] ?? ''));
        if ($stateRaw === '') {
            throw new Exception('Selecione a UF.');
        }
        $state = cleanInput($stateRaw);

        $cityLine = $normalizeLine($_POST['city'] ?? '');
        if ($cityLine === '') {
            throw new Exception('Selecione a cidade.');
        }
        $city = cleanInput($cityLine);

        $notes = $toNullableRaw($_POST['notes'] ?? null);

        $updatePayload = [
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'client_type' => $clientType,
            'person_full_name' => $personFullName,
            'person_cpf' => $personCpf,
            'person_creci' => $personCreci,
            'company_legal_name' => $companyLegalName,
            'company_trade_name' => $companyTradeName,
            'company_cnpj' => $companyCnpj,
            'company_creci' => $companyCreci,
            'company_responsible_name' => $companyResponsibleName,
            'company_responsible_cpf' => $companyResponsibleCpf,
            'contact_email' => $contactEmail,
            'contact_whatsapp' => $contactWhatsapp,
            'cep' => $cep,
            'address_street' => $addressStreet,
            'address_number' => $addressNumber,
            'address_neighborhood' => $addressNeighborhood,
            'state' => $state,
            'city' => $city,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        update('tenants', $updatePayload, "id = ?", [$tenant_id]);

        $tenant = fetch("SELECT * FROM tenants WHERE id = ?", [$tenant_id]);
        $_POST = [];
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'success',
                'action' => 'update_tenant',
                'message' => 'Informações do cliente atualizadas com sucesso.',
                'tenant' => $tenant
            ]);
        }
        $success = 'Informações do cliente atualizadas com sucesso.';
    } catch (Exception $e) {
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'error',
                'action' => 'update_tenant',
                'message' => $e->getMessage()
            ], 422);
        }
        $error = $e->getMessage();
    }
}

// Atualizar configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        $siteNameInput = cleanInput($_POST['site_name'] ?? '');
        $slugInput = cleanInput($_POST['slug'] ?? '');
        if (empty($slugInput) && !empty($siteNameInput)) {
            $slugInput = generateSlug($siteNameInput);
        }
        if (empty($slugInput)) {
            $slugInput = $tenant['slug'] ?? '';
        }
        if (empty($slugInput)) {
            throw new Exception('Slug não pode ficar vazio.');
        }
        $slugExists = fetch("SELECT id FROM tenants WHERE slug = ? AND id != ?", [$slugInput, $tenant_id]);
        if ($slugExists) {
            throw new Exception('Slug já utilizado por outro cliente.');
        }

        $payload = [
            'site_name' => $siteNameInput,
            'site_email' => cleanInput($_POST['site_email'] ?? ''),
            'primary_color' => cleanInput($_POST['primary_color'] ?? '#023A8D'),
            'secondary_color' => cleanInput($_POST['secondary_color'] ?? '#F7931E'),
            'phone_venda' => cleanInput(validateAndFormatPhone($_POST['phone_venda'] ?? '', 'Telefone vendas')),
            'phone_locacao' => cleanInput(validateAndFormatPhone($_POST['phone_locacao'] ?? '', 'Telefone locação')),
            'whatsapp_venda' => cleanInput(validateAndFormatPhone($_POST['whatsapp_venda'] ?? '', 'WhatsApp vendas')),
            'whatsapp_locacao' => cleanInput(validateAndFormatPhone($_POST['whatsapp_locacao'] ?? '', 'WhatsApp locação')),
            'instagram_url' => cleanInput($_POST['instagram_url'] ?? ''),
            'facebook_url' => cleanInput($_POST['facebook_url'] ?? '')
        ];

        if (empty($settings)) {
            $payload['tenant_id'] = $tenant_id;
            insert('tenant_settings', $payload);
        } else {
            update('tenant_settings', $payload, "tenant_id = ?", [$tenant_id]);
        }
        update('tenants', [
            'slug' => $slugInput,
            'updated_at' => date('Y-m-d H:i:s')
        ], "id = ?", [$tenant_id]);
        $tenant['slug'] = $slugInput;
        $settings = fetch("SELECT * FROM tenant_settings WHERE tenant_id = ?", [$tenant_id]) ?: [];
        if (!empty($settings)) {
            foreach (['phone_venda', 'phone_locacao', 'whatsapp_venda', 'whatsapp_locacao'] as $phoneField) {
                $settings[$phoneField] = formatPhoneIfPossible($settings[$phoneField] ?? '');
            }
        }
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'success',
                'action' => 'update_settings',
                'message' => 'Configurações atualizadas.',
                'slug' => $slugInput,
                'settings' => $settings
            ]);
        }
        $success = 'Configurações atualizadas.';
    } catch (Exception $e) {
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'error',
                'action' => 'update_settings',
                'message' => $e->getMessage()
            ], 422);
        }
        $error = $e->getMessage();
    }
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
            throw new Exception('Domínio já cadastrado em outro cliente.');
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
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'success',
                'action' => 'add_domain',
                'message' => 'Domínio adicionado.',
                'domains' => $domains
            ]);
        }
        $success = 'Domínio adicionado.';
    } catch (Exception $e) {
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'error',
                'action' => 'add_domain',
                'message' => $e->getMessage()
            ], 422);
        }
        $error = $e->getMessage();
    }
}

// Remover domínio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_domain'])) {
    $domain_id = (int)($_POST['domain_id'] ?? 0);
    $domain = fetch("SELECT * FROM tenant_domains WHERE id = ? AND tenant_id = ?", [$domain_id, $tenant_id]);
    if ($domain) {
        if ($domain['is_primary']) {
            if (isAjaxRequest()) {
                sendJsonResponse([
                    'status' => 'error',
                    'action' => 'delete_domain',
                    'message' => 'Não é possível excluir o domínio principal.'
                ], 422);
            }
            $error = 'Não é possível excluir o domínio principal.';
        } else {
            query("DELETE FROM tenant_domains WHERE id = ? AND tenant_id = ?", [$domain_id, $tenant_id]);
            $domains = fetchAll("SELECT * FROM tenant_domains WHERE tenant_id = ? ORDER BY is_primary DESC, id ASC", [$tenant_id]);
            if (isAjaxRequest()) {
                sendJsonResponse([
                    'status' => 'success',
                    'action' => 'delete_domain',
                    'message' => 'Domínio removido.',
                    'domains' => $domains
                ]);
            }
            $success = 'Domínio removido.';
        }
    } else {
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'error',
                'action' => 'delete_domain',
                'message' => 'Domínio não encontrado.'
            ], 404);
        }
        $error = 'Domínio não encontrado.';
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
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'success',
                'action' => 'reset_user_password',
                'message' => 'Senha redefinida com sucesso.',
                'password' => $generated_password,
                'user_id' => $user_id
            ]);
        }
        $success = 'Senha redefinida com sucesso.';
    } else {
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'error',
                'action' => 'reset_user_password',
                'message' => 'Usuário não encontrado.'
            ], 404);
        }
        $error = 'Usuário não encontrado.';
    }
}

// Atualizar dados de contato do usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_email'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $new_name = trim($_POST['new_name'] ?? '');
    $new_email = strtolower(trim($_POST['new_email'] ?? ''));

    try {
        if ($user_id <= 0) {
            throw new Exception('Usuário inválido.');
        }

        $user = fetch("SELECT id FROM usuarios WHERE id = ? AND tenant_id = ?", [$user_id, $tenant_id]);
        if (!$user) {
            throw new Exception('Usuário não encontrado.');
        }

        if ($new_name === '') {
            throw new Exception('Informe o nome do usuário.');
        }

        if ($new_email === '') {
            throw new Exception('Informe o e-mail do usuário.');
        }

        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Informe um e-mail válido.');
        }

        $emailExists = fetch("SELECT id FROM usuarios WHERE email = ? AND tenant_id = ? AND id != ?", [$new_email, $tenant_id, $user_id]);
        if ($emailExists) {
            throw new Exception('Este e-mail já está em uso por outro usuário.');
        }

        update('usuarios', [
            'nome' => cleanInput($new_name),
            'email' => cleanInput($new_email),
            'data_atualizacao' => date('Y-m-d H:i:s')
        ], "id = ?", [$user_id]);

        $updatedUser = fetch("SELECT id, nome, email, nivel, ativo, data_criacao FROM usuarios WHERE id = ?", [$user_id]);
        $success = 'Dados do usuário atualizados com sucesso.';
        $generated_password = '';
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'success',
                'action' => 'update_user_email',
                'message' => $success,
                'user' => $updatedUser
            ]);
        }
    } catch (Exception $e) {
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'error',
                'action' => 'update_user_email',
                'message' => $e->getMessage(),
                'user_id' => $user_id
            ], 422);
        }
        $error = $e->getMessage();
    }
}

// Definir senha personalizada para usuário específico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_user_password'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    try {
        if ($user_id <= 0) {
            throw new Exception('Usuário inválido.');
        }

        $user = fetch("SELECT id FROM usuarios WHERE id = ? AND tenant_id = ?", [$user_id, $tenant_id]);
        if (!$user) {
            throw new Exception('Usuário não encontrado.');
        }

        if ($new_password === '' || $confirm_password === '') {
            throw new Exception('Informe e confirme a nova senha.');
        }

        if ($new_password !== $confirm_password) {
            throw new Exception('As senhas informadas não conferem.');
        }

        if (strlen($new_password) < 8) {
            throw new Exception('A nova senha deve ter pelo menos 8 caracteres.');
        }

        update('usuarios', [
            'senha' => password_hash($new_password, PASSWORD_DEFAULT)
        ], "id = ?", [$user_id]);

        $generated_password = '';
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'success',
                'action' => 'set_user_password',
                'message' => 'Senha atualizada com sucesso.',
                'user_id' => $user_id
            ]);
        }
        $success = 'Senha atualizada com sucesso.';
    } catch (Exception $e) {
        if (isAjaxRequest()) {
            sendJsonResponse([
                'status' => 'error',
                'action' => 'set_user_password',
                'message' => $e->getMessage(),
                'user_id' => $user_id
            ], 422);
        }
        $error = $e->getMessage();
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

$brazilianStates = [
    ['uf' => 'AC', 'name' => 'Acre'],
    ['uf' => 'AL', 'name' => 'Alagoas'],
    ['uf' => 'AP', 'name' => 'Amapá'],
    ['uf' => 'AM', 'name' => 'Amazonas'],
    ['uf' => 'BA', 'name' => 'Bahia'],
    ['uf' => 'CE', 'name' => 'Ceará'],
    ['uf' => 'DF', 'name' => 'Distrito Federal'],
    ['uf' => 'ES', 'name' => 'Espírito Santo'],
    ['uf' => 'GO', 'name' => 'Goiás'],
    ['uf' => 'MA', 'name' => 'Maranhão'],
    ['uf' => 'MT', 'name' => 'Mato Grosso'],
    ['uf' => 'MS', 'name' => 'Mato Grosso do Sul'],
    ['uf' => 'MG', 'name' => 'Minas Gerais'],
    ['uf' => 'PA', 'name' => 'Pará'],
    ['uf' => 'PB', 'name' => 'Paraíba'],
    ['uf' => 'PR', 'name' => 'Paraná'],
    ['uf' => 'PE', 'name' => 'Pernambuco'],
    ['uf' => 'PI', 'name' => 'Piauí'],
    ['uf' => 'RJ', 'name' => 'Rio de Janeiro'],
    ['uf' => 'RN', 'name' => 'Rio Grande do Norte'],
    ['uf' => 'RS', 'name' => 'Rio Grande do Sul'],
    ['uf' => 'RO', 'name' => 'Rondônia'],
    ['uf' => 'RR', 'name' => 'Roraima'],
    ['uf' => 'SC', 'name' => 'Santa Catarina'],
    ['uf' => 'SP', 'name' => 'São Paulo'],
    ['uf' => 'SE', 'name' => 'Sergipe'],
    ['uf' => 'TO', 'name' => 'Tocantins'],
];

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
            Trocar cliente
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

<div id="tenantAlertContainer">
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
</div>

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

<style>
    .tenant-tab-nav {
        gap: 0.5rem;
        border-bottom: none;
    }
    .tenant-tab-nav .nav-link {
        border-radius: 0.5rem;
        background: rgba(2, 58, 141, 0.08);
        color: #0d1c2e;
        font-weight: 500;
        white-space: nowrap;
    }
    .tenant-tab-nav .nav-link.active {
        background: #023A8D;
        color: #fff;
    }
    .tenant-tab-nav .nav-link:focus {
        box-shadow: none;
    }
    .tenant-client-block {
        border: 1px solid rgba(2, 58, 141, 0.12);
        border-radius: 0.75rem;
        background: #f9fbfe;
        padding: 1.5rem;
    }
    .tenant-client-block h6 {
        font-weight: 600;
        color: #023A8D;
    }
    .tenant-client-divider {
        height: 1px;
        background: rgba(2, 58, 141, 0.12);
        margin: 1.5rem 0;
    }
    @media (max-width: 991.98px) {
        .tenant-tab-nav {
            overflow-x: auto;
            flex-wrap: nowrap;
            padding-bottom: 0.25rem;
        }
        .tenant-tab-nav .nav-link {
            min-width: max-content;
        }
    }
</style>

<div class="tenant-tabs mt-3">
    <ul class="nav nav-tabs tenant-tab-nav flex-nowrap" id="tenantTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-info-tab" data-bs-toggle="tab" data-bs-target="#tab-info" type="button" role="tab" aria-controls="tab-info" aria-selected="true">
                Informações do Cliente
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-settings-tab" data-bs-toggle="tab" data-bs-target="#tab-settings" type="button" role="tab" aria-controls="tab-settings" aria-selected="false">
                Configurações do Site
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-domains-tab" data-bs-toggle="tab" data-bs-target="#tab-domains" type="button" role="tab" aria-controls="tab-domains" aria-selected="false">
                Domínios
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-users-tab" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" role="tab" aria-controls="tab-users" aria-selected="false">
                Usuários
            </button>
        </li>
    </ul>
    <div class="tab-content pt-3" id="tenantTabsContent">
        <div class="tab-pane fade show active" id="tab-info" role="tabpanel" aria-labelledby="tab-info-tab">
            <div class="row g-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-file-signature me-2 text-primary"></i>Informações do Cliente</h5>
                        </div>
                        <div class="card-body">
                            <?php
                                $clientType = $_POST['client_type'] ?? ($tenant['client_type'] ?? '');
                                $clientType = in_array($clientType, ['pf', 'pj']) ? $clientType : '';
                                $statusValue = $_POST['status'] ?? ($tenant['status'] ?? 'active');
                            ?>
                            <form method="POST" id="tenantClientForm" data-ajax="true">
                                <div class="tenant-client-block mb-3">
                                    <h6 class="mb-3">Tipo de cliente</h6>
                                    <div class="d-flex flex-wrap gap-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="client_type" id="client_type_pf" value="pf" <?php echo $clientType === 'pf' ? 'checked' : ''; ?> required>
                                            <label class="form-check-label" for="client_type_pf">Pessoa Física</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="client_type" id="client_type_pj" value="pj" <?php echo $clientType === 'pj' ? 'checked' : ''; ?> required>
                                            <label class="form-check-label" for="client_type_pj">Pessoa Jurídica</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="tenant-client-block mb-3 tenant-client-block-specific <?php echo $clientType === 'pf' ? '' : 'd-none'; ?>" data-client-type-block="pf">
                                    <h6 class="mb-3">Dados da Pessoa Física</h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Nome completo</label>
                                            <input type="text" class="form-control" name="person_full_name" value="<?php echo htmlspecialchars($_POST['person_full_name'] ?? ($tenant['person_full_name'] ?? '')); ?>" data-client-type-required="pf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CPF</label>
                                            <input type="text" class="form-control" name="person_cpf" value="<?php echo htmlspecialchars($_POST['person_cpf'] ?? ($tenant['person_cpf'] ?? '')); ?>" data-client-type-required="pf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CRECI (corretor)</label>
                                            <input type="text" class="form-control" name="person_creci" value="<?php echo htmlspecialchars($_POST['person_creci'] ?? ($tenant['person_creci'] ?? '')); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="tenant-client-block mb-3 tenant-client-block-specific <?php echo $clientType === 'pj' ? '' : 'd-none'; ?>" data-client-type-block="pj">
                                    <h6 class="mb-3">Dados da Pessoa Jurídica</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Razão social</label>
                                            <input type="text" class="form-control" name="company_legal_name" value="<?php echo htmlspecialchars($_POST['company_legal_name'] ?? ($tenant['company_legal_name'] ?? '')); ?>" data-client-type-required="pj">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Nome fantasia</label>
                                            <input type="text" class="form-control" name="company_trade_name" value="<?php echo htmlspecialchars($_POST['company_trade_name'] ?? ($tenant['company_trade_name'] ?? '')); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CNPJ</label>
                                            <input type="text" class="form-control" name="company_cnpj" value="<?php echo htmlspecialchars($_POST['company_cnpj'] ?? ($tenant['company_cnpj'] ?? '')); ?>" data-client-type-required="pj">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CRECI (imobiliária)</label>
                                            <input type="text" class="form-control" name="company_creci" value="<?php echo htmlspecialchars($_POST['company_creci'] ?? ($tenant['company_creci'] ?? '')); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Responsável legal</label>
                                            <input type="text" class="form-control" name="company_responsible_name" value="<?php echo htmlspecialchars($_POST['company_responsible_name'] ?? ($tenant['company_responsible_name'] ?? '')); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CPF do responsável</label>
                                            <input type="text" class="form-control" name="company_responsible_cpf" value="<?php echo htmlspecialchars($_POST['company_responsible_cpf'] ?? ($tenant['company_responsible_cpf'] ?? '')); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="tenant-client-block mb-3">
                                    <h6 class="mb-3">Dados comuns</h6>
                                    <div class="row g-3 align-items-end">
                                        <div class="col-sm-6 col-lg-4 col-xl-3">
                                            <label class="form-label">Nome do cliente (exibição)</label>
                                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ($tenant['name'] ?? '')); ?>" required>
                                        </div>
                                        <div class="col-sm-6 col-lg-4 col-xl-3">
                                            <label class="form-label">E-mail</label>
                                            <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ($tenant['contact_email'] ?? '')); ?>">
                                        </div>
                                        <div class="col-sm-6 col-lg-4 col-xl-3">
                                            <label class="form-label">WhatsApp</label>
                                            <input type="text" class="form-control" name="contact_whatsapp" value="<?php echo htmlspecialchars($_POST['contact_whatsapp'] ?? ($tenant['contact_whatsapp'] ?? '')); ?>">
                                        </div>
                                        <div class="col-sm-6 col-lg-4 col-xl-3">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" name="status">
                                                <option value="active" <?php echo $statusValue === 'active' ? 'selected' : ''; ?>>Ativo</option>
                                                <option value="suspended" <?php echo $statusValue === 'suspended' ? 'selected' : ''; ?>>Suspenso</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <div class="tenant-client-divider"></div>
                                        </div>
                                        <div class="col-12">
                                            <h6 class="mt-1 mb-3">Endereço</h6>
                                        </div>
                                        <div class="col-12">
                                            <div class="row g-3 align-items-end">
                                                <div class="col-12 col-md-3 col-lg-2">
                                                    <label class="form-label">CEP</label>
                                                    <input type="text" class="form-control" name="cep" id="address_cep" inputmode="numeric" pattern="\d{5}-?\d{3}" maxlength="9" value="<?php echo htmlspecialchars($_POST['cep'] ?? ($tenant['cep'] ?? '')); ?>" required>
                                                </div>
                                                <div class="col-12 col-md-auto">
                                                    <label class="form-label d-md-none">&nbsp;</label>
                                                    <button class="btn btn-outline-primary w-100 w-md-auto" type="button" id="btnBuscarCep">
                                                        <i class="fas fa-search me-1"></i>Buscar
                                                    </button>
                                                </div>
                                                <div class="col-12 col-md">
                                                    <label class="form-label">Logradouro</label>
                                                    <input type="text" class="form-control" name="address_street" id="address_street" value="<?php echo htmlspecialchars($_POST['address_street'] ?? ($tenant['address_street'] ?? '')); ?>">
                                                </div>
                                            </div>
                                            <div class="form-text text-danger d-none" id="cepFeedback"></div>
                                        </div>
                                        <div class="col-sm-6 col-md-3 col-lg-2 col-xl-2">
                                            <label class="form-label">Número</label>
                                            <input type="text" class="form-control" name="address_number" id="address_number" value="<?php echo htmlspecialchars($_POST['address_number'] ?? ($tenant['address_number'] ?? '')); ?>">
                                        </div>
                                        <div class="col-sm-6 col-md-5 col-lg-4 col-xl-4">
                                            <label class="form-label">Bairro</label>
                                            <input type="text" class="form-control" name="address_neighborhood" id="address_neighborhood" value="<?php echo htmlspecialchars($_POST['address_neighborhood'] ?? ($tenant['address_neighborhood'] ?? '')); ?>">
                                        </div>
                                        <div class="col-sm-6 col-md-2 col-lg-2 col-xl-2">
                                            <label class="form-label">UF</label>
                                            <select class="form-select" name="state" id="address_state" required data-selected-state="<?php echo htmlspecialchars($_POST['state'] ?? ($tenant['state'] ?? '')); ?>">
                                                <option value="" disabled <?php echo empty($_POST['state'] ?? ($tenant['state'] ?? '')) ? 'selected' : ''; ?>>Selecione a UF</option>
                                                <?php foreach ($brazilianStates as $stateOption): ?>
                                                    <option value="<?php echo $stateOption['uf']; ?>" <?php echo (($POST_state = $_POST['state'] ?? null) !== null ? $POST_state : ($tenant['state'] ?? '')) === $stateOption['uf'] ? 'selected' : ''; ?>>
                                                        <?php echo $stateOption['name']; ?> (<?php echo $stateOption['uf']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-sm-6 col-md-4 col-lg-4 col-xl-4">
                                            <label class="form-label">Cidade</label>
                                            <select class="form-select" name="city" id="address_city" required data-selected-city="<?php echo htmlspecialchars($_POST['city'] ?? ($tenant['city'] ?? '')); ?>" <?php echo empty($_POST['state'] ?? ($tenant['state'] ?? '')) ? 'disabled' : ''; ?>>
                                                <option value="" disabled selected>Selecione a cidade</option>
                                                <?php if (!empty($_POST['city'] ?? ($tenant['city'] ?? '')) && !empty($_POST['state'] ?? ($tenant['state'] ?? ''))): ?>
                                                    <option value="<?php echo htmlspecialchars($_POST['city'] ?? ($tenant['city'] ?? '')); ?>" selected><?php echo htmlspecialchars($_POST['city'] ?? ($tenant['city'] ?? '')); ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Observações</label>
                                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ($tenant['notes'] ?? '')); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" name="update_tenant" value="1" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Salvar alterações
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings-tab">
            <div class="row g-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-brush me-2 text-primary"></i>Configurações do Site</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" data-ajax="true">
                                <div class="row g-3">
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">Nome do site</label>
                                        <input type="text" class="form-control" name="site_name" id="site_name" value="<?php echo htmlspecialchars($_POST['site_name'] ?? ($settings['site_name'] ?? $tenant['name'])); ?>">
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">Slug</label>
                                        <input type="text" class="form-control" name="slug" id="site_slug" value="<?php echo htmlspecialchars($_POST['slug'] ?? ($tenant['slug'] ?? '')); ?>" required>
                                        <small class="text-muted d-block mt-1">Usado em URLs e integrações.</small>
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">E-mail do site</label>
                                        <input type="email" class="form-control" name="site_email" value="<?php echo htmlspecialchars($_POST['site_email'] ?? ($settings['site_email'] ?? '')); ?>">
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">Cor primária</label>
                                        <input type="color" class="form-control form-control-color" name="primary_color" value="<?php echo htmlspecialchars($_POST['primary_color'] ?? ($settings['primary_color'] ?? '#023A8D')); ?>">
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">Cor secundária</label>
                                        <input type="color" class="form-control form-control-color" name="secondary_color" value="<?php echo htmlspecialchars($_POST['secondary_color'] ?? ($settings['secondary_color'] ?? '#F7931E')); ?>">
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">Telefone vendas</label>
                                        <input type="text" class="form-control" name="phone_venda" value="<?php echo htmlspecialchars($_POST['phone_venda'] ?? ($settings['phone_venda'] ?? '')); ?>">
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">Telefone locação</label>
                                        <input type="text" class="form-control" name="phone_locacao" value="<?php echo htmlspecialchars($_POST['phone_locacao'] ?? ($settings['phone_locacao'] ?? '')); ?>">
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">WhatsApp vendas</label>
                                        <input type="text" class="form-control" name="whatsapp_venda" value="<?php echo htmlspecialchars($_POST['whatsapp_venda'] ?? ($settings['whatsapp_venda'] ?? '')); ?>">
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">WhatsApp locação</label>
                                        <input type="text" class="form-control" name="whatsapp_locacao" value="<?php echo htmlspecialchars($_POST['whatsapp_locacao'] ?? ($settings['whatsapp_locacao'] ?? '')); ?>">
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">Instagram</label>
                                        <input type="text" class="form-control" name="instagram_url" value="<?php echo htmlspecialchars($_POST['instagram_url'] ?? ($settings['instagram_url'] ?? '')); ?>">
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xl-3">
                                        <label class="form-label">Facebook</label>
                                        <input type="text" class="form-control" name="facebook_url" value="<?php echo htmlspecialchars($_POST['facebook_url'] ?? ($settings['facebook_url'] ?? '')); ?>">
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
        </div>
        <div class="tab-pane fade" id="tab-domains" role="tabpanel" aria-labelledby="tab-domains-tab">
            <div class="row g-4">
                <div class="col-12 col-xxl-8">
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
                            <div id="tenantDomainsWrapper">
                                <p class="text-muted mb-0 <?php echo empty($domains) ? '' : 'd-none'; ?>" id="tenantDomainsEmpty">Nenhum domínio cadastrado.</p>
                                <ul class="list-group <?php echo empty($domains) ? 'd-none' : ''; ?>" id="tenantDomainsList">
                                    <?php foreach ($domains as $domain): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center" data-domain-id="<?php echo $domain['id']; ?>">
                                            <div>
                                                <strong><?php echo htmlspecialchars($domain['domain']); ?></strong>
                                                <?php if ($domain['is_primary']): ?>
                                                    <span class="badge text-bg-success ms-2">Principal</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!$domain['is_primary']): ?>
                                                <form method="POST" class="mb-0" data-ajax="true">
                                                    <input type="hidden" name="domain_id" value="<?php echo $domain['id']; ?>">
                                                    <button class="btn btn-sm btn-outline-danger" name="delete_domain" value="1">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-users" role="tabpanel" aria-labelledby="tab-users-tab">
            <div class="row g-4">
                <div class="col-12">
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
                                            <tr data-user-row="<?php echo $user['id']; ?>">
                                                <td data-user-name-cell><?php echo htmlspecialchars($user['nome']); ?></td>
                                                <td data-user-email-cell><?php echo htmlspecialchars($user['email']); ?></td>
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
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-dark"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editUserModal"
                                                            data-user-id="<?php echo $user['id']; ?>"
                                                            data-user-name="<?php echo htmlspecialchars($user['nome']); ?>"
                                                            data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                            data-edit-user-button
                                                        >
                                                            <i class="fas fa-pen me-1"></i>Editar usuário
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#setPasswordModal"
                                                            data-user-id="<?php echo $user['id']; ?>"
                                                            data-user-name="<?php echo htmlspecialchars($user['nome']); ?>"
                                                        >
                                                            <i class="fas fa-lock me-1"></i>Definir senha
                                                        </button>
                                                        <form method="POST" class="mb-0" data-ajax="true">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button class="btn btn-sm btn-outline-primary" name="reset_user_password" value="1">
                                                                <i class="fas fa-key me-1"></i>Resetar senha
                                                            </button>
                                                        </form>
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
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var alertContainer = document.getElementById('tenantAlertContainer');
        function showTenantAlert(type, message, options) {
            options = options || {};
            if (!alertContainer) {
                return;
            }
            alertContainer.innerHTML = '';
            var alert = document.createElement('div');
            alert.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-danger');
            var icon = document.createElement('i');
            icon.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation') + ' me-2';
            alert.appendChild(icon);
            var text = document.createElement('span');
            text.textContent = message || '';
            alert.appendChild(text);

            if (options.password) {
                var passwordWrap = document.createElement('div');
                passwordWrap.className = 'mt-2';
                var strong = document.createElement('strong');
                strong.textContent = 'Nova senha:';
                passwordWrap.appendChild(strong);
                var span = document.createElement('span');
                span.className = 'fw-bold text-primary ms-1';
                span.textContent = options.password;
                passwordWrap.appendChild(span);
                alert.appendChild(passwordWrap);
            }

            alertContainer.appendChild(alert);
            setTimeout(function () {
                alertContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }

        function hideModalForForm(form) {
            if (!form) {
                return;
            }
            var modalElement = form.closest('.modal');
            if (modalElement && typeof bootstrap !== 'undefined') {
                var modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }

        function updateUserRow(user) {
            if (!user) {
                return;
            }
            var row = document.querySelector('[data-user-row="' + user.id + '"]');
            if (!row) {
                return;
            }
            var nameCell = row.querySelector('[data-user-name-cell]');
            if (nameCell) {
                nameCell.textContent = user.nome || '';
            }
            var emailCell = row.querySelector('[data-user-email-cell]');
            if (emailCell) {
                emailCell.textContent = user.email || '';
            }
            var editButton = row.querySelector('[data-edit-user-button]');
            if (editButton) {
                editButton.setAttribute('data-user-name', user.nome || '');
                editButton.setAttribute('data-user-email', user.email || '');
            }
        }

        function attachAjaxToForm(form) {
            if (!form || form.dataset.ajaxBound === '1') {
                return;
            }
            form.addEventListener('submit', handleAjaxFormSubmit);
            form.dataset.ajaxBound = '1';
        }

        function refreshAjaxForms(root) {
            (root || document).querySelectorAll('form[data-ajax="true"]').forEach(attachAjaxToForm);
        }

        function renderDomainsList(domains) {
            var list = document.getElementById('tenantDomainsList');
            var empty = document.getElementById('tenantDomainsEmpty');
            if (!list || !empty) {
                return;
            }

            if (!Array.isArray(domains) || domains.length === 0) {
                list.innerHTML = '';
                list.classList.add('d-none');
                empty.classList.remove('d-none');
                return;
            }

            empty.classList.add('d-none');
            list.classList.remove('d-none');
            list.innerHTML = '';

            domains.forEach(function (domain) {
                var li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                li.dataset.domainId = domain.id;

                var info = document.createElement('div');
                var strong = document.createElement('strong');
                strong.textContent = domain.domain;
                info.appendChild(strong);

                if (Number(domain.is_primary)) {
                    var badge = document.createElement('span');
                    badge.className = 'badge text-bg-success ms-2';
                    badge.textContent = 'Principal';
                    info.appendChild(badge);
                }

                li.appendChild(info);

                if (!Number(domain.is_primary)) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.className = 'mb-0';
                    form.dataset.ajax = 'true';

                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'domain_id';
                    input.value = domain.id;
                    form.appendChild(input);

                    var button = document.createElement('button');
                    button.className = 'btn btn-sm btn-outline-danger';
                    button.name = 'delete_domain';
                    button.value = '1';
                    button.innerHTML = '<i class="fas fa-trash"></i>';
                    form.appendChild(button);

                    attachAjaxToForm(form);
                    li.appendChild(form);
                }

                list.appendChild(li);
            });
        }

        function processAjaxAction(data, form) {
            var action = data.action || '';
            switch (action) {
                case 'update_tenant':
                    if (data.tenant && data.tenant.slug) {
                        var tenantSlugInput = document.querySelector('input[name="slug"]');
                        if (tenantSlugInput) {
                            tenantSlugInput.value = data.tenant.slug;
                        }
                    }
                    showTenantAlert('success', data.message || 'Informações do cliente atualizadas com sucesso.');
                    break;
                case 'update_settings':
                    if (data.slug) {
                        var slugInput = document.querySelector('input[name="slug"]');
                        if (slugInput) {
                            slugInput.value = data.slug;
                            slugInput.dataset.autofill = '0';
                        }
                    }
                    showTenantAlert('success', data.message || 'Configurações atualizadas.');
                    break;
                case 'add_domain':
                    renderDomainsList(data.domains || []);
                    showTenantAlert('success', data.message || 'Domínio adicionado.');
                    if (form) {
                        form.reset();
                    }
                    hideModalForForm(form);
                    break;
                case 'delete_domain':
                    renderDomainsList(data.domains || []);
                    showTenantAlert('success', data.message || 'Domínio removido.');
                    break;
                case 'reset_user_password':
                    showTenantAlert('success', data.message || 'Senha redefinida.', {
                        password: data.password
                    });
                    break;
                case 'set_user_password':
                    showTenantAlert('success', data.message || 'Senha atualizada com sucesso.');
                    hideModalForForm(form);
                    if (form) {
                        form.reset();
                    }
                    break;
                case 'update_user_email':
                    if (data.user) {
                        updateUserRow(data.user);
                    }
                    showTenantAlert('success', data.message || 'Usuário atualizado com sucesso.');
                    hideModalForForm(form);
                    break;
                default:
                    if (data.status === 'success') {
                        showTenantAlert('success', data.message || 'Operação realizada com sucesso.');
                    } else {
                        showTenantAlert('error', data.message || 'Não foi possível concluir a operação.');
                    }
            }
        }

        function handleAjaxFormSubmit(event) {
            event.preventDefault();
            var form = event.target;
            var submitter = event.submitter;
            var formData = new FormData(form);

            var submitReference = submitter;
            if (submitReference && submitReference.name) {
                formData.append(submitReference.name, submitReference.value === undefined ? '1' : submitReference.value);
            } else {
                var fallbackSubmitter = form.querySelector('button[type="submit"][name], input[type="submit"][name]');
                if (fallbackSubmitter && fallbackSubmitter.name) {
                    formData.append(fallbackSubmitter.name, fallbackSubmitter.value === undefined ? '1' : fallbackSubmitter.value);
                    submitReference = fallbackSubmitter;
                }
            }

            if (submitReference) {
                submitReference.disabled = true;
            }

            fetch(form.getAttribute('action') || window.location.href, {
                method: (form.getAttribute('method') || 'POST').toUpperCase(),
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                }).catch(function () {
                    return { ok: response.ok, data: null };
                });
            }).then(function (result) {
                var data = result.data || {};
                if (!result.ok || data.status !== 'success') {
                    showTenantAlert('error', data.message || 'Não foi possível concluir a operação.');
                    return;
                }
                processAjaxAction(data, form);
            }).catch(function () {
                showTenantAlert('error', 'Erro ao comunicar com o servidor. Tente novamente.');
            }).finally(function () {
                if (submitReference) {
                    submitReference.disabled = false;
                }
                refreshAjaxForms(document);
            });
        }

        refreshAjaxForms(document);

        var typeRadios = document.querySelectorAll('input[name="client_type"]');
        var typeBlocks = document.querySelectorAll('[data-client-type-block]');
        var personFullNameInput = document.querySelector('input[name="person_full_name"]');
        var clientDisplayNameInput = document.querySelector('input[name="name"]');
        var siteNameInput = document.querySelector('input[name="site_name"]');
        var slugInput = document.querySelector('input[name="slug"]');
        var clientNameEdited = false;
        var slugInputAutofill = slugInput && slugInput.value.trim() === '' ? '1' : '0';
        if (slugInput) {
            slugInput.dataset.autofill = slugInputAutofill;
        }
        var cepInput = document.getElementById('address_cep');
        var buscarCepBtn = document.getElementById('btnBuscarCep');
        var cepFeedback = document.getElementById('cepFeedback');
        var addressStreetInput = document.getElementById('address_street');
        var addressNeighborhoodInput = document.getElementById('address_neighborhood');
        var stateSelect = document.getElementById('address_state');
        var citySelect = document.getElementById('address_city');
        var tenantClientForm = document.getElementById('tenantClientForm');
        var citiesCache = {};
        var cepAbortController = null;

        function toggleTypeBlocks(selectedType) {
            typeBlocks.forEach(function (block) {
                var blockType = block.getAttribute('data-client-type-block');
                var shouldShow = selectedType === blockType;
                block.classList.toggle('d-none', !shouldShow);

                var conditionalFields = block.querySelectorAll('[data-client-type-required]');
                conditionalFields.forEach(function (field) {
                    field.required = shouldShow && field.getAttribute('data-client-type-required') === blockType;
                });
            });
        }

        function normalizeCep(value) {
            return (value || '').replace(/\D/g, '').slice(0, 8);
        }

        function formatCep(value) {
            var digits = normalizeCep(value);
            if (digits.length > 5) {
                return digits.slice(0, 5) + '-' + digits.slice(5);
            }
            return digits;
        }

        function slugify(text) {
            return text
                .toString()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }

        function showCepMessage(message, isError) {
            if (!cepFeedback) {
                return;
            }
            cepFeedback.textContent = message;
            cepFeedback.classList.toggle('d-none', !message);
            cepFeedback.classList.toggle('text-danger', !!isError);
            cepFeedback.classList.toggle('text-success', !isError);
        }

        function clearCepMessage() {
            if (cepFeedback) {
                cepFeedback.textContent = '';
                cepFeedback.classList.add('d-none');
                cepFeedback.classList.remove('text-danger');
                cepFeedback.classList.remove('text-success');
            }
        }

        function loadCitiesForState(uf, selectedCity) {
            if (!stateSelect || !citySelect) {
                return Promise.resolve();
            }
            if (!uf) {
                citySelect.innerHTML = '<option value="" disabled selected>Selecione a cidade</option>';
                citySelect.disabled = true;
                return Promise.resolve();
            }
            citySelect.dataset.selectedCity = selectedCity || '';
            citySelect.disabled = true;
            citySelect.innerHTML = '<option value="" disabled selected>Carregando cidades...</option>';
            var fetchPromise;
            if (citiesCache[uf]) {
                fetchPromise = Promise.resolve(citiesCache[uf]);
            } else {
                fetchPromise = fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + uf + '/municipios')
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Erro ao carregar cidades');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        citiesCache[uf] = data.map(function (item) {
                            return item.nome;
                        });
                        return citiesCache[uf];
                    })
                    .catch(function () {
                        citiesCache[uf] = [];
                        return [];
                    });
            }

            return fetchPromise.then(function (cities) {
                citySelect.innerHTML = '<option value="" disabled selected>Selecione a cidade</option>';
                if (cities.length === 0) {
                    citySelect.innerHTML = '<option value="" disabled selected>Não foi possível carregar as cidades</option>';
                    citySelect.disabled = false;
                    return;
                }
                cities.forEach(function (cityName) {
                    var option = document.createElement('option');
                    option.value = cityName;
                    option.textContent = cityName;
                    citySelect.appendChild(option);
                });
                if (selectedCity) {
                    var cityMatch = cities.find(function (cityName) {
                        return cityName.toLowerCase() === selectedCity.toLowerCase();
                    });
                    if (cityMatch) {
                        citySelect.value = cityMatch;
                        citySelect.dataset.selectedCity = cityMatch;
                    }
                }
                citySelect.disabled = false;
            }).catch(function () {
                citySelect.innerHTML = '<option value="" disabled selected>Selecione a cidade</option>';
                citySelect.disabled = false;
            });
        }

        function fillAddressFieldsFromCep(data) {
            if (!data) {
                return;
            }
            if (addressStreetInput && data.logradouro) {
                addressStreetInput.value = data.logradouro;
            }
            if (addressNeighborhoodInput && data.bairro) {
                addressNeighborhoodInput.value = data.bairro;
            }
            if (stateSelect && data.uf) {
                stateSelect.value = data.uf;
                stateSelect.dataset.selectedState = data.uf;
            }
            if (citySelect && data.localidade) {
                citySelect.dataset.selectedCity = data.localidade;
                loadCitiesForState(data.uf, data.localidade);
            }
        }

        function handleCepLookup() {
            if (!cepInput) {
                return;
            }
            var cepDigits = normalizeCep(cepInput.value);
            if (cepDigits.length !== 8) {
                showCepMessage('Informe um CEP válido no formato 00000-000.', true);
                cepInput.setCustomValidity('Informe um CEP válido no formato 00000-000.');
                return;
            }
            cepInput.setCustomValidity('');
            clearCepMessage();

            if (cepAbortController) {
                cepAbortController.abort();
            }

            cepAbortController = new AbortController();
            showCepMessage('Buscando endereço...', false);

            fetch('https://viacep.com.br/ws/' + cepDigits + '/json/', { signal: cepAbortController.signal })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Erro na consulta de CEP');
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (data.erro) {
                        showCepMessage('CEP não encontrado, preencha o endereço manualmente.', true);
                        return;
                    }
                    showCepMessage('Endereço localizado. Verifique os dados antes de salvar.', false);
                    fillAddressFieldsFromCep(data);
                })
                .catch(function (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    showCepMessage('Não foi possível consultar o CEP. Preencha o endereço manualmente.', true);
                });
        }

        function validateCepField() {
            if (!cepInput) {
                return true;
            }
            var cepDigits = normalizeCep(cepInput.value);
            if (cepDigits.length === 0) {
                cepInput.setCustomValidity('Informe o CEP.');
                showCepMessage('Informe o CEP.', true);
                return false;
            }
            if (cepDigits.length !== 8) {
                cepInput.setCustomValidity('CEP deve ter 8 dígitos (00000-000).');
                showCepMessage('CEP deve ter 8 dígitos (00000-000).', true);
                return false;
            }
            cepInput.setCustomValidity('');
            return true;
        }

        typeRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                toggleTypeBlocks(this.value);
            });
        });

        if (clientDisplayNameInput) {
            clientDisplayNameInput.addEventListener('input', function () {
                clientNameEdited = this.value.trim() !== '';
            });
        }

        if (personFullNameInput && clientDisplayNameInput) {
            personFullNameInput.addEventListener('input', function () {
                if (!clientNameEdited && clientDisplayNameInput.value.trim() === '') {
                    clientDisplayNameInput.value = this.value;
                }
            });
        }

        if (slugInput) {
            slugInput.addEventListener('input', function () {
                this.dataset.autofill = '0';
            });
        }

        if (siteNameInput && slugInput) {
            siteNameInput.addEventListener('input', function () {
                if (slugInput.dataset.autofill !== '0' || slugInput.value.trim() === '') {
                    var newSlug = slugify(this.value);
                    slugInput.value = newSlug;
                    slugInput.dataset.autofill = '1';
                }
            });
        }

        if (cepInput) {
            cepInput.addEventListener('input', function (e) {
                var formatted = formatCep(e.target.value);
                e.target.value = formatted;
                cepInput.setCustomValidity('');
                if (formatted.length === 9) {
                    clearCepMessage();
                }
            });
            cepInput.addEventListener('blur', function () {
                if (validateCepField()) {
                    handleCepLookup();
                }
            });
        }

        if (buscarCepBtn) {
            buscarCepBtn.addEventListener('click', function () {
                if (validateCepField()) {
                    handleCepLookup();
                }
            });
        }

        if (stateSelect) {
            stateSelect.addEventListener('change', function () {
                var uf = this.value;
                if (citySelect) {
                    citySelect.dataset.selectedCity = '';
                }
                loadCitiesForState(uf).then(function () {
                    if (citySelect) {
                        citySelect.focus();
                    }
                });
            });
        }

        if (tenantClientForm) {
            tenantClientForm.addEventListener('submit', function (event) {
                var cepValid = validateCepField();
                if (!cepValid) {
                    event.preventDefault();
                    if (cepInput) {
                        cepInput.focus();
                    }
                    return;
                }
                if (stateSelect && !stateSelect.value) {
                    event.preventDefault();
                    showCepMessage('Selecione a UF para concluir o endereço.', true);
                    stateSelect.focus();
                    return;
                }
                if (citySelect && (citySelect.disabled || !citySelect.value)) {
                    event.preventDefault();
                    showCepMessage('Selecione a cidade para concluir o endereço.', true);
                    citySelect.focus();
                }
            });
        }

        var initialSelected = document.querySelector('input[name="client_type"]:checked');
        toggleTypeBlocks(initialSelected ? initialSelected.value : '');

        if (stateSelect) {
            var initialState = stateSelect.dataset.selectedState || stateSelect.value;
            var initialCity = citySelect ? (citySelect.dataset.selectedCity || citySelect.value) : '';
            if (initialState) {
                loadCitiesForState(initialState, initialCity);
            }
        }

        var setPasswordModal = document.getElementById('setPasswordModal');
        if (setPasswordModal) {
            setPasswordModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) {
                    return;
                }

                var userId = button.getAttribute('data-user-id') || '';
                var userName = button.getAttribute('data-user-name') || '';
                var userIdInput = setPasswordModal.querySelector('input[name="user_id"]');
                var userNamePlaceholder = setPasswordModal.querySelector('[data-set-password-user]');
                var passwordInputs = setPasswordModal.querySelectorAll('input[type="password"]');

                if (userIdInput) {
                    userIdInput.value = userId;
                }

                if (userNamePlaceholder) {
                    userNamePlaceholder.textContent = userName || 'usuário';
                }

                passwordInputs.forEach(function (input) {
                    input.value = '';
                });
            });
        }

        var editUserModal = document.getElementById('editUserModal');
        if (editUserModal) {
            editUserModal.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                if (!trigger) {
                    return;
                }

                var userId = trigger.getAttribute('data-user-id') || '';
                var userName = trigger.getAttribute('data-user-name') || '';
                var userEmail = trigger.getAttribute('data-user-email') || '';

                var userIdInput = editUserModal.querySelector('input[name="user_id"]');
                var nameInput = editUserModal.querySelector('input[name="new_name"]');
                var emailInput = editUserModal.querySelector('input[name="new_email"]');

                if (userIdInput) {
                    userIdInput.value = userId;
                }
                if (nameInput) {
                    nameInput.value = userName;
                }
                if (emailInput) {
                    emailInput.value = userEmail;
                }
            });
        }
    });
</script>

<!-- Modal adicionar domínio -->
<div class="modal fade" id="addDomainModal" tabindex="-1" aria-labelledby="addDomainModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-ajax="true">
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

<!-- Modal editar usuário -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-ajax="true">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Editar usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" value="">
                    <div class="mb-3">
                        <label class="form-label" for="editUserName">Nome</label>
                        <input type="text" class="form-control" id="editUserName" name="new_name" required maxlength="100" autocomplete="name">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="editUserEmail">E-mail</label>
                        <input type="email" class="form-control" id="editUserEmail" name="new_email" required maxlength="120" autocomplete="email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="update_user_email" value="1" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Salvar alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal definir senha manualmente -->
<div class="modal fade" id="setPasswordModal" tabindex="-1" aria-labelledby="setPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" data-ajax="true">
                <div class="modal-header">
                    <h5 class="modal-title" id="setPasswordModalLabel">Definir senha manualmente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" value="">
                    <p class="text-muted small mb-3">Informe uma nova senha para <strong data-set-password-user></strong>. Ela deve ter no mínimo 8 caracteres.</p>
                    <div class="mb-3">
                        <label class="form-label" for="setPasswordNew">Nova senha</label>
                        <input type="password" class="form-control" id="setPasswordNew" name="new_password" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="setPasswordConfirm">Confirmar nova senha</label>
                        <input type="password" class="form-control" id="setPasswordConfirm" name="confirm_password" minlength="8" required autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="set_user_password" value="1" class="btn btn-primary">
                        <i class="fas fa-check me-1"></i>Salvar senha
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

