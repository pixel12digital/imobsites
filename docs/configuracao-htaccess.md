# Configuração do .htaccess

## ⚠️ IMPORTANTE: Segurança

**NUNCA faça commit do arquivo `.htaccess` com credenciais reais no repositório Git!**

O arquivo `.htaccess` contém informações sensíveis (chaves de API, tokens) e está configurado no `.gitignore` para não ser versionado.

## Configuração Inicial

1. **Copie o arquivo de exemplo:**
   ```bash
   cp .htaccess.example .htaccess
   ```

2. **Edite o `.htaccess` e preencha com suas credenciais reais:**
   - `ASAAS_API_KEY`: Sua chave de API do Asaas
   - `ASAAS_ENV`: `production` ou `sandbox`
   - `ASAAS_API_BASE_URL`: URL da API (produção ou sandbox)
   - `ASAAS_WEBHOOK_TOKEN`: Token para validação de webhooks

## Ambientes

### Produção
```apache
<IfModule mod_env.c>
    SetEnv ASAAS_API_KEY "sua_chave_de_producao_aqui"
    SetEnv ASAAS_ENV "production"
    SetEnv ASAAS_API_BASE_URL "https://api.asaas.com/v3"
    SetEnv ASAAS_WEBHOOK_TOKEN "seu_webhook_token_producao"
</IfModule>
```

### Sandbox (Testes)
```apache
<IfModule mod_env.c>
    SetEnv ASAAS_API_KEY "sua_chave_de_sandbox_aqui"
    SetEnv ASAAS_ENV "sandbox"
    SetEnv ASAAS_API_BASE_URL "https://api-sandbox.asaas.com/v3"
    SetEnv ASAAS_WEBHOOK_TOKEN "seu_webhook_token_sandbox"
</IfModule>
```

## Como Obter as Credenciais

1. Acesse: https://www.asaas.com
2. Faça login na sua conta
3. Vá em: **Configurações > Integrações > API**
4. Copie a chave de API (formato: `$aact_prod_...` ou `$aact_YTU...`)
5. Para webhooks, configure em: **Configurações > Webhooks**

## Verificação

Após configurar, teste se as variáveis estão sendo lidas:

```bash
# Via navegador:
https://seu-dominio.com/scripts/test_asaas_env.php

# Ou via script:
php scripts/test_asaas_api_key.php
```

## Se o .htaccess Já Foi Commitado

Se você acidentalmente fez commit do `.htaccess` com credenciais:

1. **Remova do histórico do Git:**
   ```bash
   git rm --cached .htaccess
   git commit -m "Remove .htaccess do repositório (contém credenciais)"
   ```

2. **Revogue as credenciais expostas:**
   - Acesse o painel do Asaas
   - Revogue a chave de API exposta
   - Gere uma nova chave de API

3. **Adicione ao .gitignore (já está configurado):**
   ```
   .htaccess
   ```

4. **Crie o .htaccess localmente** (não será commitado)

## Boas Práticas

- ✅ Use `.htaccess.example` como template
- ✅ Mantenha `.htaccess` no `.gitignore`
- ✅ Use variáveis de ambiente do servidor quando possível
- ✅ Revogue chaves expostas imediatamente
- ✅ Use chaves diferentes para desenvolvimento e produção
- ❌ Nunca compartilhe credenciais em mensagens, emails ou chats
- ❌ Nunca faça commit de arquivos com credenciais

