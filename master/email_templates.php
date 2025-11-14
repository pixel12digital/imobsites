<?php
session_start();

require_once '../config/paths.php';
require_once '../config/database.php';
require_once '../config/master.php';
require_once 'utils.php';
require_once 'includes/EmailTemplateService.php';

$error = '';
$success = '';
$editTemplate = null;
$filterEventType = $_GET['event_type'] ?? null;

if (!isset($_SESSION['master_logged_in']) || $_SESSION['master_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$templateService = new EmailTemplateService($pdo);
$availableEvents = $templateService->getAvailableEvents();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_template'])) {
            $templateId = isset($_POST['template_id']) && $_POST['template_id'] !== '' ? (int)$_POST['template_id'] : null;

            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $eventType = $_POST['event_type'] ?? '';
            $description = trim($_POST['description'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $htmlBody = $_POST['html_body'] ?? '';
            $textBody = $_POST['text_body'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            // Validações
            if (empty($name)) {
                throw new Exception('Nome é obrigatório.');
            }

            if (empty($slug)) {
                // Gerar slug a partir do nome se não informado
                $slug = generateSlug($name);
                if (empty($slug)) {
                    throw new Exception('Slug é obrigatório ou informe um nome válido.');
                }
            }

            // Validar slug único (exceto para o próprio template em edição)
            $existing = $templateService->findTemplateBySlug($slug);
            if ($existing && (int)$existing['id'] !== $templateId) {
                throw new Exception('Slug já existe. Escolha outro identificador.');
            }

            if (empty($eventType) || !isset($availableEvents[$eventType])) {
                throw new Exception('Tipo de evento inválido.');
            }

            if (empty($subject)) {
                throw new Exception('Assunto é obrigatório.');
            }

            if (empty($htmlBody)) {
                throw new Exception('Corpo HTML é obrigatório.');
            }

            $payload = [
                'name' => $name,
                'slug' => $slug,
                'event_type' => $eventType,
                'description' => $description ?: null,
                'subject' => $subject,
                'html_body' => $htmlBody,
                'text_body' => $textBody ?: null,
                'is_active' => $isActive,
            ];

            $savedId = $templateService->saveTemplate($payload, $templateId);

            if ($savedId) {
                header('Location: email_templates.php?success=1&event_type=' . urlencode($eventType));
                exit;
            } else {
                throw new Exception('Erro ao salvar template.');
            }
        }

        if (isset($_POST['toggle_template'])) {
            $templateId = (int)($_POST['template_id'] ?? 0);
            $currentTemplate = $templateService->findTemplateBySlug('');
            if ($currentTemplate) {
                // Buscar template
                $sql = "SELECT * FROM email_templates WHERE id = ? LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$templateId]);
                $template = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($template) {
                    $newStatus = (int)$template['is_active'] === 1 ? 0 : 1;
                    $updateSql = "UPDATE email_templates SET is_active = ?, updated_at = NOW() WHERE id = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([$newStatus, $templateId]);
                }
            }
            header('Location: email_templates.php?success=1');
            exit;
        }

        if (isset($_POST['delete_template'])) {
            $templateId = (int)($_POST['template_id'] ?? 0);
            if ($templateService->deleteTemplate($templateId)) {
                header('Location: email_templates.php?success=1');
                exit;
            } else {
                throw new Exception('Erro ao excluir template.');
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $templateId = (int)$_GET['edit'];
    $sql = "SELECT * FROM email_templates WHERE id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$templateId]);
    $editTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editTemplate) {
        $error = 'Template não encontrado para edição.';
    }
}

if (isset($_GET['success'])) {
    $success = 'Template salvo com sucesso.';
}

// Buscar templates
$templates = $templateService->getAllTemplates($filterEventType ?: null);

include 'includes/header.php';

// Variáveis disponíveis para o evento selecionado (usado no formulário)
$availableVariables = [];
if ($editTemplate) {
    $availableVariables = $templateService->getAvailableVariablesForEvent($editTemplate['event_type']);
} elseif (isset($_POST['event_type'])) {
    $availableVariables = $templateService->getAvailableVariablesForEvent($_POST['event_type']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="page-title mb-1"><i class="fas fa-envelope-open-text me-2 text-primary"></i>Templates de e-mail</h1>
        <p class="page-subtitle mb-0">Gerencie os templates de e-mail transacionais do sistema.</p>
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
            <?php echo $editTemplate ? 'Editar template' : 'Cadastrar novo template'; ?>
        </h5>
        <?php if ($editTemplate): ?>
            <a class="btn btn-sm btn-outline-secondary" href="email_templates.php">
                <i class="fas fa-plus me-1"></i>Novo template
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="save_template" value="1">
            <?php if ($editTemplate): ?>
                <input type="hidden" name="template_id" value="<?php echo (int)$editTemplate['id']; ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" maxlength="191" required 
                           value="<?php echo htmlspecialchars($editTemplate['name'] ?? ''); ?>">
                    <div class="form-text text-muted">Nome amigável para identificação no painel.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="slug" maxlength="191" required 
                           value="<?php echo htmlspecialchars($editTemplate['slug'] ?? ''); ?>"
                           <?php echo $editTemplate ? 'readonly' : ''; ?>>
                    <div class="form-text text-muted">Identificador único (ex.: order_created_default). <?php echo $editTemplate ? 'Não pode ser alterado.' : 'Gerado automaticamente se vazio.'; ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tipo de evento <span class="text-danger">*</span></label>
                    <select class="form-select" name="event_type" id="event_type" required 
                            <?php echo $editTemplate ? 'disabled' : ''; ?>>
                        <option value="">Selecione...</option>
                        <?php foreach ($availableEvents as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                    <?php echo ($editTemplate['event_type'] ?? '') === $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editTemplate): ?>
                        <input type="hidden" name="event_type" value="<?php echo htmlspecialchars($editTemplate['event_type']); ?>">
                    <?php endif; ?>
                    <div class="form-text text-muted">Tipo de evento para o qual este template será usado.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descrição</label>
                    <input type="text" class="form-control" name="description" maxlength="255"
                           value="<?php echo htmlspecialchars($editTemplate['description'] ?? ''); ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Assunto <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="subject" maxlength="255" required
                           value="<?php echo htmlspecialchars($editTemplate['subject'] ?? ''); ?>">
                    <div class="form-text text-muted">Use variáveis como {{customer_name}}, {{order_id}}, etc.</div>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Corpo HTML <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="html_body" rows="12" required 
                              style="font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($editTemplate['html_body'] ?? ''); ?></textarea>
                    <div class="form-text text-muted">Use variáveis como {{customer_name}}, {{order_id}}, etc. Use HTML para formatação.</div>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Corpo texto (opcional)</label>
                    <textarea class="form-control" name="text_body" rows="8"
                              style="font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($editTemplate['text_body'] ?? ''); ?></textarea>
                    <div class="form-text text-muted">Versão em texto puro. Se vazio, será gerado automaticamente do HTML.</div>
                </div>
                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                               <?php echo !isset($editTemplate['is_active']) || (int)$editTemplate['is_active'] === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            Template ativo
                        </label>
                        <div class="form-text text-muted">Apenas templates ativos serão usados pelo sistema.</div>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-end gap-2">
                <a href="email_templates.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Salvar template
                </button>
            </div>
        </form>
    </div>
    <?php if (!empty($availableVariables)): ?>
        <div class="card-footer bg-light">
            <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Variáveis disponíveis para este evento:</h6>
            <div class="row g-2">
                <?php foreach ($availableVariables as $var => $description): ?>
                    <div class="col-md-6">
                        <code class="text-primary">{{<?php echo htmlspecialchars($var); ?>}}</code>
                        <small class="text-muted ms-2"><?php echo htmlspecialchars($description); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="alert alert-info mt-3 mb-0">
                <small><i class="fas fa-lightbulb me-1"></i><strong>Dica:</strong> Use as variáveis acima entre duplas chaves no assunto e corpo do e-mail. Exemplo: Olá, {{customer_name}}!</small>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-table-list me-2 text-primary"></i>Templates cadastrados</h5>
        <div>
            <form method="GET" class="d-inline">
                <select name="event_type" class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                    <option value="">Todos os eventos</option>
                    <?php foreach ($availableEvents as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" 
                                <?php echo $filterEventType === $value ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($templates)): ?>
            <p class="text-muted mb-0">Nenhum template cadastrado ainda.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Evento</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Atualizado em</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($template['name']); ?></strong>
                                <?php if (!empty($template['description'])): ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars($template['description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge text-bg-secondary">
                                    <?php echo htmlspecialchars($availableEvents[$template['event_type']] ?? $template['event_type']); ?>
                                </span>
                            </td>
                            <td><code><?php echo htmlspecialchars($template['slug']); ?></code></td>
                            <td>
                                <?php if ((int)$template['is_active'] === 1): ?>
                                    <span class="badge text-bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge text-bg-danger">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo !empty($template['updated_at']) ? date('d/m/Y H:i', strtotime($template['updated_at'])) : '-'; ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="email_templates.php?edit=<?php echo (int)$template['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este template?');">
                                        <input type="hidden" name="delete_template" value="1">
                                        <input type="hidden" name="template_id" value="<?php echo (int)$template['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                            <i class="fas fa-trash"></i>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const eventTypeSelect = document.getElementById('event_type');
    if (eventTypeSelect && !eventTypeSelect.disabled) {
        // Quando mudar o evento, recarregar variáveis disponíveis
        eventTypeSelect.addEventListener('change', function() {
            // Recarregar a página com o evento selecionado para mostrar variáveis
            const form = eventTypeSelect.closest('form');
            if (form) {
                // Criar um input hidden temporário para manter o valor
                const tempInput = document.createElement('input');
                tempInput.type = 'hidden';
                tempInput.name = 'event_type';
                tempInput.value = eventTypeSelect.value;
                form.appendChild(tempInput);
                
                // Não submeter o form, apenas manter o valor para o PHP processar
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>

