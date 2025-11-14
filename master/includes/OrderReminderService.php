<?php
/**
 * OrderReminderService
 * 
 * Serviço responsável por buscar pedidos pendentes elegíveis para lembretes
 * e disparar as notificações através do NotificationService.
 * 
 * Regras de elegibilidade:
 * - status = 'pending'
 * - customer_email IS NOT NULL e customer_email <> ''
 * - created_at <= NOW() - INTERVAL 1 HOUR (não lembrar pedido recém-criado)
 * - reminder_count < 3 (limite de 3 lembretes por pedido)
 * - last_reminder_sent_at IS NULL OR last_reminder_sent_at <= NOW() - INTERVAL 24 HOUR
 */

declare(strict_types=1);

require_once __DIR__ . '/NotificationService.php';

if (!class_exists('OrderReminderService')) {
    class OrderReminderService
    {
        private PDO $db;
        private NotificationService $notificationService;

        public function __construct(PDO $db, NotificationService $notificationService)
        {
            $this->db = $db;
            $this->notificationService = $notificationService;
        }

        /**
         * Busca pedidos pendentes elegíveis e envia lembretes.
         * 
         * @param int $limit Limite de pedidos a processar (padrão: 50)
         * @return int Número de pedidos processados
         */
        public function sendPendingOrderReminders(int $limit = 50): int
        {
            $processed = 0;

            try {
                // Busca pedidos elegíveis
                $orders = $this->fetchEligibleOrders($limit);

                if (empty($orders)) {
                    error_log("[reminder.pending_orders] No eligible orders found for reminders");
                    return 0;
                }

                error_log(sprintf(
                    "[reminder.pending_orders] Found %d eligible order(s) for reminders",
                    count($orders)
                ));

                // Processa cada pedido
                foreach ($orders as $order) {
                    $orderId = (int)$order['id'];
                    $currentReminderCount = (int)($order['reminder_count'] ?? 0);

                    try {
                        // Envia o lembrete
                        $sent = $this->notificationService->sendOrderReminderNotifications($orderId);

                        if ($sent) {
                            // Atualiza os campos de lembrete
                            $this->updateReminderFields($orderId, $currentReminderCount);
                            $processed++;

                            $newReminderCount = $currentReminderCount + 1;
                            error_log(sprintf(
                                "[reminder.pending_orders] Reminder sent for order %d, count=%d",
                                $orderId,
                                $newReminderCount
                            ));
                        } else {
                            error_log(sprintf(
                                "[reminder.pending_orders] Failed to send reminder for order %d (NotificationService returned false)",
                                $orderId
                            ));
                        }
                    } catch (Throwable $e) {
                        error_log(sprintf(
                            "[reminder.pending_orders] Exception while processing order %d: %s",
                            $orderId,
                            $e->getMessage()
                        ));
                        // Continua processando os próximos pedidos mesmo se um falhar
                    }
                }

                return $processed;

            } catch (Throwable $e) {
                error_log(sprintf(
                    "[reminder.pending_orders.error] Error in sendPendingOrderReminders: %s",
                    $e->getMessage()
                ));
                return $processed;
            }
        }

        /**
         * Busca pedidos elegíveis para lembretes.
         * 
         * @param int $limit
         * @return array<array<string,mixed>>
         */
        private function fetchEligibleOrders(int $limit): array
        {
            $sql = "
                SELECT 
                    id,
                    customer_email,
                    reminder_count,
                    first_reminder_sent_at,
                    last_reminder_sent_at
                FROM orders
                WHERE status = 'pending'
                  AND customer_email IS NOT NULL
                  AND customer_email <> ''
                  AND created_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                  AND reminder_count < 3
                  AND (
                      last_reminder_sent_at IS NULL
                      OR last_reminder_sent_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                  )
                ORDER BY created_at ASC
                LIMIT ?
            ";

            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$limit]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $results ?: [];
            } catch (PDOException $e) {
                error_log(sprintf(
                    "[reminder.pending_orders.error] Error fetching eligible orders: %s",
                    $e->getMessage()
                ));
                return [];
            }
        }

        /**
         * Atualiza os campos de lembrete após envio bem-sucedido.
         * 
         * @param int $orderId
         * @param int $currentReminderCount
         * @return void
         */
        private function updateReminderFields(int $orderId, int $currentReminderCount): void
        {
            $now = date('Y-m-d H:i:s');
            $newReminderCount = $currentReminderCount + 1;

            // Se é o primeiro lembrete, atualiza first_reminder_sent_at
            if ($currentReminderCount === 0) {
                $sql = "
                    UPDATE orders
                    SET first_reminder_sent_at = ?,
                        last_reminder_sent_at = ?,
                        reminder_count = ?,
                        updated_at = ?
                    WHERE id = ?
                ";

                try {
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$now, $now, $newReminderCount, $now, $orderId]);
                } catch (PDOException $e) {
                    error_log(sprintf(
                        "[reminder.pending_orders.error] Error updating reminder fields for order %d: %s",
                        $orderId,
                        $e->getMessage()
                    ));
                }
            } else {
                // Apenas atualiza last_reminder_sent_at e reminder_count
                $sql = "
                    UPDATE orders
                    SET last_reminder_sent_at = ?,
                        reminder_count = ?,
                        updated_at = ?
                    WHERE id = ?
                ";

                try {
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$now, $newReminderCount, $now, $orderId]);
                } catch (PDOException $e) {
                    error_log(sprintf(
                        "[reminder.pending_orders.error] Error updating reminder fields for order %d: %s",
                        $orderId,
                        $e->getMessage()
                    ));
                }
            }
        }
    }
}
