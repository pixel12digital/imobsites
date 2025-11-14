<?php
/**
 * Script CLI para enviar lembretes de pedidos pendentes.
 * 
 * Este script busca pedidos pendentes elegíveis e envia e-mails de lembrete
 * através do OrderReminderService.
 * 
 * INSTRUÇÕES DE USO:
 * 
 * 1. Via CLI (linha de comando):
 *    php scripts/tasks/send_pending_order_reminders.php
 * 
 * 2. Via cron (exemplo):
 *    *\/30 * * * * /usr/bin/php /home/USUARIO/public_html/painel.imobsites.com.br/scripts/tasks/send_pending_order_reminders.php >> /home/USUARIO/logs/imobsites_reminders.log 2>&1
 * 
 * IMPORTANTE:
 * - Este script deve ser executado periodicamente via cron
 * - Ajuste o caminho do PHP e o caminho do script conforme seu ambiente
 * - Os logs são enviados para error_log() e também para stdout quando executado via CLI
 */

declare(strict_types=1);

// Bootstrap: carregar configurações e dependências
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../master/includes/NotificationService.php';
require_once __DIR__ . '/../../master/includes/OrderReminderService.php';

// Verificar se está rodando via CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Este script só pode ser executado via CLI.');
}

try {
    // Verificar se a conexão com o banco está disponível
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Conexão com o banco de dados não disponível. Verifique config/database.php');
    }

    // Instanciar serviços
    $notificationService = new NotificationService($pdo);
    $orderReminderService = new OrderReminderService($pdo, $notificationService);

    // Processar lembretes (limite padrão: 100 pedidos por execução)
    $processed = $orderReminderService->sendPendingOrderReminders(100);

    // Exibir resultado
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Pending order reminders processed: {$processed}\n";

    // Exit code 0 = sucesso
    exit(0);

} catch (Throwable $e) {
    $timestamp = date('Y-m-d H:i:s');
    $errorMessage = sprintf(
        "[%s] ERROR: %s\nStack trace:\n%s\n",
        $timestamp,
        $e->getMessage(),
        $e->getTraceAsString()
    );
    
    error_log($errorMessage);
    echo $errorMessage;
    
    // Exit code 1 = erro
    exit(1);
}
