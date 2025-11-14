<?php
/**
 * EmailTemplateService
 * 
 * Serviço para gerenciar e renderizar templates de e-mail.
 * 
 * Suporta:
 * - Busca de templates por evento ou slug
 * - Renderização de templates com substituição de variáveis {{chave}}
 * - Lista de eventos disponíveis
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

if (!class_exists('EmailTemplateService')) {
    class EmailTemplateService
    {
        private PDO $db;

        public function __construct(PDO $db)
        {
            $this->db = $db;
        }

        /**
         * Busca um template ativo por tipo de evento.
         * 
         * @param string $eventType Tipo do evento (ex.: 'order_created', 'order_paid')
         * @return array<string,mixed>|null Template encontrado ou null
         */
        public function findActiveTemplateByEvent(string $eventType): ?array
        {
            try {
                $sql = "
                    SELECT * 
                    FROM email_templates 
                    WHERE event_type = ? AND is_active = 1 
                    ORDER BY id ASC 
                    LIMIT 1
                ";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$eventType]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return $result ?: null;
            } catch (PDOException $e) {
                error_log('[email_template.error] Erro ao buscar template por evento ' . $eventType . ': ' . $e->getMessage());
                return null;
            }
        }

        /**
         * Busca um template por slug.
         * 
         * @param string $slug Slug do template
         * @return array<string,mixed>|null Template encontrado ou null
         */
        public function findTemplateBySlug(string $slug): ?array
        {
            try {
                $sql = "SELECT * FROM email_templates WHERE slug = ? LIMIT 1";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$slug]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return $result ?: null;
            } catch (PDOException $e) {
                error_log('[email_template.error] Erro ao buscar template por slug ' . $slug . ': ' . $e->getMessage());
                return null;
            }
        }

        /**
         * Retorna a lista de eventos disponíveis.
         * 
         * @return array<string,string> Array associativo evento => descrição
         */
        public function getAvailableEvents(): array
        {
            return [
                'order_created' => 'Pedido criado',
                'order_paid' => 'Pagamento confirmado',
                'order_reminder' => 'Lembrete de pagamento',
                'tenant_activation' => 'Ativação de conta / acesso',
            ];
        }

        /**
         * Renderiza um template substituindo variáveis {{chave}} pelos valores.
         * 
         * @param array<string,mixed> $template Template (deve conter subject, html_body, text_body)
         * @param array<string,mixed> $variables Array associativo de variáveis para substituir
         * @return array<string,string> Array com subject, html_body, text_body renderizados
         */
        public function renderTemplate(array $template, array $variables): array
        {
            $subject = $template['subject'] ?? '';
            $htmlBody = $template['html_body'] ?? '';
            $textBody = $template['text_body'] ?? null;

            // Normalizar variáveis: null vira string vazia
            $normalizedVars = [];
            foreach ($variables as $key => $value) {
                $normalizedVars[$key] = $value ?? '';
            }

            // Fazer substituição de {{chave}} em cada campo
            // Subject e HTML precisam de escape HTML
            $subject = $this->replaceVariables($subject, $normalizedVars, true);
            $htmlBody = $this->replaceVariables($htmlBody, $normalizedVars, true);
            
            if ($textBody !== null && $textBody !== '') {
                // Text body não precisa de escape HTML
                $textBody = $this->replaceVariables($textBody, $normalizedVars, false);
            } else {
                // Se não tiver text_body, gerar a partir do HTML (remover tags)
                $textBody = strip_tags($htmlBody);
                $textBody = html_entity_decode($textBody, ENT_QUOTES, 'UTF-8');
            }

            return [
                'subject' => $subject,
                'html_body' => $htmlBody,
                'text_body' => $textBody,
            ];
        }

        /**
         * Substitui variáveis {{chave}} em um texto.
         * 
         * @param string $text Texto com variáveis
         * @param array<string,string> $variables Variáveis para substituir
         * @param bool $escapeHtml Se true, faz escape HTML dos valores
         * @return string Texto com variáveis substituídas
         */
        private function replaceVariables(string $text, array $variables, bool $escapeHtml = true): string
        {
            foreach ($variables as $key => $value) {
                // Escapar valor se necessário (para HTML)
                $replacement = $escapeHtml ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : (string)$value;
                
                // Substituir {{chave}}
                $text = str_replace('{{' . $key . '}}', $replacement, $text);
                
                // Também substituir sem as chaves para compatibilidade
                $text = str_replace('{{ ' . $key . ' }}', $replacement, $text);
            }

            // Limpar variáveis não substituídas (remover {{chave}} que não foram encontradas)
            $text = preg_replace('/\{\{[^}]+\}\}/', '', $text);

            return $text;
        }

        /**
         * Retorna as variáveis disponíveis para um evento específico.
         * 
         * @param string $eventType Tipo do evento
         * @return array<string,string> Array associativo variável => descrição
         */
        public function getAvailableVariablesForEvent(string $eventType): array
        {
            $variables = [];

            switch ($eventType) {
                case 'order_created':
                case 'order_paid':
                case 'order_reminder':
                    $variables = [
                        'customer_name' => 'Nome do cliente',
                        'customer_email' => 'E-mail do cliente',
                        'order_id' => 'ID do pedido',
                        'plan_name' => 'Nome do plano',
                        'plan_cycle' => 'Ciclo de cobrança (ex.: Mensal, Anual)',
                        'plan_total' => 'Valor total formatado (ex.: R$ 1.234,56)',
                        'payment_method' => 'Método de pagamento (ex.: PIX, Boleto)',
                        'payment_url' => 'URL para finalizar pagamento',
                        'created_at' => 'Data/hora de criação do pedido',
                        'paid_at' => 'Data/hora do pagamento (apenas order_paid)',
                        'reminder_count' => 'Número do lembrete (apenas order_reminder)',
                    ];
                    break;

                case 'tenant_activation':
                    $variables = [
                        'customer_name' => 'Nome do cliente',
                        'tenant_name' => 'Nome do tenant/cliente',
                        'activation_link' => 'Link para ativar a conta',
                        'support_email' => 'E-mail de suporte',
                        'support_whatsapp' => 'WhatsApp de suporte',
                    ];
                    break;
            }

            return $variables;
        }

        /**
         * Busca todos os templates.
         * 
         * @param string|null $eventType Filtrar por tipo de evento (opcional)
         * @return array<array<string,mixed>> Lista de templates
         */
        public function getAllTemplates(?string $eventType = null): array
        {
            try {
                if ($eventType) {
                    $sql = "SELECT * FROM email_templates WHERE event_type = ? ORDER BY event_type, name";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$eventType]);
                } else {
                    $sql = "SELECT * FROM email_templates ORDER BY event_type, name";
                    $stmt = $this->db->query($sql);
                }
                
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $results ?: [];
            } catch (PDOException $e) {
                error_log('[email_template.error] Erro ao buscar templates: ' . $e->getMessage());
                return [];
            }
        }

        /**
         * Cria ou atualiza um template.
         * 
         * @param array<string,mixed> $data Dados do template
         * @param int|null $id ID do template (null para criar novo)
         * @return int|false ID do template criado/atualizado ou false em caso de erro
         */
        public function saveTemplate(array $data, ?int $id = null)
        {
            try {
                if ($id === null) {
                    // Criar novo
                    $sql = "
                        INSERT INTO email_templates 
                        (name, slug, event_type, description, subject, html_body, text_body, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $data['name'],
                        $data['slug'],
                        $data['event_type'],
                        $data['description'] ?? null,
                        $data['subject'],
                        $data['html_body'],
                        $data['text_body'] ?? null,
                        $data['is_active'] ?? 1,
                    ]);
                    return (int)$this->db->lastInsertId();
                } else {
                    // Atualizar existente
                    $sql = "
                        UPDATE email_templates 
                        SET name = ?, slug = ?, event_type = ?, description = ?, 
                            subject = ?, html_body = ?, text_body = ?, is_active = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $data['name'],
                        $data['slug'],
                        $data['event_type'],
                        $data['description'] ?? null,
                        $data['subject'],
                        $data['html_body'],
                        $data['text_body'] ?? null,
                        $data['is_active'] ?? 1,
                        $id,
                    ]);
                    return $id;
                }
            } catch (PDOException $e) {
                error_log('[email_template.error] Erro ao salvar template: ' . $e->getMessage());
                return false;
            }
        }

        /**
         * Exclui um template.
         * 
         * @param int $id ID do template
         * @return bool true se excluído com sucesso, false caso contrário
         */
        public function deleteTemplate(int $id): bool
        {
            try {
                $sql = "DELETE FROM email_templates WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$id]);
                return $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                error_log('[email_template.error] Erro ao excluir template: ' . $e->getMessage());
                return false;
            }
        }
    }
}

