<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configurações ANTES de iniciar a sessão
require_once '../../config/paths.php';
require_once '../../config/database.php';
require_once '../../config/tenant.php';
require_once '../../config/config.php';

// Agora iniciar a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado e pertence ao tenant atual
if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !isset($_SESSION['tenant_id']) ||
    (int)$_SESSION['tenant_id'] !== TENANT_ID
) {
    header('Location: ../login.php');
    exit;
}

// Apenas administradores podem editar esses dados
if (!isset($_SESSION['admin_nivel']) || $_SESSION['admin_nivel'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';
$tenantData = currentTenant();

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

$clientType = $tenantData['client_type'] ?? 'pf';
$statusValue = $tenantData['status'] ?? 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_client_data'])) {
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

        $clientTypeInput = strtolower($normalizeLine($_POST['client_type'] ?? ''));
        if (!in_array($clientTypeInput, ['pf', 'pj'], true)) {
            throw new Exception('Selecione se o cliente é Pessoa Física ou Jurídica.');
        }

        $nameLine = $normalizeLine($_POST['name'] ?? ($tenantData['name'] ?? ''));
        if ($nameLine === '') {
            throw new Exception('Informe o nome de exibição do cliente.');
        }
        $name = cleanInput($nameLine);

        $personFullName = $toNullableLine($_POST['person_full_name'] ?? null);
        $personCpfDigits = $digitsOnly($_POST['person_cpf'] ?? null);
        $personCpf = $personCpfDigits === '' ? null : cleanInput($personCpfDigits);
        $personCreci = $toNullableLine($_POST['person_creci'] ?? null);

        if ($clientTypeInput === 'pf') {
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

        if ($clientTypeInput === 'pj') {
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
            throw new Exception('CEP deve ter 8 dígitos.');
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
            throw new Exception('Informe a cidade.');
        }
        $city = cleanInput($cityLine);

        $notes = $toNullableRaw($_POST['notes'] ?? null);

        $updatePayload = [
            'name' => $name,
            'client_type' => $clientTypeInput,
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

        update('tenants', $updatePayload, 'id = ?', [TENANT_ID]);

        $tenantData = fetch("SELECT * FROM tenants WHERE id = ?", [TENANT_ID]) ?: $tenantData;
        $GLOBALS['current_tenant'] = $tenantData;
        $clientType = $tenantData['client_type'] ?? $clientTypeInput;
        $statusValue = $tenantData['status'] ?? $statusValue;

        $success = 'Dados salvos com sucesso!';
        $_POST = [];
    } catch (Exception $e) {
        $error = $e->getMessage();
        $clientType = $_POST['client_type'] ?? $clientType;
    }
} else {
    $clientType = $tenantData['client_type'] ?? $clientType;
}

$page_title = 'Dados do Cliente';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2">Dados do Cliente</h1>
        <p class="text-muted mb-0">Sincroniza automaticamente com o painel master.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header">
        <h5 class="m-0">
            <i class="fas fa-building me-2"></i>
            Informações da imobiliária / corretor
        </h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="save_client_data" value="1">

            <div class="mb-4">
                <label class="form-label d-block">Tipo de cliente</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="client_type" id="client_type_pf" value="pf" <?php echo ($clientType ?? '') === 'pf' ? 'checked' : ''; ?> required>
                    <label class="form-check-label" for="client_type_pf">Pessoa Física</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="client_type" id="client_type_pj" value="pj" <?php echo ($clientType ?? '') === 'pj' ? 'checked' : ''; ?> required>
                    <label class="form-check-label" for="client_type_pj">Pessoa Jurídica</label>
                </div>
            </div>

            <div class="tenant-client-block mb-4 tenant-client-block-specific <?php echo ($clientType ?? '') === 'pf' ? '' : 'd-none'; ?>" data-client-type-block="pf">
                <h6>Dados da Pessoa Física</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nome completo</label>
                        <input type="text" class="form-control" name="person_full_name" value="<?php echo htmlspecialchars($_POST['person_full_name'] ?? ($tenantData['person_full_name'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CPF</label>
                        <input type="text" class="form-control" name="person_cpf" value="<?php echo htmlspecialchars($_POST['person_cpf'] ?? ($tenantData['person_cpf'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CRECI</label>
                        <input type="text" class="form-control" name="person_creci" value="<?php echo htmlspecialchars($_POST['person_creci'] ?? ($tenantData['person_creci'] ?? '')); ?>">
                    </div>
                </div>
            </div>

            <div class="tenant-client-block mb-4 tenant-client-block-specific <?php echo ($clientType ?? '') === 'pj' ? '' : 'd-none'; ?>" data-client-type-block="pj">
                <h6>Dados da Pessoa Jurídica</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Razão social</label>
                        <input type="text" class="form-control" name="company_legal_name" value="<?php echo htmlspecialchars($_POST['company_legal_name'] ?? ($tenantData['company_legal_name'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nome fantasia</label>
                        <input type="text" class="form-control" name="company_trade_name" value="<?php echo htmlspecialchars($_POST['company_trade_name'] ?? ($tenantData['company_trade_name'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CNPJ</label>
                        <input type="text" class="form-control" name="company_cnpj" value="<?php echo htmlspecialchars($_POST['company_cnpj'] ?? ($tenantData['company_cnpj'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CRECI (imobiliária)</label>
                        <input type="text" class="form-control" name="company_creci" value="<?php echo htmlspecialchars($_POST['company_creci'] ?? ($tenantData['company_creci'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Responsável legal</label>
                        <input type="text" class="form-control" name="company_responsible_name" value="<?php echo htmlspecialchars($_POST['company_responsible_name'] ?? ($tenantData['company_responsible_name'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CPF do responsável</label>
                        <input type="text" class="form-control" name="company_responsible_cpf" value="<?php echo htmlspecialchars($_POST['company_responsible_cpf'] ?? ($tenantData['company_responsible_cpf'] ?? '')); ?>">
                    </div>
                </div>
            </div>

            <div class="tenant-client-block mb-4">
                <h6>Dados comuns</h6>
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">Nome (exibição)</label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ($tenantData['name'] ?? '')); ?>" required>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">E-mail principal</label>
                        <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ($tenantData['contact_email'] ?? '')); ?>">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">WhatsApp</label>
                        <input type="text" class="form-control" name="contact_whatsapp" value="<?php echo htmlspecialchars($_POST['contact_whatsapp'] ?? ($tenantData['contact_whatsapp'] ?? '')); ?>">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">Status</label>
                        <input type="text" class="form-control" value="<?php echo $statusValue === 'suspended' ? 'Suspenso' : 'Ativo'; ?>" disabled>
                        <div class="form-text">Alterações de status são feitas com a equipe Imobsites.</div>
                    </div>
                </div>
            </div>

            <div class="tenant-client-block mb-4">
                <h6>Endereço</h6>
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label">CEP</label>
                        <input type="text" class="form-control" name="cep" id="address_cep" inputmode="numeric" maxlength="9" value="<?php echo htmlspecialchars($_POST['cep'] ?? ($tenantData['cep'] ?? '')); ?>" required>
                        <div class="form-text text-danger d-none" id="cepFeedback"></div>
                    </div>
                    <div class="col-12 col-md-auto">
                        <label class="form-label d-md-none">&nbsp;</label>
                        <button class="btn btn-outline-primary w-100 w-md-auto" type="button" id="btnBuscarCep">
                            <i class="fas fa-search me-1"></i>Buscar
                        </button>
                    </div>
                    <div class="col-12 col-md">
                        <label class="form-label">Logradouro</label>
                        <input type="text" class="form-control" name="address_street" id="address_street" value="<?php echo htmlspecialchars($_POST['address_street'] ?? ($tenantData['address_street'] ?? '')); ?>">
                    </div>
                </div>
                <div class="row g-3 mt-0">
                    <div class="col-sm-6 col-md-3 col-lg-2">
                        <label class="form-label">Número</label>
                        <input type="text" class="form-control" name="address_number" id="address_number" value="<?php echo htmlspecialchars($_POST['address_number'] ?? ($tenantData['address_number'] ?? '')); ?>">
                    </div>
                    <div class="col-sm-6 col-md-5 col-lg-3">
                        <label class="form-label">Bairro</label>
                        <input type="text" class="form-control" name="address_neighborhood" id="address_neighborhood" value="<?php echo htmlspecialchars($_POST['address_neighborhood'] ?? ($tenantData['address_neighborhood'] ?? '')); ?>">
                    </div>
                    <div class="col-sm-6 col-md-2 col-lg-2">
                        <label class="form-label">UF</label>
                        <select class="form-select" name="state" id="address_state" required data-selected-state="<?php echo htmlspecialchars($_POST['state'] ?? ($tenantData['state'] ?? '')); ?>">
                            <option value="" disabled <?php echo empty($_POST['state'] ?? ($tenantData['state'] ?? '')) ? 'selected' : ''; ?>>UF</option>
                            <?php foreach ($brazilianStates as $stateOption): ?>
                                <option value="<?php echo $stateOption['uf']; ?>" <?php echo (($POST_state = $_POST['state'] ?? null) !== null ? $POST_state : ($tenantData['state'] ?? '')) === $stateOption['uf'] ? 'selected' : ''; ?>>
                                    <?php echo $stateOption['name']; ?> (<?php echo $stateOption['uf']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <label class="form-label">Cidade</label>
                        <input type="text" class="form-control" name="city" id="address_city" value="<?php echo htmlspecialchars($_POST['city'] ?? ($tenantData['city'] ?? '')); ?>" required>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Observações internas</label>
                <textarea class="form-control" name="notes" rows="4" placeholder="Informações adicionais sobre sua imobiliária ou operação."><?php echo htmlspecialchars($_POST['notes'] ?? ($tenantData['notes'] ?? '')); ?></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="../index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Salvar dados
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
const clientTypeRadios = document.querySelectorAll('input[name="client_type"]');
const clientBlocks = document.querySelectorAll('[data-client-type-block]');

function toggleClientBlocks(selectedType) {
    clientBlocks.forEach(block => {
        block.classList.toggle('d-none', block.getAttribute('data-client-type-block') !== selectedType);
    });
}

clientTypeRadios.forEach(radio => {
    radio.addEventListener('change', (event) => {
        toggleClientBlocks(event.target.value);
    });
});

// Estado selecionado no carregamento
const stateSelect = document.getElementById('address_state');
const selectedState = stateSelect?.dataset?.selectedState;
if (stateSelect && selectedState) {
    stateSelect.value = selectedState;
}

// Consulta CEP via ViaCEP
let cepAbortController = null;
const cepInput = document.getElementById('address_cep');
const cepFeedback = document.getElementById('cepFeedback');
const streetInput = document.getElementById('address_street');
const neighborhoodInput = document.getElementById('address_neighborhood');
const cityInput = document.getElementById('address_city');

function setCepError(message) {
    if (!cepFeedback) return;
    if (message) {
        cepFeedback.textContent = message;
        cepFeedback.classList.remove('d-none');
    } else {
        cepFeedback.textContent = '';
        cepFeedback.classList.add('d-none');
    }
}

function normalizeCep(value) {
    return (value || '').replace(/\D+/g, '').slice(0, 8);
}

async function buscarCep() {
    const cepDigits = normalizeCep(cepInput.value);
    if (cepDigits.length !== 8) {
        setCepError('Informe um CEP válido com 8 dígitos.');
        return;
    }

    setCepError('');

    if (cepAbortController) {
        cepAbortController.abort();
    }
    cepAbortController = new AbortController();

    try {
        const response = await fetch('https://viacep.com.br/ws/' + cepDigits + '/json/', { signal: cepAbortController.signal });
        if (!response.ok) {
            throw new Error('Não foi possível consultar o CEP.');
        }
        const data = await response.json();
        if (data.erro) {
            setCepError('CEP não encontrado.');
            return;
        }
        cepInput.value = data.cep ?? cepDigits.replace(/(\d{5})(\d{3})/, '$1-$2');
        if (streetInput && !streetInput.value) {
            streetInput.value = data.logradouro ?? '';
        }
        if (neighborhoodInput && !neighborhoodInput.value) {
            neighborhoodInput.value = data.bairro ?? '';
        }
        if (cityInput && !cityInput.value) {
            cityInput.value = data.localidade ?? '';
        }
        if (stateSelect && !stateSelect.value) {
            stateSelect.value = data.uf ?? '';
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            setCepError(error.message || 'Erro ao buscar CEP.');
        }
    }
}

document.getElementById('btnBuscarCep')?.addEventListener('click', buscarCep);

if (cepInput) {
    cepInput.addEventListener('input', () => {
        const digits = normalizeCep(cepInput.value);
        cepInput.value = digits.replace(/(\d{5})(\d{1,3})?/, (full, part1, part2) => part2 ? part1 + '-' + part2 : part1);
        if (digits.length !== 8) {
            setCepError('');
        }
    });
}
</script>

<?php
// Finalizar output buffering
ob_end_flush();
?>

