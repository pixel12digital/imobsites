<?php
/**
 * NotificationService
 * 
 * Serviço centralizado para envio de notificações relacionadas a pedidos:
 * - order_created: Pedido criado
 * - order_paid: Pagamento confirmado
 * - order_reminder: Lembrete de cobrança pendente (futuro)
 * 
 * Suporta envio por e-mail e preparado para WhatsApp no futuro.
 */

declare(strict_types=1);

require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/PlanService.php';

if (!class_exists('NotificationService')) {
    class NotificationService
    {
        private PDO $db;
        private MailService $mailService;
        // Futuramente: private WhatsAppService $whatsappService;

        public function __construct(PDO $db)
        {
            $this->db = $db;
            $this->mailService = new MailService($db);
        }

        /**
         * Envia notificações quando um pedido é criado.
         * 
         * @param int $orderId ID do pedido
         * @return void
         */
        public function sendOrderCreatedNotifications(int $orderId): void
        {
            try {
                // Busca dados do pedido com JOIN na tabela plans
                $order = $this->fetchOrderWithPlan($orderId);

                if (!$order) {
                    error_log("[notification.order_created] Pedido {$orderId} não encontrado para envio de notificação");
                    return;
                }

                // Validação de dados mínimos
                $customerEmail = trim($order['customer_email'] ?? '');
                $customerName = trim($order['customer_name'] ?? 'Cliente');

                if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                    error_log("[notification.order_created] E-mail inválido para pedido {$orderId}: " . substr($customerEmail, 0, 20));
                    return;
                }

                // Monta dados para o template
                $planName = $order['plan_name'] ?? $order['plan_code'] ?? 'Plano';
                $billingCycle = $order['billing_cycle'] ?? '';
                $totalAmount = (float)($order['total_amount'] ?? 0.0);
                $paymentMethod = $order['payment_method'] ?? '';
                $paymentUrl = $order['payment_url'] ?? '';

                // Formata o ciclo de cobrança
                $cycleLabel = $this->formatBillingCycle($billingCycle);

                // Formata o valor
                $formattedAmount = 'R$ ' . number_format($totalAmount, 2, ',', '.');

                // Formata o método de pagamento
                $paymentMethodLabel = $this->formatPaymentMethod($paymentMethod);

                // Monta o assunto
                $subject = '[ImobSites] Seu pedido foi criado – finalize o pagamento';

                // Monta o conteúdo do e-mail
                $content = $this->buildOrderCreatedEmailContent(
                    $customerName,
                    $planName,
                    $cycleLabel,
                    $formattedAmount,
                    $paymentMethodLabel,
                    $paymentUrl,
                    $orderId
                );

                // Monta o template HTML completo
                $htmlBody = $this->mailService->buildEmailTemplate(
                    'Pedido Criado - ImobSites',
                    $content
                );

                // Envia o e-mail
                $sent = $this->mailService->send(
                    $customerEmail,
                    $customerName,
                    $subject,
                    $htmlBody
                );

                if ($sent) {
                    error_log("[notification.order_created.email] E-mail enviado com sucesso para pedido {$orderId} | Destinatário: " . substr($customerEmail, 0, 30));
                } else {
                    error_log("[notification.order_created.email] Falha ao enviar e-mail para pedido {$orderId} | Destinatário: " . substr($customerEmail, 0, 30));
                }

                // Futuramente: enviar WhatsApp aqui
                // $this->whatsappService->sendOrderCreated($orderId);

            } catch (Throwable $e) {
                error_log("[notification.order_created.error] Erro ao enviar notificações para pedido {$orderId}: " . $e->getMessage());
                // Não propaga a exceção para não quebrar o fluxo de criação do pedido
            }
        }

        /**
         * Envia notificações quando um pedido é pago.
         * 
         * @param int $orderId ID do pedido
         * @return void
         */
        public function sendOrderPaidNotifications(int $orderId): void
        {
            // TODO: implementar envio de e-mail/WhatsApp para pedido pago
            error_log("[notification.order_paid] TODO: implementar envio de e-mail/WhatsApp para pedido {$orderId} pago.");
        }

        /**
         * Envia lembretes de cobrança pendente.
         * 
         * @param int $orderId ID do pedido
         * @return void
         */
        public function sendOrderReminderNotifications(int $orderId): void
        {
            // TODO: implementar envio de lembrete de cobrança para pedido pendente
            // Esta função será usada por um scheduler/cron job para enviar lembretes
            // para pedidos pendentes há X horas/dias
            error_log("[notification.order_reminder] TODO: implementar envio de lembrete para pedido {$orderId}.");
        }

        /**
         * Busca dados do pedido com JOIN na tabela plans.
         * 
         * @param int $orderId
         * @return array<string,mixed>|null
         */
        private function fetchOrderWithPlan(int $orderId): ?array
        {
            $sql = "
                SELECT 
                    o.*,
                    p.name AS plan_name,
                    p.description AS plan_description
                FROM orders o
                LEFT JOIN plans p ON p.code = o.plan_code
                WHERE o.id = ?
                LIMIT 1
            ";

            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$orderId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ?: null;
            } catch (PDOException $e) {
                error_log("[notification.order_created.error] Erro ao buscar pedido {$orderId}: " . $e->getMessage());
                return null;
            }
        }

        /**
         * Formata o ciclo de cobrança para exibição.
         * 
         * @param string $billingCycle
         * @return string
         */
        private function formatBillingCycle(string $billingCycle): string
        {
            $cycles = [
                'mensal' => 'Mensal',
                'trimestral' => 'Trimestral',
                'semestral' => 'Semestral',
                'anual' => 'Anual',
            ];

            $cycle = strtolower(trim($billingCycle));
            return $cycles[$cycle] ?? ucfirst($billingCycle);
        }

        /**
         * Formata o método de pagamento para exibição.
         * 
         * @param string $paymentMethod
         * @return string
         */
        private function formatPaymentMethod(string $paymentMethod): string
        {
            $methods = [
                'pix' => 'PIX',
                'credit_card' => 'Cartão de Crédito',
                'boleto' => 'Boleto Bancário',
            ];

            $method = strtolower(trim($paymentMethod));
            return $methods[$method] ?? ucfirst($paymentMethod);
        }

        /**
         * Monta o conteúdo HTML do e-mail de pedido criado.
         * 
         * @param string $customerName
         * @param string $planName
         * @param string $cycleLabel
         * @param string $formattedAmount
         * @param string $paymentMethodLabel
         * @param string $paymentUrl
         * @param int $orderId
         * @return string HTML
         */
        private function buildOrderCreatedEmailContent(
            string $customerName,
            string $planName,
            string $cycleLabel,
            string $formattedAmount,
            string $paymentMethodLabel,
            string $paymentUrl,
            int $orderId
        ): string {
            $content = "<p>Olá, <strong>" . htmlspecialchars($customerName, ENT_QUOTES) . "</strong>!</p>";
            $content .= "<p>Seu pedido foi criado com sucesso. Seguem as informações:</p>";
            
            $content .= "<table style=\"width: 100%; margin: 20px 0; border-collapse: collapse;\">";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Pedido:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\">#{$orderId}</td></tr>";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Plano:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\">" . htmlspecialchars($planName, ENT_QUOTES) . "</td></tr>";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Ciclo:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\">" . htmlspecialchars($cycleLabel, ENT_QUOTES) . "</td></tr>";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Valor Total:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>" . htmlspecialchars($formattedAmount, ENT_QUOTES) . "</strong></td></tr>";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Forma de Pagamento:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\">" . htmlspecialchars($paymentMethodLabel, ENT_QUOTES) . "</td></tr>";
            $content .= "</table>";

            if ($paymentUrl !== '') {
                $content .= "<p style=\"margin: 30px 0;\">";
                $content .= "<a href=\"" . htmlspecialchars($paymentUrl, ENT_QUOTES) . "\" style=\"display: inline-block; padding: 15px 30px; background-color: #F7931E; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;\">";
                $content .= "Finalizar Pagamento";
                $content .= "</a>";
                $content .= "</p>";
                
                $content .= "<p style=\"color: #666; font-size: 14px;\">";
                $content .= "Ou copie e cole este link no seu navegador:<br>";
                $content .= "<code style=\"background-color: #f5f5f5; padding: 5px 10px; border-radius: 3px; word-break: break-all;\">" . htmlspecialchars($paymentUrl, ENT_QUOTES) . "</code>";
                $content .= "</p>";
            }

            $content .= "<p style=\"margin-top: 30px; padding: 15px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;\">";
            $content .= "<strong>Observação:</strong> Se você já concluiu o pagamento, desconsidere este e-mail.";
            $content .= "</p>";

            return $content;
        }
    }
}

