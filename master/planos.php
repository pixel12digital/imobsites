<?php
session_start();

require_once '../config/paths.php';
require_once '../config/database.php';
require_once '../config/master.php';
require_once 'utils.php';
require_once 'includes/PlanService.php';

$error = '';
$success = '';
$editPlan = null;

if (!isset($_SESSION['master_logged_in']) || $_SESSION['master_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_plan'])) {
            $planId = isset($_POST['plan_id']) && $_POST['plan_id'] !== '' ? (int)$_POST['plan_id'] : null;

            $priceInput = str_replace(['.', ','], ['', '.'], trim((string)($_POST['price_per_month'] ?? '0')));
            $pricePerMonth = (float)$priceInput;

            $featuresInput = $_POST['features_list'] ?? '';

            $payload = [
                'code' => $_POST['code'] ?? '',
                'name' => $_POST['name'] ?? '',
                'billing_cycle' => $_POST['billing_cycle'] ?? 'mensal',
                'months' => (int)($_POST['months'] ?? 1),
                'price_per_month' => $pricePerMonth,
                'description_short' => $_POST['description_short'] ?? null,
                'features_json' => $featuresInput,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            ];

            $savedId = createOrUpdatePlan($payload, $planId);

            if (!empty($payload['is_featured'])) {
                markPlanAsFeatured($savedId);
            }

            header('Location: planos.php?success=1');
            exit;
        }

        if (isset($_POST['toggle_plan'])) {
            $planId = (int)($_POST['plan_id'] ?? 0);
            $newStatus = (bool)($_POST['active'] ?? 0);
            togglePlanActive($planId, $newStatus);
            header('Location: planos.php?success=1');
            exit;
        }

        if (isset($_POST['feature_plan'])) {
            $planId = (int)($_POST['plan_id'] ?? 0);
            markPlanAsFeatured($planId);
            header('Location: planos.php?success=1');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $planId = (int)$_GET['edit'];
    $editPlan = getPlanById($planId);
    if (!$editPlan) {
        $error = 'Plano não encontrado para edição.';
    }
}

if (isset($_GET['success'])) {
    $success = 'Plano salvo com sucesso.';
}

$plans = getAllPlans();

include 'includes/header.php';

/**
 * Helper para converter o JSON de features em linhas.
 *
 * @param array<string,mixed>|null $plan
 * @return string
 */
function renderFeaturesTextarea(?array $plan): string
{
    if (!$plan || empty($plan['features_json'])) {
        return '';
    }

    $decoded = json_decode((string)$plan['features_json'], true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return '';
    }

    return implode(PHP_EOL, array_map('trim', $decoded));
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="page-title mb-1"><i class="fas fa-tags me-2 text-primary"></i>Planos</h1>
        <p class="page-subtitle mb-0">Gerencie os planos oferecidos na página de vendas.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="fas fa-circle-exclamation me-2"></i>
        <div><?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <div><?php echo htmlspecialchars($success); ?></div>
    </div>
<?php endif; ?>

<div class="card mb-4 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-pen-to-square me-2 text-primary"></i>
            <?php echo $editPlan ? 'Editar plano' : 'Cadastrar novo plano'; ?>
        </h5>
        <?php if ($editPlan): ?>
            <a class="btn btn-sm btn-outline-secondary" href="planos.php">
                <i class="fas fa-plus me-1"></i>Novo plano
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="save_plan" value="1">
            <?php if ($editPlan): ?>
                <input type="hidden" name="plan_id" value="<?php echo (int)$editPlan['id']; ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Código</label>
                    <input type="text" class="form-control" name="code" maxlength="50" required value="<?php echo htmlspecialchars($editPlan['code'] ?? ''); ?>">
                    <div class="form-text text-muted">Usado como identificador no checkout.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nome</label>
                    <input type="text" class="form-control" name="name" maxlength="100" required value="<?php echo htmlspecialchars($editPlan['name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ciclo de cobrança</label>
                    <select class="form-select" name="billing_cycle" required>
                        <?php
                        $cycles = ['mensal' => 'Mensal', 'trimestral' => 'Trimestral', 'semestral' => 'Semestral', 'anual' => 'Anual'];
                        $selectedCycle = strtolower($editPlan['billing_cycle'] ?? 'mensal');
                        foreach ($cycles as $value => $label):
                        ?>
                            <option value="<?php echo $value; ?>" <?php echo $selectedCycle === $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantidade de meses</label>
                    <input type="number" class="form-control" name="months" min="1" value="<?php echo htmlspecialchars((string)($editPlan['months'] ?? 1)); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Preço mensal (R$)</label>
                    <input type="text" class="form-control" name="price_per_month" inputmode="decimal" value="<?php echo isset($editPlan['price_per_month']) ? number_format((float)$editPlan['price_per_month'], 2, ',', '') : ''; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordem de exibição</label>
                    <input type="number" class="form-control" name="sort_order" value="<?php echo htmlspecialchars((string)($editPlan['sort_order'] ?? 0)); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-center">
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo !isset($editPlan['is_active']) || (int)$editPlan['is_active'] === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Ativo</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" <?php echo isset($editPlan['is_featured']) && (int)$editPlan['is_featured'] === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_featured">Destacar</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descrição curta</label>
                    <input type="text" class="form-control" name="description_short" maxlength="191" value="<?php echo htmlspecialchars($editPlan['description_short'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Benefícios (um por linha)</label>
                    <textarea class="form-control" name="features_list" rows="4" placeholder="- Site completo&#10;- Suporte dedicado"><?php echo htmlspecialchars(renderFeaturesTextarea($editPlan)); ?></textarea>
                    <div class="form-text text-muted">Será convertido automaticamente em JSON.</div>
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-secondary">Limpar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Salvar plano
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-table-list me-2 text-primary"></i>Planos cadastrados</h5>
    </div>
    <div class="card-body">
        <?php if (empty($plans)): ?>
            <p class="text-muted mb-0">Nenhum plano cadastrado ainda.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Código</th>
                        <th>Ciclo</th>
                        <th>Meses</th>
                        <th>Preço mensal</th>
                        <th>Valor total</th>
                        <th>Ativo</th>
                        <th>Destaque</th>
                        <th>Atualizado em</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($plan['name']); ?></strong>
                                <?php if (!empty($plan['description_short'])): ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars($plan['description_short']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars($plan['code']); ?></span></td>
                            <td><?php echo ucfirst(htmlspecialchars($plan['billing_cycle'])); ?></td>
                            <td><?php echo (int)$plan['months']; ?></td>
                            <td>R$ <?php echo number_format((float)$plan['price_per_month'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float)$plan['total_amount'], 2, ',', '.'); ?></td>
                            <td>
                                <?php if ((int)$plan['is_active'] === 1): ?>
                                    <span class="badge text-bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge text-bg-danger">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$plan['is_featured'] === 1): ?>
                                    <span class="badge text-bg-primary">Destaque</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo !empty($plan['updated_at']) ? date('d/m/Y H:i', strtotime($plan['updated_at'])) : '-'; ?></small></td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="planos.php?edit=<?php echo (int)$plan['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="toggle_plan" value="1">
                                        <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">
                                        <input type="hidden" name="active" value="<?php echo (int)$plan['is_active'] === 1 ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?php if ((int)$plan['is_active'] === 1): ?>
                                                <i class="fas fa-pause"></i>
                                            <?php else: ?>
                                                <i class="fas fa-play"></i>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="feature_plan" value="1">
                                        <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-star"></i>
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

<?php include 'includes/footer.php'; ?>

