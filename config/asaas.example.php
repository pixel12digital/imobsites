<?php
/**
 * Configuração do Asaas - Template
 *
 * INSTRUÇÕES:
 * 1. Copie este arquivo para config/asaas.php
 * 2. Preencha os valores abaixo com suas credenciais do Asaas
 * 3. NUNCA faça commit do arquivo config/asaas.php (ele deve estar no .gitignore)
 *
 * IMPORTANTE:
 * - Em PRODUÇÃO, prefira definir as variáveis de ambiente (ASAAS_API_KEY, etc.)
 *   via .htaccess (SetEnv) ou configuração do servidor, ao invés de editar este arquivo.
 * - O código tentará ler primeiro das variáveis de ambiente, depois deste arquivo.
 *
 * Como obter as credenciais:
 * - Acesse https://www.asaas.com e faça login
 * - Vá em Configurações > Integrações > API
 * - Copie a "Chave de API" (API Key)
 * - Para webhooks, configure um token de segurança
 */

return [
    // Chave de API do Asaas (obrigatória)
    // Obtenha em: https://www.asaas.com > Configurações > Integrações > API
    'api_key' => 'sua_chave_api_aqui',

    // Ambiente: 'sandbox' ou 'production'
    // Use 'sandbox' para testes locais, 'production' para ambiente real
    'env' => 'sandbox',

    // URL base da API (opcional - será definida automaticamente se não informada)
    // Sandbox: https://api-sandbox.asaas.com/v3
    // Production: https://api.asaas.com/v3
    'base_url' => null, // null = usar padrão conforme 'env'

    // Token para validação de webhooks (opcional, mas recomendado)
    // Configure em: https://www.asaas.com > Configurações > Webhooks
    'webhook_token' => null,
];

