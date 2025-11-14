<?php
/**
 * Template de e-mail: Pagamento Confirmado / Acesso Liberado
 * 
 * Este template é usado pelo NotificationService para enviar e-mail
 * quando um pedido é confirmado como pago.
 * 
 * @param array<string,mixed> $data Dados do pedido e cliente
 * @return string HTML do e-mail
 */

$customerName = htmlspecialchars($data['customer_name'] ?? 'Cliente', ENT_QUOTES);
$planName = htmlspecialchars($data['plan_name'] ?? $data['plan_code'] ?? 'Plano', ENT_QUOTES);
$totalAmount = (float)($data['total_amount'] ?? 0.0);
$formattedAmount = 'R$ ' . number_format($totalAmount, 2, ',', '.');
$paidAt = $data['paid_at'] ?? $data['created_at'] ?? date('Y-m-d H:i:s');
$paidAtFormatted = date('d/m/Y à\s H:i', strtotime($paidAt));

// Verifica se o tenant já foi criado
$tenantId = $data['tenant_id'] ?? null;
$activationLink = $data['activation_link'] ?? null;
$primaryDomain = $data['primary_domain'] ?? null;
$hasTenant = !empty($tenantId);

$content = "<p>Olá, <strong>{$customerName}</strong>!</p>";
$content .= "<p style=\"margin: 20px 0;\">Seu pagamento foi <strong style=\"color: #28a745;\">confirmado com sucesso</strong>!</p>";

// Informações do pedido
$content .= "<div style=\"background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;\">";
$content .= "<h3 style=\"margin: 0 0 15px 0; color: #023A8D;\">Detalhes do Pagamento</h3>";
$content .= "<table style=\"width: 100%; border-collapse: collapse;\">";
$content .= "<tr><td style=\"padding: 8px 0; border-bottom: 1px solid #dee2e6;\"><strong>Pedido:</strong></td><td style=\"padding: 8px 0; border-bottom: 1px solid #dee2e6;\">#{$data['order_id']}</td></tr>";
$content .= "<tr><td style=\"padding: 8px 0; border-bottom: 1px solid #dee2e6;\"><strong>Plano:</strong></td><td style=\"padding: 8px 0; border-bottom: 1px solid #dee2e6;\">{$planName}</td></tr>";
$content .= "<tr><td style=\"padding: 8px 0; border-bottom: 1px solid #dee2e6;\"><strong>Valor Pago:</strong></td><td style=\"padding: 8px 0; border-bottom: 1px solid #dee2e6;\"><strong style=\"color: #28a745;\">{$formattedAmount}</strong></td></tr>";
$content .= "<tr><td style=\"padding: 8px 0;\"><strong>Data de Pagamento:</strong></td><td style=\"padding: 8px 0;\">{$paidAtFormatted}</td></tr>";
$content .= "</table>";
$content .= "</div>";

// Instruções baseadas no status do tenant
if ($hasTenant && $activationLink) {
    // Tenant criado, mas ainda não ativado
    $content .= "<p style=\"margin: 30px 0 15px 0;\"><strong>Próximo passo:</strong> Ative sua conta para começar a usar o ImobSites!</p>";
    $content .= "<p>Clique no botão abaixo para ativar sua conta e definir sua senha:</p>";
    
    $content .= "<p style=\"margin: 30px 0;\">";
    $content .= "<a href=\"" . htmlspecialchars($activationLink, ENT_QUOTES) . "\" style=\"display: inline-block; padding: 15px 30px; background-color: #28a745; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;\">";
    $content .= "Ativar Minha Conta";
    $content .= "</a>";
    $content .= "</p>";
    
    $content .= "<p style=\"color: #666; font-size: 14px;\">";
    $content .= "Ou copie e cole este link no seu navegador:<br>";
    $content .= "<code style=\"background-color: #f5f5f5; padding: 5px 10px; border-radius: 3px; word-break: break-all;\">" . htmlspecialchars($activationLink, ENT_QUOTES) . "</code>";
    $content .= "</p>";
    
    if ($primaryDomain) {
        $content .= "<p style=\"margin-top: 20px;\">";
        $content .= "<strong>Seu domínio:</strong> <a href=\"https://{$primaryDomain}\" style=\"color: #023A8D;\">{$primaryDomain}</a>";
        $content .= "</p>";
    }
    
    $content .= "<p style=\"color: #856404; background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px; margin-top: 30px;\">";
    $content .= "<strong>⚠️ Importante:</strong> Este link é válido por 7 dias. Após a ativação, você poderá acessar seu painel administrativo.";
    $content .= "</p>";
} elseif ($hasTenant && $primaryDomain) {
    // Tenant criado e já ativado (presumivelmente)
    $content .= "<p style=\"margin: 30px 0 15px 0;\"><strong>Suas credenciais de acesso:</strong></p>";
    $content .= "<div style=\"background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0;\">";
    $content .= "<p style=\"margin: 5px 0;\"><strong>URL do Painel:</strong> <a href=\"https://{$primaryDomain}\" style=\"color: #023A8D;\">https://{$primaryDomain}</a></p>";
    $content .= "<p style=\"margin: 5px 0;\">Use o e-mail cadastrado e a senha que você definiu durante a ativação.</p>";
    $content .= "</div>";
    
    $content .= "<p style=\"margin: 30px 0;\">";
    $content .= "<a href=\"https://{$primaryDomain}\" style=\"display: inline-block; padding: 15px 30px; background-color: #023A8D; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;\">";
    $content .= "Acessar Meu Painel";
    $content .= "</a>";
    $content .= "</p>";
} else {
    // Tenant ainda não criado (situação rara, mas possível)
    $content .= "<p style=\"margin: 30px 0 15px 0;\">Sua conta está sendo preparada e você receberá um e-mail com as instruções de ativação em breve.</p>";
    $content .= "<p>Se você não receber o e-mail de ativação nas próximas horas, entre em contato conosco.</p>";
}

$content .= "<p style=\"color: #666; font-size: 14px; margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6;\">";
$content .= "Se você tiver alguma dúvida ou precisar de suporte, estamos à disposição!";
$content .= "</p>";

return $content;

