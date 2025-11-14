<?php
/**
 * MailService
 * 
 * Serviço centralizado para envio de e-mails transacionais.
 * 
 * Por padrão, usa a função mail() nativa do PHP.
 * Pode ser estendido para usar PHPMailer ou outro serviço SMTP no futuro.
 * 
 * IMPORTANTE: Este serviço NÃO deve logar conteúdo sensível (como tokens)
 * em texto plano nos logs de erro.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/email.php';

if (!class_exists('MailService')) {
    class MailService
    {
        private ?PDO $db;
        private array $config;

        public function __construct(?PDO $db = null)
        {
            $this->db = $db;
            $this->config = getEmailConfig();
        }

        /**
         * Envia um e-mail transacional.
         * 
         * @param string $toEmail E-mail do destinatário
         * @param string $toName Nome do destinatário
         * @param string $subject Assunto do e-mail
         * @param string $htmlBody Corpo do e-mail em HTML
         * @param string|null $textBody Corpo do e-mail em texto plano (opcional)
         * @return bool true em caso de sucesso, false caso contrário
         */
        public function send(
            string $toEmail,
            string $toName,
            string $subject,
            string $htmlBody,
            ?string $textBody = null
        ): bool {
            // Validação básica
            $toEmail = trim($toEmail);
            $toName = trim($toName);

            if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                error_log('[mail.error] E-mail do destinatário inválido: ' . substr($toEmail, 0, 20));
                return false;
            }

            if ($subject === '') {
                error_log('[mail.error] Assunto do e-mail não pode ser vazio');
                return false;
            }

            if ($htmlBody === '') {
                error_log('[mail.error] Corpo do e-mail não pode ser vazio');
                return false;
            }

            // Se não tiver texto plano, gera um básico removendo tags HTML
            if ($textBody === null || $textBody === '') {
                $textBody = strip_tags($htmlBody);
                $textBody = html_entity_decode($textBody, ENT_QUOTES, $this->config['charset']);
            }

            // Monta os headers
            $headers = $this->buildHeaders($toEmail, $toName);

            // Prepara o corpo (multipart/alternative para HTML + texto)
            $body = $this->buildMultipartBody($htmlBody, $textBody);

            // Tenta enviar
            try {
                $result = mail(
                    $this->formatRecipient($toEmail, $toName),
                    $subject,
                    $body,
                    $headers
                );

                if ($result) {
                    error_log(sprintf(
                        '[mail.success] E-mail enviado para %s | Assunto: %s',
                        substr($toEmail, 0, 30),
                        substr($subject, 0, 50)
                    ));
                    return true;
                } else {
                    error_log(sprintf(
                        '[mail.error] Falha ao enviar e-mail para %s | Assunto: %s',
                        substr($toEmail, 0, 30),
                        substr($subject, 0, 50)
                    ));
                    return false;
                }
            } catch (Throwable $e) {
                error_log(sprintf(
                    '[mail.error] Exceção ao enviar e-mail para %s: %s',
                    substr($toEmail, 0, 30),
                    $e->getMessage()
                ));
                return false;
            }
        }

        /**
         * Monta os headers do e-mail.
         * 
         * @param string $toEmail E-mail do destinatário
         * @param string $toName Nome do destinatário
         * @return string Headers formatados
         */
        private function buildHeaders(string $toEmail, string $toName): string
        {
            $headers = [];
            
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = sprintf('Content-Type: multipart/alternative; boundary="%s"', $this->getBoundary());
            $headers[] = sprintf('From: %s <%s>', $this->config['from_name'], $this->config['from_email']);
            $headers[] = sprintf('Reply-To: %s', $this->config['reply_to']);
            $headers[] = sprintf('X-Mailer: ImobSites-MailService/1.0');
            $headers[] = sprintf('X-Priority: 3');
            $headers[] = sprintf('Date: %s', date('r'));

            return implode("\r\n", $headers);
        }

        /**
         * Monta o corpo multipart (texto + HTML).
         * 
         * @param string $htmlBody
         * @param string $textBody
         * @return string
         */
        private function buildMultipartBody(string $htmlBody, string $textBody): string
        {
            $boundary = $this->getBoundary();
            
            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=" . $this->config['charset'] . "\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $textBody . "\r\n\r\n";
            
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=" . $this->config['charset'] . "\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";
            
            $body .= "--{$boundary}--";

            return $body;
        }

        /**
         * Gera um boundary único para multipart.
         * 
         * @return string
         */
        private function getBoundary(): string
        {
            return '----=_Part_' . md5(time() . uniqid());
        }

        /**
         * Formata o destinatário (e-mail + nome).
         * 
         * @param string $email
         * @param string $name
         * @return string
         */
        private function formatRecipient(string $email, string $name): string
        {
            if ($name !== '') {
                return sprintf('%s <%s>', $name, $email);
            }
            return $email;
        }

        /**
         * Gera um template HTML simples para e-mails transacionais.
         * 
         * @param string $title Título do e-mail
         * @param string $content Conteúdo principal (HTML)
         * @param string|null $footer Footer customizado (opcional)
         * @return string HTML completo
         */
        public function buildEmailTemplate(string $title, string $content, ?string $footer = null): string
        {
            if ($footer === null) {
                $footer = '<p style="color: #666; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">';
                $footer .= 'Este é um e-mail automático. Por favor, não responda diretamente.<br>';
                $footer .= '&copy; ' . date('Y') . ' ImobSites. Todos os direitos reservados.';
                $footer .= '</p>';
            }

            return sprintf(
                '<!DOCTYPE html>
<html>
<head>
    <meta charset="%s">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>%s</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 30px 40px; background-color: #023A8D; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px;">%s</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px 40px;">
                            %s
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 40px 30px; background-color: #f9f9f9; border-radius: 0 0 8px 8px;">
                            %s
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>',
                htmlspecialchars($this->config['charset'], ENT_QUOTES),
                htmlspecialchars($title, ENT_QUOTES),
                htmlspecialchars($title, ENT_QUOTES),
                $content,
                $footer
            );
        }
    }
}

