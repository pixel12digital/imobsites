<?php
/**
 * Configuração de e-mail transacional
 * 
 * Este arquivo centraliza as configurações para envio de e-mails transacionais
 * do sistema (notificações de pedidos, ativações de conta, etc.).
 * 
 * Para produção, considere usar variáveis de ambiente ou um serviço SMTP robusto.
 */

// Configurações de SMTP (opcional - se não definidas, usa mail() nativo do PHP)
define('MAIL_SMTP_ENABLED', getenv('MAIL_SMTP_ENABLED') ?: false);
define('MAIL_SMTP_HOST', getenv('MAIL_SMTP_HOST') ?: 'smtp.example.com');
define('MAIL_SMTP_PORT', getenv('MAIL_SMTP_PORT') ?: 587);
define('MAIL_SMTP_USER', getenv('MAIL_SMTP_USER') ?: '');
define('MAIL_SMTP_PASS', getenv('MAIL_SMTP_PASS') ?: '');
define('MAIL_SMTP_SECURE', getenv('MAIL_SMTP_SECURE') ?: 'tls'); // tls ou ssl

// Configurações do remetente padrão
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: 'no-reply@imobsites.com.br');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'ImobSites');

// Configurações adicionais
define('MAIL_REPLY_TO', getenv('MAIL_REPLY_TO') ?: MAIL_FROM_EMAIL);
define('MAIL_CHARSET', 'UTF-8');

/**
 * Retorna as configurações de e-mail como array associativo
 * 
 * @return array<string,mixed>
 */
function getEmailConfig(): array
{
    return [
        'smtp_enabled' => (bool)MAIL_SMTP_ENABLED,
        'smtp_host' => (string)MAIL_SMTP_HOST,
        'smtp_port' => (int)MAIL_SMTP_PORT,
        'smtp_user' => (string)MAIL_SMTP_USER,
        'smtp_pass' => (string)MAIL_SMTP_PASS,
        'smtp_secure' => (string)MAIL_SMTP_SECURE,
        'from_email' => (string)MAIL_FROM_EMAIL,
        'from_name' => (string)MAIL_FROM_NAME,
        'reply_to' => (string)MAIL_REPLY_TO,
        'charset' => (string)MAIL_CHARSET,
    ];
}

