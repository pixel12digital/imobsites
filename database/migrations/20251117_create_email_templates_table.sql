-- Migration: Create email_templates table for email template management
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    event_type VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    subject VARCHAR(255) NOT NULL,
    html_body MEDIUMTEXT NOT NULL,
    text_body MEDIUMTEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create default templates
INSERT INTO email_templates (name, slug, event_type, description, subject, html_body, text_body, is_active) VALUES
(
    'Pedido Criado - Padrão',
    'order_created_default',
    'order_created',
    'Template padrão para e-mail de pedido criado',
    '[ImobSites] Seu pedido foi criado – finalize o pagamento',
    '<p>Olá, <strong>{{customer_name}}</strong>!</p>
<p>Seu pedido foi criado com sucesso. Seguem as informações:</p>
<table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Pedido:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;">#{{order_id}}</td></tr>
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Plano:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;">{{plan_name}}</td></tr>
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Ciclo:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;">{{plan_cycle}}</td></tr>
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Valor Total:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>{{plan_total}}</strong></td></tr>
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Forma de Pagamento:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;">{{payment_method}}</td></tr>
</table>
{{#if payment_url}}
<p style="margin: 30px 0;">
<a href="{{payment_url}}" style="display: inline-block; padding: 15px 30px; background-color: #F7931E; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;">Finalizar Pagamento</a>
</p>
<p style="color: #666; font-size: 14px;">
Ou copie e cole este link no seu navegador:<br>
<code style="background-color: #f5f5f5; padding: 5px 10px; border-radius: 3px; word-break: break-all;">{{payment_url}}</code>
</p>
{{/if}}
<p style="margin-top: 30px; padding: 15px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
<strong>Observação:</strong> Se você já concluiu o pagamento, desconsidere este e-mail.
</p>',
    'Olá, {{customer_name}}!

Seu pedido foi criado com sucesso. Seguem as informações:

Pedido: #{{order_id}}
Plano: {{plan_name}}
Ciclo: {{plan_cycle}}
Valor Total: {{plan_total}}
Forma de Pagamento: {{payment_method}}

{{#if payment_url}}
Link para pagamento: {{payment_url}}
{{/if}}

Observação: Se você já concluiu o pagamento, desconsidere este e-mail.',
    1
),
(
    'Pagamento Confirmado - Padrão',
    'order_paid_default',
    'order_paid',
    'Template padrão para e-mail de pagamento confirmado',
    'Pagamento confirmado – seu ImobSites está quase pronto',
    '<p>Olá, <strong>{{customer_name}}</strong>!</p>
<p style="margin: 20px 0;">Seu pagamento foi <strong style="color: #28a745;">confirmado com sucesso</strong>!</p>
<table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Pedido:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;">#{{order_id}}</td></tr>
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Plano:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;">{{plan_name}}</td></tr>
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Valor Total:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong style="color: #28a745;">{{plan_total}}</strong></td></tr>
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Forma de Pagamento:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;">{{payment_method}}</td></tr>
{{#if paid_at}}
<tr><td style="padding: 10px;"><strong>Data/Hora do Pagamento:</strong></td><td style="padding: 10px;">{{paid_at}}</td></tr>
{{/if}}
</table>
<p style="margin: 30px 0 15px 0;"><strong>Próximos passos:</strong></p>
<p>Sua conta está sendo preparada e você receberá um e-mail com as instruções de ativação em breve.</p>
<p>No e-mail de ativação, você encontrará um link para ativar sua conta e definir sua senha de acesso ao painel administrativo.</p>
<p style="color: #666; font-size: 14px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
Se você tiver alguma dúvida ou precisar de suporte, estamos à disposição!
</p>',
    'Olá, {{customer_name}}!

Seu pagamento foi confirmado com sucesso!

Pedido: #{{order_id}}
Plano: {{plan_name}}
Valor Total: {{plan_total}}
Forma de Pagamento: {{payment_method}}
{{#if paid_at}}
Data/Hora do Pagamento: {{paid_at}}
{{/if}}

Próximos passos:
Sua conta está sendo preparada e você receberá um e-mail com as instruções de ativação em breve.',
    1
),
(
    'Lembrete de Pagamento - Padrão',
    'order_reminder_default',
    'order_reminder',
    'Template padrão para e-mail de lembrete de pagamento',
    'Pagamento pendente – finalize seu ImobSites',
    '<p>Olá, <strong>{{customer_name}}</strong>!</p>
<p style="margin: 20px 0;">Existe um pedido pendente de pagamento aguardando sua finalização.</p>
<table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Número do pedido:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;">#{{order_id}}</td></tr>
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Nome do plano:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;">{{plan_name}}</td></tr>
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Ciclo:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;">{{plan_cycle}}</td></tr>
<tr><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Valor total:</strong></td><td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>{{plan_total}}</strong></td></tr>
<tr><td style="padding: 10px;"><strong>Forma de pagamento:</strong></td><td style="padding: 10px;">{{payment_method}}</td></tr>
</table>
{{#if payment_url}}
<p style="margin: 30px 0;">
<a href="{{payment_url}}" style="display: inline-block; padding: 15px 30px; background-color: #F7931E; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;">Finalizar Pagamento</a>
</p>
<p style="color: #666; font-size: 14px;">
Ou copie e cole este link no seu navegador:<br>
<code style="background-color: #f5f5f5; padding: 5px 10px; border-radius: 3px; word-break: break-all;">{{payment_url}}</code>
</p>
{{/if}}
<p style="margin: 30px 0 15px 0;"><strong>O que acontece após o pagamento?</strong></p>
<p>Após o pagamento, você receberá o e-mail de ativação da conta.</p>
<p style="color: #666; font-size: 14px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
Se você tiver alguma dúvida ou precisar de suporte, estamos à disposição!
</p>',
    'Olá, {{customer_name}}!

Existe um pedido pendente de pagamento aguardando sua finalização.

Número do pedido: #{{order_id}}
Nome do plano: {{plan_name}}
Ciclo: {{plan_cycle}}
Valor total: {{plan_total}}
Forma de pagamento: {{payment_method}}

{{#if payment_url}}
Link para pagamento: {{payment_url}}
{{/if}}

O que acontece após o pagamento?
Após o pagamento, você receberá o e-mail de ativação da conta.',
    1
),
(
    'Ativação de Conta - Padrão',
    'tenant_activation_default',
    'tenant_activation',
    'Template padrão para e-mail de ativação de conta',
    '[ImobSites] Ative sua conta - Acesso ao painel',
    '<p>Olá, <strong>{{customer_name}}</strong>!</p>
<p>Seu pagamento foi confirmado e sua conta está pronta para ser ativada.</p>
{{#if tenant_name}}
<p><strong>Nome da conta:</strong> {{tenant_name}}</p>
{{/if}}
<p>Clique no botão abaixo para ativar sua conta e definir sua senha:</p>
{{#if activation_link}}
<p style="margin: 30px 0;">
<a href="{{activation_link}}" style="display: inline-block; padding: 15px 30px; background-color: #F7931E; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;">Ativar Minha Conta</a>
</p>
<p style="color: #666; font-size: 14px;">
Ou copie e cole este link no seu navegador:<br>
<code style="background-color: #f5f5f5; padding: 5px 10px; border-radius: 3px; word-break: break-all;">{{activation_link}}</code>
</p>
{{/if}}
<p style="color: #666; font-size: 12px; margin-top: 30px;">
Este link é válido por 7 dias. Se não ativar sua conta neste período, entre em contato conosco.
</p>
{{#if support_email}}
<p style="color: #666; font-size: 12px; margin-top: 15px;">
E-mail de suporte: <a href="mailto:{{support_email}}">{{support_email}}</a>
</p>
{{/if}}
{{#if support_whatsapp}}
<p style="color: #666; font-size: 12px;">
WhatsApp: {{support_whatsapp}}
</p>
{{/if}}',
    'Olá, {{customer_name}}!

Seu pagamento foi confirmado e sua conta está pronta para ser ativada.

{{#if tenant_name}}
Nome da conta: {{tenant_name}}
{{/if}}

Clique no link abaixo para ativar sua conta e definir sua senha:
{{#if activation_link}}
{{activation_link}}
{{/if}}

Este link é válido por 7 dias. Se não ativar sua conta neste período, entre em contato conosco.

{{#if support_email}}
E-mail de suporte: {{support_email}}
{{/if}}
{{#if support_whatsapp}}
WhatsApp: {{support_whatsapp}}
{{/if}}',
    1
)
ON DUPLICATE KEY UPDATE name=name;

