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
require_once __DIR__ . '/EmailTemplateService.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/PlanService.php';

if (!class_exists('NotificationService')) {
    class NotificationService
    {
        private PDO $db;
        private MailService $mailService;
        private EmailTemplateService $templateService;
        // Futuramente: private WhatsAppService $whatsappService;

        public function __construct(PDO $db)
        {
            $this->db = $db;
            $this->mailService = new MailService($db);
            $this->templateService = new EmailTemplateService($db);
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
                
                // Formata data de criação
                $createdAt = date('d/m/Y à\s H:i', strtotime($order['created_at'] ?? date('Y-m-d H:i:s')));

                // Tentar carregar template ativo
                $template = $this->templateService->findActiveTemplateByEvent('order_created');
                
                if ($template) {
                    // Usar template do banco
                    $variables = [
                        'customer_name' => $customerName,
                        'customer_email' => $customerEmail,
                        'order_id' => (string)$orderId,
                        'plan_name' => $planName,
                        'plan_cycle' => $cycleLabel,
                        'plan_total' => $formattedAmount,
                        'payment_method' => $paymentMethodLabel,
                        'payment_url' => $paymentUrl,
                        'created_at' => $createdAt,
                    ];
                    
                    $rendered = $this->templateService->renderTemplate($template, $variables);
                    $subject = $rendered['subject'];
                    $htmlBody = $this->mailService->buildEmailTemplate(
                        'Pedido Criado - ImobSites',
                        $rendered['html_body']
                    );
                    $textBody = $rendered['text_body'];
                    
                    error_log("[notification.template] Using template {$template['slug']} for event order_created order {$orderId}");
                } else {
                    // Fallback para conteúdo inline
                    $subject = '[ImobSites] Seu pedido foi criado – finalize o pagamento';
                    $content = $this->buildOrderCreatedEmailContent(
                        $customerName,
                        $planName,
                        $cycleLabel,
                        $formattedAmount,
                        $paymentMethodLabel,
                        $paymentUrl,
                        $orderId
                    );
                    $htmlBody = $this->mailService->buildEmailTemplate(
                        'Pedido Criado - ImobSites',
                        $content
                    );
                    $textBody = null;
                    
                    error_log("[notification.template] No active template for event order_created, using fallback for order {$orderId}");
                }

                // Envia o e-mail
                $sent = $this->mailService->send(
                    $customerEmail,
                    $customerName,
                    $subject,
                    $htmlBody,
                    $textBody
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
            try {
                // Busca dados do pedido com JOIN na tabela plans
                $order = $this->fetchOrderWithPlanAndTenant($orderId);

                if (!$order) {
                    error_log("[notification.order_paid] Pedido {$orderId} não encontrado para envio de notificação");
                    return;
                }

                // Validação de dados mínimos
                $customerEmail = trim($order['customer_email'] ?? '');
                $customerName = trim($order['customer_name'] ?? 'Cliente');

                if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                    error_log("[notification.order_paid] E-mail inválido para pedido {$orderId}: " . substr($customerEmail, 0, 20));
                    return;
                }

                // Verifica se o pedido está realmente pago
                $orderStatus = strtolower(trim($order['status'] ?? ''));
                if ($orderStatus !== 'paid') {
                    error_log("[notification.order_paid] Pedido {$orderId} não está pago (status: {$orderStatus}). Notificação não será enviada.");
                    return;
                }

                // Prepara dados para o e-mail
                $planName = $order['plan_name'] ?? $order['plan_code'] ?? 'Plano';
                $billingCycle = $order['plan_billing_cycle'] ?? $order['order_billing_cycle'] ?? '';
                $months = (int)($order['months'] ?? 1);
                $pricePerMonth = (float)($order['price_per_month'] ?? 0.0);
                $totalAmount = (float)($order['total_amount'] ?? 0.0);
                $paymentMethod = $order['payment_method'] ?? '';
                $paidAt = $order['paid_at'] ?? $order['updated_at'] ?? $order['created_at'] ?? date('Y-m-d H:i:s');

                // Formata valores e dados
                $cycleLabel = $this->formatBillingCycle($billingCycle);
                $formattedAmount = 'R$ ' . number_format($totalAmount, 2, ',', '.');
                $paymentMethodLabel = $this->formatPaymentMethod($paymentMethod);
                $paidAtFormatted = date('d/m/Y à\s H:i', strtotime($paidAt));
                $createdAt = date('d/m/Y à\s H:i', strtotime($order['created_at'] ?? date('Y-m-d H:i:s')));

                // Tentar carregar template ativo
                $template = $this->templateService->findActiveTemplateByEvent('order_paid');
                
                if ($template) {
                    // Usar template do banco
                    $variables = [
                        'customer_name' => $customerName,
                        'customer_email' => $customerEmail,
                        'order_id' => (string)$orderId,
                        'plan_name' => $planName,
                        'plan_cycle' => $cycleLabel,
                        'plan_total' => $formattedAmount,
                        'payment_method' => $paymentMethodLabel,
                        'paid_at' => $paidAtFormatted,
                        'created_at' => $createdAt,
                    ];
                    
                    $rendered = $this->templateService->renderTemplate($template, $variables);
                    $subject = $rendered['subject'];
                    $htmlBody = $this->mailService->buildEmailTemplate(
                        'Pagamento Confirmado - ImobSites',
                        $rendered['html_body']
                    );
                    $textBody = $rendered['text_body'];
                    
                    error_log("[notification.template] Using template {$template['slug']} for event order_paid order {$orderId}");
                } else {
                    // Fallback para conteúdo inline
                    $subject = "Pagamento confirmado – seu ImobSites está quase pronto";
                    $content = $this->buildOrderPaidEmailContent(
                        $customerName,
                        $planName,
                        $cycleLabel,
                        $months,
                        $pricePerMonth,
                        $formattedAmount,
                        $paymentMethodLabel,
                        $paidAtFormatted,
                        $orderId
                    );
                    $htmlBody = $this->mailService->buildEmailTemplate(
                        'Pagamento Confirmado - ImobSites',
                        $content
                    );
                    $textBody = null;
                    
                    error_log("[notification.template] No active template for event order_paid, using fallback for order {$orderId}");
                }

                // Envia o e-mail
                $sent = $this->mailService->send(
                    $customerEmail,
                    $customerName,
                    $subject,
                    $htmlBody,
                    $textBody
                );

                if ($sent) {
                    error_log("[notification.order_paid.email] E-mail enviado com sucesso para pedido {$orderId} | Destinatário: " . substr($customerEmail, 0, 30));
                } else {
                    error_log("[notification.order_paid.email] Falha ao enviar e-mail para pedido {$orderId} | Destinatário: " . substr($customerEmail, 0, 30));
                }

                // Futuramente: enviar WhatsApp aqui
                // $this->whatsappService->sendOrderPaid($orderId);

            } catch (Throwable $e) {
                error_log("[notification.order_paid.error] Erro ao enviar notificações para pedido {$orderId}: " . $e->getMessage());
                // Não propaga a exceção para não quebrar o fluxo do webhook
            }
        }

        /**
         * Envia lembretes de cobrança pendente.
         * 
         * @param int $orderId ID do pedido
         * @return bool true se o e-mail foi enviado com sucesso, false caso contrário
         */
        public function sendOrderReminderNotifications(int $orderId): bool
        {
            try {
                // Busca dados do pedido com JOIN na tabela plans
                $order = $this->fetchOrderWithPlanAndTenant($orderId);

                if (!$order) {
                    error_log("[notification.order_reminder] Order not found: {$orderId}");
                    return false;
                }

                // Validação de dados mínimos
                $customerEmail = trim($order['customer_email'] ?? '');
                $customerName = trim($order['customer_name'] ?? 'Cliente');

                if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                    error_log("[notification.order_reminder] Missing or invalid e-mail for order {$orderId}");
                    return false;
                }

                // Verifica se o pedido está pendente
                $orderStatus = strtolower(trim($order['status'] ?? ''));
                if ($orderStatus !== 'pending') {
                    error_log("[notification.order_reminder] Order {$orderId} not pending, skipping");
                    return false;
                }

                // Prepara dados para o e-mail
                $planName = $order['plan_name'] ?? $order['plan_code'] ?? 'Plano';
                $billingCycle = $order['plan_billing_cycle'] ?? $order['order_billing_cycle'] ?? '';
                $months = (int)($order['months'] ?? 1);
                $totalAmount = (float)($order['total_amount'] ?? 0.0);
                $paymentMethod = $order['payment_method'] ?? '';
                $paymentUrl = $order['payment_url'] ?? '';

                // Formata valores e dados
                $cycleLabel = $this->formatBillingCycle($billingCycle);
                $formattedAmount = 'R$ ' . number_format($totalAmount, 2, ',', '.');
                $paymentMethodLabel = $this->formatPaymentMethod($paymentMethod);
                $reminderCount = (int)($order['reminder_count'] ?? 0);
                $createdAt = date('d/m/Y à\s H:i', strtotime($order['created_at'] ?? date('Y-m-d H:i:s')));

                // Tentar carregar template ativo
                $template = $this->templateService->findActiveTemplateByEvent('order_reminder');
                
                if ($template) {
                    // Usar template do banco
                    $variables = [
                        'customer_name' => $customerName,
                        'customer_email' => $customerEmail,
                        'order_id' => (string)$orderId,
                        'plan_name' => $planName,
                        'plan_cycle' => $cycleLabel,
                        'plan_total' => $formattedAmount,
                        'payment_method' => $paymentMethodLabel,
                        'payment_url' => $paymentUrl,
                        'created_at' => $createdAt,
                        'reminder_count' => (string)$reminderCount,
                    ];
                    
                    $rendered = $this->templateService->renderTemplate($template, $variables);
                    $subject = $rendered['subject'];
                    $htmlBody = $this->mailService->buildEmailTemplate(
                        'Lembrete de Pagamento - ImobSites',
                        $rendered['html_body']
                    );
                    $textBody = $rendered['text_body'];
                    
                    error_log("[notification.template] Using template {$template['slug']} for event order_reminder order {$orderId}");
                } else {
                    // Fallback para conteúdo inline
                    $subject = "Pagamento pendente – finalize seu ImobSites";
                    $content = $this->buildOrderReminderEmailContent(
                        $customerName,
                        $planName,
                        $cycleLabel,
                        $months,
                        $formattedAmount,
                        $paymentMethodLabel,
                        $paymentUrl,
                        $orderId
                    );
                    $htmlBody = $this->mailService->buildEmailTemplate(
                        'Lembrete de Pagamento - ImobSites',
                        $content
                    );
                    $textBody = null;
                    
                    error_log("[notification.template] No active template for event order_reminder, using fallback for order {$orderId}");
                }

                // Envia o e-mail
                $sent = $this->mailService->send(
                    $customerEmail,
                    $customerName,
                    $subject,
                    $htmlBody,
                    $textBody
                );

                if ($sent) {
                    error_log("[notification.order_reminder] Reminder e-mail sent for order {$orderId} to {$customerEmail}");
                    return true;
                } else {
                    error_log("[notification.order_reminder.error] Failed to send reminder for order {$orderId}: MailService returned false");
                    return false;
                }

                // Futuramente: enviar WhatsApp aqui
                // $this->whatsappService->sendOrderReminder($orderId);

            } catch (Throwable $e) {
                error_log("[notification.order_reminder.error] Erro ao enviar lembrete para pedido {$orderId}: " . $e->getMessage());
                // Não propaga a exceção para não quebrar o fluxo do serviço de lembretes
                return false;
            }
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
                    p.description_short AS plan_description
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
         * Busca dados do pedido com JOIN na tabela plans e tenants.
         * 
         * @param int $orderId
         * @return array<string,mixed>|null
         */
        private function fetchOrderWithPlanAndTenant(int $orderId): ?array
        {
            $sql = "
                SELECT 
                    o.id,
                    o.customer_name,
                    o.customer_email,
                    o.payment_method,
                    o.plan_code,
                    o.status,
                    o.paid_at,
                    o.billing_cycle AS order_billing_cycle,
                    o.total_amount,
                    o.payment_url,
                    o.tenant_id,
                    o.created_at,
                    o.updated_at,
                    o.reminder_count,
                    o.first_reminder_sent_at,
                    o.last_reminder_sent_at,
                    p.name AS plan_name,
                    p.description_short AS plan_description,
                    p.billing_cycle AS plan_billing_cycle,
                    p.months,
                    p.price_per_month
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
                error_log("[notification.order_paid.error] Erro ao buscar pedido {$orderId}: " . $e->getMessage());
                return null;
            }
        }

        /**
         * Busca dados de ativação do tenant (domínio primário, token de ativação, status do usuário).
         * 
         * @param int $tenantId
         * @return array<string,mixed>|null
         */
        private function fetchTenantActivationData(int $tenantId): ?array
        {
            $sql = "
                SELECT 
                    td.domain AS primary_domain,
                    u.activation_token,
                    u.activation_expires_at,
                    u.ativo AS user_active
                FROM tenants t
                LEFT JOIN tenant_domains td ON td.tenant_id = t.id AND td.is_primary = 1
                LEFT JOIN usuarios u ON u.tenant_id = t.id AND u.nivel = 'admin'
                WHERE t.id = ?
                ORDER BY u.id ASC
                LIMIT 1
            ";

            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$tenantId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ?: null;
            } catch (PDOException $e) {
                error_log("[notification.order_paid.error] Erro ao buscar dados de ativação do tenant {$tenantId}: " . $e->getMessage());
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

        /**
         * Monta o conteúdo HTML do e-mail de pagamento confirmado.
         * 
         * @param string $customerName
         * @param string $planName
         * @param string $cycleLabel
         * @param int $months
         * @param float $pricePerMonth
         * @param string $formattedAmount
         * @param string $paymentMethodLabel
         * @param string $paidAtFormatted
         * @param int $orderId
         * @return string HTML
         */
        private function buildOrderPaidEmailContent(
            string $customerName,
            string $planName,
            string $cycleLabel,
            int $months,
            float $pricePerMonth,
            string $formattedAmount,
            string $paymentMethodLabel,
            string $paidAtFormatted,
            int $orderId
        ): string {
            $content = "<p>Olá, <strong>" . htmlspecialchars($customerName, ENT_QUOTES) . "</strong>!</p>";
            $content .= "<p style=\"margin: 20px 0;\">Seu pagamento foi <strong style=\"color: #28a745;\">confirmado com sucesso</strong>!</p>";
            
            // Informações do pagamento
            $content .= "<table style=\"width: 100%; margin: 20px 0; border-collapse: collapse;\">";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Pedido:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\">#{$orderId}</td></tr>";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Plano:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\">" . htmlspecialchars($planName, ENT_QUOTES) . "</td></tr>";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Valor Total:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong style=\"color: #28a745;\">" . htmlspecialchars($formattedAmount, ENT_QUOTES) . "</strong></td></tr>";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Forma de Pagamento:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\">" . htmlspecialchars($paymentMethodLabel, ENT_QUOTES) . "</td></tr>";
            
            if ($paidAtFormatted !== '') {
                $content .= "<tr><td style=\"padding: 10px;\"><strong>Data/Hora do Pagamento:</strong></td><td style=\"padding: 10px;\">" . htmlspecialchars($paidAtFormatted, ENT_QUOTES) . "</td></tr>";
            }
            $content .= "</table>";

            // Resumo do plano
            $planSummary = htmlspecialchars($planName, ENT_QUOTES);
            if ($cycleLabel !== '') {
                $planSummary .= " (" . htmlspecialchars($cycleLabel, ENT_QUOTES) . ")";
            }
            if ($months > 1) {
                $formattedPricePerMonth = 'R$ ' . number_format($pricePerMonth, 2, ',', '.');
                $planSummary .= " - " . $months . " mês" . ($months > 1 ? "es" : "") . " x " . $formattedPricePerMonth;
            }

            $content .= "<div style=\"background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;\">";
            $content .= "<p style=\"margin: 0;\"><strong>Resumo do Plano:</strong> " . $planSummary;
            if ($cycleLabel !== '' && strpos(strtolower($cycleLabel), 'mensal') !== false) {
                $content .= " - Renovação automática";
            }
            $content .= "</p>";
            $content .= "</div>";

            // Instruções sobre a conta e e-mail de ativação
            $content .= "<p style=\"margin: 30px 0 15px 0;\"><strong>Próximos passos:</strong></p>";
            $content .= "<p>Sua conta está sendo preparada e você receberá um e-mail com as instruções de ativação em breve.</p>";
            $content .= "<p>No e-mail de ativação, você encontrará um link para ativar sua conta e definir sua senha de acesso ao painel administrativo.</p>";

            $content .= "<p style=\"color: #666; font-size: 14px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;\">";
            $content .= "Se você tiver alguma dúvida ou precisar de suporte, estamos à disposição!";
            $content .= "</p>";

            return $content;
        }

        /**
         * Monta o conteúdo HTML do e-mail de lembrete de pagamento pendente.
         * 
         * @param string $customerName
         * @param string $planName
         * @param string $cycleLabel
         * @param int $months
         * @param string $formattedAmount
         * @param string $paymentMethodLabel
         * @param string $paymentUrl
         * @param int $orderId
         * @return string HTML
         */
        private function buildOrderReminderEmailContent(
            string $customerName,
            string $planName,
            string $cycleLabel,
            int $months,
            string $formattedAmount,
            string $paymentMethodLabel,
            string $paymentUrl,
            int $orderId
        ): string {
            $content = "<p>Olá, <strong>" . htmlspecialchars($customerName, ENT_QUOTES) . "</strong>!</p>";
            $content .= "<p style=\"margin: 20px 0;\">Existe um pedido pendente de pagamento aguardando sua finalização.</p>";
            
            // Resumo do pedido
            $content .= "<table style=\"width: 100%; margin: 20px 0; border-collapse: collapse;\">";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Número do pedido:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\">#{$orderId}</td></tr>";
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Nome do plano:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\">" . htmlspecialchars($planName, ENT_QUOTES) . "</td></tr>";
            
            // Ciclo e quantidade de meses
            $cycleInfo = htmlspecialchars($cycleLabel, ENT_QUOTES);
            if ($months > 1) {
                $cycleInfo .= " - " . $months . " mês" . ($months > 1 ? "es" : "");
            }
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Ciclo:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\">" . $cycleInfo . "</td></tr>";
            
            $content .= "<tr><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>Valor total:</strong></td><td style=\"padding: 10px; border-bottom: 1px solid #eee;\"><strong>" . htmlspecialchars($formattedAmount, ENT_QUOTES) . "</strong></td></tr>";
            $content .= "<tr><td style=\"padding: 10px;\"><strong>Forma de pagamento:</strong></td><td style=\"padding: 10px;\">" . htmlspecialchars($paymentMethodLabel, ENT_QUOTES) . "</td></tr>";
            $content .= "</table>";

            // Link de pagamento
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

            // Informação sobre criação automática da conta
            $content .= "<p style=\"margin: 30px 0 15px 0;\"><strong>O que acontece após o pagamento?</strong></p>";
            $content .= "<p>Após o pagamento, você receberá o e-mail de ativação da conta.</p>";

            $content .= "<p style=\"color: #666; font-size: 14px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;\">";
            $content .= "Se você tiver alguma dúvida ou precisar de suporte, estamos à disposição!";
            $content .= "</p>";

            return $content;
        }
    }
}

