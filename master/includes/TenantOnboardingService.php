<?php
/**
 * TenantOnboardingService
 *
 * Responsável por transformar pedidos pagos em tenants ativos.
 * Reutiliza helpers globais (config/database.php, master/utils.php, OrderService).
 *
 * TODO: integrar com serviço de e-mail definitivo (ex.: SMTP/Mailgun).
 */

declare(strict_types=1);

require_once __DIR__ . '/OrderService.php';
require_once __DIR__ . '/../utils.php';

if (!function_exists('generateActivationToken')) {
    function generateActivationToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('getTenantDomainBase')) {
    function getTenantDomainBase(): string
    {
        $fromEnv = getenv('TENANT_DOMAIN_BASE');
        if ($fromEnv) {
            return strtolower(trim($fromEnv));
        }

        // TODO: parametrizar o domínio base em configuração central.
        return 'clientes.imobsites.local';
    }
}

if (!function_exists('generateUniqueTenantSlug')) {
    function generateUniqueTenantSlug(string $name): string
    {
        $base = generateSlug($name ?: 'cliente');
        if ($base === '') {
            $base = 'cliente';
        }

        $slug = $base;
        $index = 1;

        while (fetch('SELECT id FROM tenants WHERE slug = ? LIMIT 1', [$slug])) {
            $index++;
            $slug = $base . '-' . $index;
        }

        return $slug;
    }
}

if (!function_exists('createTenantWithDefaults')) {
    /**
     * Cria tenant + settings + domínio + usuário admin em transação.
     *
     * @param array<string,mixed> $order
     * @return array<string,mixed> ['tenant_id'=>int,'user_id'=>int,'activation_token'=>string]
     * @throws Throwable
     */
    function createTenantWithDefaults(array $order): array
    {
        global $pdo;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('Conexão PDO não disponível.');
        }

        $now = date('Y-m-d H:i:s');

        $slug = generateUniqueTenantSlug($order['customer_name'] ?? 'cliente');
        $domainBase = getTenantDomainBase();
        $primaryDomain = $slug . '.' . $domainBase;

        $pdo->beginTransaction();

        try {
            $tenantId = insert('tenants', [
                'name' => cleanInput($order['customer_name'] ?? 'Cliente ImobSites'),
                'slug' => $slug,
                'status' => 'active',
                'contact_email' => $order['customer_email'] ?? null,
                'contact_whatsapp' => $order['customer_whatsapp'] ?? null,
                'notes' => sprintf(
                    'Pedido #%d • Plano: %s • Ciclo: %s',
                    $order['id'],
                    $order['plan_code'] ?? 'N/D',
                    $order['billing_cycle'] ?? 'N/D'
                ),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            insert('tenant_settings', [
                'tenant_id' => $tenantId,
                'site_name' => $order['customer_name'] ?? 'Portal Imobiliário',
                'site_email' => $order['customer_email'] ?? null,
                'primary_color' => '#023A8D',
                'secondary_color' => '#F7931E',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            insert('tenant_domains', [
                'tenant_id' => $tenantId,
                'domain' => $primaryDomain,
                'is_primary' => 1,
                'created_at' => $now,
            ]);

            $activationToken = generateActivationToken();
            $activationExpiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
            $temporaryPassword = password_hash(generateRandomPassword(16), PASSWORD_DEFAULT);

            $userId = insert('usuarios', [
                'nome' => $order['customer_name'] ?? 'Administrador',
                'email' => $order['customer_email'] ?? null,
                'senha' => $temporaryPassword,
                'nivel' => 'admin',
                'ativo' => 0,
                'tenant_id' => $tenantId,
                'data_criacao' => $now,
                'data_atualizacao' => $now,
                'activation_token' => $activationToken,
                'activation_expires_at' => $activationExpiresAt,
            ]);

            $pdo->commit();

            return [
                'tenant_id' => (int)$tenantId,
                'user_id' => (int)$userId,
                'activation_token' => $activationToken,
                'primary_domain' => $primaryDomain,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('buildTenantActivationLink')) {
    function buildTenantActivationLink(string $token): string
    {
        $baseUrl = getenv('TENANT_ACTIVATION_BASE_URL');

        if (!$baseUrl) {
            if (function_exists('getBaseUrl')) {
                $baseUrl = getBaseUrl();
            } else {
                // TODO: definir base URL padrão para ativação em ambientes sem contexto HTTP.
                $baseUrl = 'https://painel.imobsites.com.br';
            }
        }

        return rtrim($baseUrl, '/') . '/ativar-conta.php?token=' . urlencode($token);
    }
}

if (!function_exists('sendTenantActivationEmail')) {
    /**
     * Dispara e-mail com link de ativação.
     * 
     * Agora usa MailService centralizado para envio de e-mails transacionais.
     * Suporta templates administráveis via EmailTemplateService.
     *
     * @param array<string,mixed> $context
     * @return void
     */
    function sendTenantActivationEmail(array $context): void
    {
        require_once __DIR__ . '/MailService.php';
        require_once __DIR__ . '/EmailTemplateService.php';

        try {
            global $pdo;
            $mailService = new MailService($pdo);
            $templateService = new EmailTemplateService($pdo);

            $email = trim($context['email'] ?? '');
            $name = trim($context['name'] ?? 'Cliente');
            $activationLink = $context['activation_link'] ?? '';
            $primaryDomain = $context['primary_domain'] ?? '';
            $tenantId = $context['tenant_id'] ?? null;
            $orderId = $context['order_id'] ?? null;

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error_log('[tenant_onboarding] E-mail inválido para envio de ativação: ' . substr($email, 0, 20));
                return;
            }

            // Buscar nome do tenant se disponível
            $tenantName = $name;
            if ($tenantId) {
                try {
                    $tenant = fetch('SELECT name FROM tenants WHERE id = ? LIMIT 1', [$tenantId]);
                    if ($tenant && !empty($tenant['name'])) {
                        $tenantName = $tenant['name'];
                    }
                } catch (Throwable $e) {
                    // Ignorar erro ao buscar tenant
                }
            }

            // Tentar carregar template ativo
            $template = $templateService->findActiveTemplateByEvent('tenant_activation');
            
            if ($template) {
                // Buscar e-mail de suporte (pode vir de email_settings ou config)
                $supportEmail = '';
                $supportWhatsapp = '';
                try {
                    $emailSettings = fetch('SELECT from_email, reply_to_email FROM email_settings LIMIT 1');
                    if ($emailSettings) {
                        $supportEmail = $emailSettings['reply_to_email'] ?? $emailSettings['from_email'] ?? '';
                    }
                } catch (Throwable $e) {
                    // Ignorar erro
                }
                
                // Usar template do banco
                $variables = [
                    'customer_name' => $name,
                    'tenant_name' => $tenantName,
                    'activation_link' => $activationLink,
                    'support_email' => $supportEmail,
                    'support_whatsapp' => $supportWhatsapp,
                ];
                
                $rendered = $templateService->renderTemplate($template, $variables);
                $subject = $rendered['subject'];
                $htmlBody = $mailService->buildEmailTemplate(
                    'Ative sua Conta - ImobSites',
                    $rendered['html_body']
                );
                $textBody = $rendered['text_body'];
                
                error_log("[notification.template] Using template {$template['slug']} for event tenant_activation tenant {$tenantId}");
            } else {
                // Fallback para conteúdo inline
                $subject = '[ImobSites] Ative sua conta - Acesso ao painel';
                
                $content = "<p>Olá, <strong>" . htmlspecialchars($name, ENT_QUOTES) . "</strong>!</p>";
                $content .= "<p>Seu pagamento foi confirmado e sua conta está pronta para ser ativada.</p>";
                
                if ($primaryDomain !== '') {
                    $content .= "<p><strong>Seu domínio:</strong> " . htmlspecialchars($primaryDomain, ENT_QUOTES) . "</p>";
                }
                
                $content .= "<p>Clique no botão abaixo para ativar sua conta e definir sua senha:</p>";
                
                if ($activationLink !== '') {
                    $content .= "<p style=\"margin: 30px 0;\">";
                    $content .= "<a href=\"" . htmlspecialchars($activationLink, ENT_QUOTES) . "\" style=\"display: inline-block; padding: 15px 30px; background-color: #F7931E; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;\">";
                    $content .= "Ativar Minha Conta";
                    $content .= "</a>";
                    $content .= "</p>";
                    
                    $content .= "<p style=\"color: #666; font-size: 14px;\">";
                    $content .= "Ou copie e cole este link no seu navegador:<br>";
                    $content .= "<code style=\"background-color: #f5f5f5; padding: 5px 10px; border-radius: 3px; word-break: break-all;\">" . htmlspecialchars($activationLink, ENT_QUOTES) . "</code>";
                    $content .= "</p>";
                }
                
                $content .= "<p style=\"color: #666; font-size: 12px; margin-top: 30px;\">";
                $content .= "Este link é válido por 7 dias. Se não ativar sua conta neste período, entre em contato conosco.";
                $content .= "</p>";

                $htmlBody = $mailService->buildEmailTemplate(
                    'Ative sua Conta - ImobSites',
                    $content
                );
                $textBody = null;
                
                error_log("[notification.template] No active template for event tenant_activation, using fallback");
            }

            $sent = $mailService->send(
                $email,
                $name,
                $subject,
                $htmlBody,
                $textBody
            );

            if ($sent) {
                error_log(sprintf(
                    '[tenant_onboarding] E-mail de ativação enviado com sucesso | orderId=%s | tenantId=%s | email=%s',
                    $orderId ?? 'N/A',
                    $tenantId ?? 'N/A',
                    substr($email, 0, 30)
                ));
            } else {
                error_log(sprintf(
                    '[tenant_onboarding] Falha ao enviar e-mail de ativação | orderId=%s | tenantId=%s | email=%s',
                    $orderId ?? 'N/A',
                    $tenantId ?? 'N/A',
                    substr($email, 0, 30)
                ));
            }
        } catch (Throwable $e) {
            error_log('[tenant_onboarding] Erro ao enviar e-mail de ativação: ' . $e->getMessage());
            // Não propaga a exceção para não quebrar o fluxo de onboarding
        }
    }
}

if (!function_exists('onPaidOrderCreateTenantAndSendActivation')) {
    /**
     * Entrada principal chamada pelo webhook após confirmar pagamento.
     *
     * @param int $orderId
     * @return void
     */
    function onPaidOrderCreateTenantAndSendActivation(int $orderId): void
    {
        $order = fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);

        if (!$order) {
            throw new InvalidArgumentException('Pedido não encontrado para onboarding.');
        }

        if (($order['status'] ?? '') !== 'paid') {
            error_log(sprintf('[tenant_onboarding] Pedido #%d não está pago. Status atual: %s', $orderId, $order['status'] ?? 'n/d'));
            return;
        }

        if (!empty($order['tenant_id'])) {
            error_log(sprintf('[tenant_onboarding] Pedido #%d já vinculado ao tenant #%d.', $orderId, $order['tenant_id']));
            return;
        }

        try {
            $result = createTenantWithDefaults($order);
        } catch (Throwable $e) {
            error_log('[tenant_onboarding] Falha ao criar tenant para pedido #' . $orderId . ': ' . $e->getMessage());
            throw $e;
        }

        attachTenantToOrder($orderId, $result['tenant_id']);

        $activationLink = buildTenantActivationLink($result['activation_token']);

        sendTenantActivationEmail([
            'order_id' => $orderId,
            'tenant_id' => $result['tenant_id'],
            'user_id' => $result['user_id'],
            'email' => $order['customer_email'],
            'name' => $order['customer_name'],
            'activation_link' => $activationLink,
            'primary_domain' => $result['primary_domain'],
        ]);
    }
}

