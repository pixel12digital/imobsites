<?php
session_start();

require_once '../config/paths.php';
require_once '../config/database.php';
require_once '../config/master.php';
require_once 'utils.php';
require_once 'includes/OrderService.php';
require_once 'includes/PlanService.php';

if (!isset($_SESSION['master_logged_in']) || $_SESSION['master_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Variáveis para mensagens de feedback
$flashMessage = '';
$flashType = '';

// Processa ação de cancelamento de pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $cancelReason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : null;
    
    if ($orderId > 0) {
        // Verificar o status atual do pedido antes de tentar cancelar
        $currentOrder = fetch('SELECT id, status FROM orders WHERE id = ?', [$orderId]);
        
        if (!$currentOrder) {
            $flashMessage = 'Pedido não encontrado.';
            $flashType = 'danger';
        } elseif ($currentOrder['status'] === 'paid') {
            $flashMessage = 'Não é possível cancelar um pedido que já foi pago.';
            $flashType = 'danger';
        } elseif (in_array($currentOrder['status'], ['canceled', 'expired'], true)) {
            $flashMessage = 'Este pedido já está cancelado ou expirado.';
            $flashType = 'warning';
        } else {
            // Chama a função de cancelamento
            $success = cancelOrder($orderId, null, $cancelReason);
            
            if ($success) {
                $flashMessage = 'Pedido marcado como cancelado com sucesso. Esta ação não cancela a cobrança no Asaas.';
                $flashType = 'success';
            } else {
                $flashMessage = 'Não foi possível cancelar este pedido. Verifique o status ou tente novamente.';
                $flashType = 'danger';
            }
        }
    } else {
        $flashMessage = 'ID do pedido inválido.';
        $flashType = 'danger';
    }
    
    // Preserva filtros e página atual no redirect
    $redirectParams = [];
    if (!empty($_GET['status'])) {
        $redirectParams['status'] = $_GET['status'];
    }
    if (!empty($_GET['payment_method'])) {
        $redirectParams['payment_method'] = $_GET['payment_method'];
    }
    if (!empty($_GET['plan_code'])) {
        $redirectParams['plan_code'] = $_GET['plan_code'];
    }
    if (!empty($_GET['q'])) {
        $redirectParams['q'] = $_GET['q'];
    }
    if (!empty($_GET['page'])) {
        $redirectParams['page'] = $_GET['page'];
    }
    
    // Armazena mensagem na sessão para exibir após redirect
    $_SESSION['flash_message'] = $flashMessage;
    $_SESSION['flash_type'] = $flashType;
    
    $redirectUrl = 'pedidos.php';
    if (!empty($redirectParams)) {
        $redirectUrl .= '?' . http_build_query($redirectParams);
    }
    
    header('Location: ' . $redirectUrl);
    exit;
}

// Recupera mensagem flash da sessão (se houver)
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    $flashType = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Processa filtros do GET
$filters = [];
if (!empty($_GET['status']) && in_array($_GET['status'], ['pending', 'paid', 'canceled', 'expired'], true)) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['payment_method']) && in_array($_GET['payment_method'], ['credit_card', 'pix', 'boleto'], true)) {
    $filters['payment_method'] = $_GET['payment_method'];
}
if (!empty($_GET['plan_code'])) {
    $filters['plan_code'] = strtoupper(trim($_GET['plan_code']));
}
if (!empty($_GET['q'])) {
    $filters['q'] = trim($_GET['q']);
}

// Paginação
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;

// Busca os pedidos
$result = listOrders($filters, $page, $perPage);
$orders = $result['items'];
$pagination = $result['pagination'];

// Busca planos para o select de filtro
$plans = getAllPlans(true); // Apenas planos ativos

// Helper para formatar método de pagamento
function formatPaymentMethod(?string $method): string
{
    if (!$method) {
        return '—';
    }
    $labels = [
        'credit_card' => 'Cartão de Crédito',
        'pix' => 'PIX',
        'boleto' => 'Boleto',
    ];
    return $labels[$method] ?? ucfirst($method);
}

// Helper para formatar status com badge e tooltip
function formatOrderStatus(?string $status): string
{
    if (!$status) {
        return '<span class="badge text-bg-secondary" title="Status não definido">—</span>';
    }
    $badges = [
        'pending' => '<span class="badge" style="background-color: #ff9800; color: #fff;" title="Aguardando confirmação do pagamento no Asaas.">Pendente</span>',
        'paid' => '<span class="badge text-bg-success" title="Pagamento confirmado pelo Asaas. Tenant deve estar criado.">Pago</span>',
        'canceled' => '<span class="badge text-bg-danger" title="Pedido cancelado internamente. Esta ação não cancela a cobrança no Asaas.">Cancelado</span>',
        'expired' => '<span class="badge text-bg-secondary" title="Pagamento expirado no Asaas.">Expirado</span>',
    ];
    return $badges[$status] ?? '<span class="badge text-bg-secondary" title="Status: ' . htmlspecialchars($status) . '">' . htmlspecialchars($status) . '</span>';
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="page-title mb-1"><i class="fas fa-shopping-cart me-2 text-primary"></i>Pedidos / Assinaturas</h1>
        <p class="page-subtitle mb-0">Acompanhe os pedidos gerados pelo checkout e o status dos pagamentos.</p>
    </div>
</div>

<?php if (!empty($flashMessage)): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $flashType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <?php echo htmlspecialchars($flashMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-filter me-2 text-primary"></i>Filtros</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="pedidos.php" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="pending" <?php echo isset($filters['status']) && $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="paid" <?php echo isset($filters['status']) && $filters['status'] === 'paid' ? 'selected' : ''; ?>>Pago</option>
                    <option value="canceled" <?php echo isset($filters['status']) && $filters['status'] === 'canceled' ? 'selected' : ''; ?>>Cancelado</option>
                    <option value="expired" <?php echo isset($filters['status']) && $filters['status'] === 'expired' ? 'selected' : ''; ?>>Expirado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Forma de pagamento</label>
                <select name="payment_method" class="form-select">
                    <option value="">Todas</option>
                    <option value="credit_card" <?php echo isset($filters['payment_method']) && $filters['payment_method'] === 'credit_card' ? 'selected' : ''; ?>>Cartão de Crédito</option>
                    <option value="pix" <?php echo isset($filters['payment_method']) && $filters['payment_method'] === 'pix' ? 'selected' : ''; ?>>PIX</option>
                    <option value="boleto" <?php echo isset($filters['payment_method']) && $filters['payment_method'] === 'boleto' ? 'selected' : ''; ?>>Boleto</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Plano</label>
                <select name="plan_code" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?php echo htmlspecialchars($plan['code']); ?>" <?php echo isset($filters['plan_code']) && $filters['plan_code'] === $plan['code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($plan['name']); ?> (<?php echo htmlspecialchars($plan['code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" placeholder="ID, nome ou e-mail" value="<?php echo isset($filters['q']) ? htmlspecialchars($filters['q']) : ''; ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Filtrar
                </button>
                <?php if (!empty($filters)): ?>
                    <a href="pedidos.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Limpar filtros
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de pedidos -->
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-table-list me-2 text-primary"></i>Pedidos</h5>
    </div>
    <div class="card-body">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-state__icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3 class="empty-state__title">Nenhum pedido encontrado</h3>
                <p class="empty-state__description">
                    <?php if (!empty($filters)): ?>
                        Nenhum pedido corresponde aos filtros aplicados. Tente ajustar os filtros.
                    <?php else: ?>
                        Ainda não há pedidos cadastrados no sistema.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Plano</th>
                        <th>Forma de pagamento</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Pago em</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <strong>#<?php echo htmlspecialchars((string)$order['id']); ?></strong>
                            </td>
                            <td>
                                <?php
                                // Priorizar dados do tenant quando disponível
                                $clientName = !empty($order['tenant_name']) ? $order['tenant_name'] : ($order['customer_name'] ?? '—');
                                $clientEmail = !empty($order['tenant_email']) ? $order['tenant_email'] : ($order['customer_email'] ?? '');
                                $hasTenant = !empty($order['tenant_id']);
                                ?>
                                <div>
                                    <?php if ($hasTenant && !empty($order['tenant_name'])): ?>
                                        <a href="tenant.php?id=<?php echo (int)$order['tenant_id']; ?>" class="text-decoration-none">
                                            <strong><?php echo htmlspecialchars($clientName); ?></strong>
                                        </a>
                                    <?php else: ?>
                                        <strong><?php echo htmlspecialchars($clientName); ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($clientEmail)): ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars($clientEmail); ?></div>
                                    <?php endif; ?>
                                    <div class="mt-1">
                                        <?php if ($hasTenant): ?>
                                            <span class="badge text-bg-success" style="font-size: 0.75rem;">
                                                <i class="fas fa-check me-1"></i>Conta criada (tenant #<?php echo (int)$order['tenant_id']; ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge text-bg-warning" style="font-size: 0.75rem; background-color: #ff9800 !important;">
                                                <i class="fas fa-minus me-1"></i>Conta ainda não criada
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($order['plan_name'])): ?>
                                    <strong><?php echo htmlspecialchars($order['plan_name']); ?></strong>
                                    <div class="text-muted small"><?php echo htmlspecialchars($order['plan_code'] ?? ''); ?></div>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary"><?php echo htmlspecialchars($order['plan_code'] ?? '—'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatPaymentMethod($order['payment_method'] ?? null); ?></td>
                            <td>
                                <strong>R$ <?php echo number_format((float)($order['total_amount'] ?? 0), 2, ',', '.'); ?></strong>
                            </td>
                            <td><?php echo formatOrderStatus($order['status'] ?? null); ?></td>
                            <td>
                                <small class="text-muted">
                                    <?php echo !empty($order['created_at']) ? formatDateTime($order['created_at']) : '—'; ?>
                                </small>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo !empty($order['paid_at']) ? formatDateTime($order['paid_at']) : '—'; ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <?php 
                                    $orderStatus = $order['status'] ?? null;
                                    $isPending = $orderStatus === 'pending';
                                    ?>
                                    <?php if ($isPending): ?>
                                        <form method="POST" onsubmit="return confirm('Tem certeza que deseja marcar este pedido como cancelado?\\n\\nEsta ação NÃO cancela a cobrança no Asaas. É apenas um controle interno.');" style="display:inline;">
                                            <input type="hidden" name="action" value="cancel_order">
                                            <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                            <input type="hidden" name="cancel_reason" value="Cancelado manualmente pelo painel">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Marcar pedido como cancelado (não cancela no Asaas)">
                                                <i class="fas fa-times me-1"></i>Cancelar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!empty($order['tenant_id'])): ?>
                                        <a href="tenant.php?id=<?php echo (int)$order['tenant_id']; ?>" class="btn btn-sm btn-outline-primary" title="Configurar cliente">
                                            <i class="fas fa-gear me-1"></i>Configurar
                                        </a>
                                        <a href="../admin/login.php?tenant=<?php echo urlencode($order['tenant_slug'] ?? ''); ?>" class="btn btn-sm btn-outline-secondary" title="Acessar como cliente" target="_blank">
                                            <i class="fas fa-user-check me-1"></i>Acessar
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($order['payment_url'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-info btn-copy-payment-url" 
                                                data-payment-url="<?php echo htmlspecialchars($order['payment_url']); ?>" 
                                                title="Copiar link de pagamento">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <a href="<?php echo htmlspecialchars($order['payment_url']); ?>" class="btn btn-sm btn-outline-info" title="Ver no Asaas" target="_blank">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <nav aria-label="Paginação de pedidos" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php
                        $currentPage = $pagination['page'];
                        $totalPages = $pagination['total_pages'];
                        
                        // Monta query string preservando filtros
                        $queryParams = $filters;
                        if (isset($queryParams['q'])) {
                            $queryParams['q'] = urlencode($queryParams['q']);
                        }
                        
                        // Botão Anterior
                        if ($currentPage > 1):
                            $prevParams = $queryParams;
                            $prevParams['page'] = $currentPage - 1;
                            $prevUrl = 'pedidos.php?' . http_build_query($prevParams);
                        ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo htmlspecialchars($prevUrl); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link"><i class="fas fa-chevron-left"></i> Anterior</span>
                            </li>
                        <?php endif; ?>

                        <!-- Informação da página -->
                        <li class="page-item disabled">
                            <span class="page-link">
                                Página <?php echo $currentPage; ?> de <?php echo $totalPages; ?>
                                <small class="d-block text-muted">(<?php echo $pagination['total_items']; ?> pedidos)</small>
                            </span>
                        </li>

                        <!-- Botão Próximo -->
                        <?php if ($currentPage < $totalPages):
                            $nextParams = $queryParams;
                            $nextParams['page'] = $currentPage + 1;
                            $nextUrl = 'pedidos.php?' . http_build_query($nextParams);
                        ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo htmlspecialchars($nextUrl); ?>">
                                    Próximo <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">Próximo <i class="fas fa-chevron-right"></i></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Função para copiar texto para a área de transferência
    function copyToClipboard(text) {
        // Tenta usar a API moderna do clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).then(function() {
                return true;
            }).catch(function() {
                return false;
            });
        }
        
        // Fallback para navegadores mais antigos
        try {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            var successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            return Promise.resolve(successful);
        } catch (err) {
            return Promise.resolve(false);
        }
    }
    
    // Função para mostrar feedback visual
    function showCopyFeedback(button, success) {
        var originalHTML = button.innerHTML;
        var originalTitle = button.getAttribute('title');
        
        if (success) {
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.setAttribute('title', 'Link copiado!');
            button.classList.remove('btn-outline-info');
            button.classList.add('btn-success');
            
            setTimeout(function() {
                button.innerHTML = originalHTML;
                button.setAttribute('title', originalTitle);
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-info');
            }, 2000);
        } else {
            button.innerHTML = '<i class="fas fa-times"></i>';
            button.setAttribute('title', 'Erro ao copiar');
            button.classList.remove('btn-outline-info');
            button.classList.add('btn-danger');
            
            setTimeout(function() {
                button.innerHTML = originalHTML;
                button.setAttribute('title', originalTitle);
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline-info');
            }, 2000);
        }
    }
    
    // Adiciona event listeners aos botões de copiar
    var copyButtons = document.querySelectorAll('.btn-copy-payment-url');
    copyButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var paymentUrl = this.getAttribute('data-payment-url');
            if (!paymentUrl) {
                showCopyFeedback(this, false);
                return;
            }
            
            copyToClipboard(paymentUrl).then(function(success) {
                showCopyFeedback(button, success);
            });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>

