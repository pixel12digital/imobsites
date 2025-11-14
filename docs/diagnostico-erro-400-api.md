# Diagnóstico do Erro 400 na API de Pedidos

## Problema
Ao executar o comando de teste apontando para produção (`https://painel.imobsites.com.br/api/orders/create.php`), a API retorna erro 400 (Bad Request).

## Possíveis Causas

### 1. Variáveis de Ambiente do .htaccess Não Sendo Lidas
**Sintoma:** As variáveis `ASAAS_API_KEY`, `ASAAS_ENV`, etc. definidas no `.htaccess` não estão disponíveis no PHP.

**Verificação:**
- Acesse: `https://painel.imobsites.com.br/scripts/test_asaas_env.php`
- Verifique se todas as variáveis aparecem como "ENCONTRADA"

**Soluções:**
- Verificar se o módulo `mod_env` do Apache está habilitado
- Verificar se o `.htaccess` está no diretório correto (raiz do projeto)
- Verificar se há erros de sintaxe no `.htaccess`
- Reiniciar o Apache após alterações no `.htaccess`

### 2. Payload JSON Inválido ou Incompleto
**Sintoma:** O JSON enviado não contém todos os campos obrigatórios ou está malformado.

**Campos Obrigatórios:**
```json
{
  "plan_code": "P02_ANUAL",
  "customer_name": "Nome do Cliente",
  "customer_email": "email@example.com",
  "payment_method": "pix|boleto|credit_card"
}
```

**Para cartão de crédito, também é necessário:**
```json
{
  "card": {
    "cardNumber": "4111111111111111",
    "holderName": "NOME DO PORTADOR",
    "expiryMonth": "12",
    "expiryYear": "2025",
    "ccv": "123"
  }
}
```

**Verificação:**
- Execute: `scripts/test_order_api_production.php` para testar com dados válidos

### 3. Plano Não Encontrado ou Inativo
**Sintoma:** O `plan_code` enviado não existe no banco de dados ou está inativo.

**Verificação:**
- Verifique se o plano existe e está ativo no banco de dados
- Verifique se o `plan_code` está correto (case-sensitive)

### 4. Problema com CORS
**Sintoma:** A requisição pode estar sendo bloqueada por CORS.

**Verificação:**
- O endpoint permite apenas requisições de `https://imobsites.com.br`
- Se estiver testando de outro domínio, pode ser necessário ajustar o CORS

### 5. Erro na Configuração do Asaas
**Sintoma:** A configuração do Asaas não está sendo carregada corretamente.

**Verificação:**
- Execute: `scripts/test_asaas_env.php`
- Verifique os logs de erro do PHP para mensagens como `[asaas.config]`

## Como Diagnosticar

### Passo 1: Verificar Variáveis de Ambiente
```bash
# Acesse via navegador:
https://painel.imobsites.com.br/scripts/test_asaas_env.php
```

### Passo 2: Testar a API com Script de Diagnóstico
```bash
# Execute via CLI ou acesse via navegador:
php scripts/test_order_api_production.php
# ou
https://painel.imobsites.com.br/scripts/test_order_api_production.php
```

### Passo 3: Verificar Logs de Erro
- Acesse o painel de controle do servidor
- Verifique os logs de erro do Apache/PHP
- Procure por entradas com:
  - `[orders.create]`
  - `[asaas.config]`
  - `[orders.create.error]`

### Passo 4: Testar Requisição Manual
```powershell
# PowerShell
$body = @{
    plan_code = "P02_ANUAL"
    customer_name = "Teste API"
    customer_email = "teste@example.com"
    payment_method = "pix"
} | ConvertTo-Json

Invoke-RestMethod `
  -Uri 'https://painel.imobsites.com.br/api/orders/create.php' `
  -Method POST `
  -Body $body `
  -ContentType 'application/json' `
  -ErrorAction Stop
```

## Soluções Recomendadas

### 1. Verificar .htaccess
Certifique-se de que o `.htaccess` está configurado corretamente:

```apache
<IfModule mod_env.c>
    SetEnv ASAAS_API_KEY "sua_chave_aqui"
    SetEnv ASAAS_ENV "production"  # ou "sandbox" para testes
    SetEnv ASAAS_API_BASE_URL "https://api.asaas.com/v3"  # ou "https://api-sandbox.asaas.com/v3"
    SetEnv ASAAS_WEBHOOK_TOKEN "seu_token_aqui"
</IfModule>
```

**Importante:** 
- Para produção, use `ASAAS_ENV "production"` e `ASAAS_API_BASE_URL "https://api.asaas.com/v3"`
- Para testes, use `ASAAS_ENV "sandbox"` e `ASAAS_API_BASE_URL "https://api-sandbox.asaas.com/v3"`

### 2. Verificar Módulo mod_env
No servidor, verifique se o módulo está habilitado:
```bash
apache2ctl -M | grep env
# ou
httpd -M | grep env
```

### 3. Adicionar Logs de Debug
Se necessário, adicione logs temporários no `api/orders/create.php` para ver exatamente onde está falhando.

### 4. Verificar Permissões
Certifique-se de que o `.htaccess` tem permissões de leitura:
```bash
chmod 644 .htaccess
```

## Próximos Passos

1. Execute `scripts/test_asaas_env.php` para verificar variáveis de ambiente
2. Execute `scripts/test_order_api_production.php` para testar a API
3. Verifique os logs de erro do servidor
4. Se as variáveis não estiverem sendo lidas, verifique a configuração do Apache
5. Se o erro persistir, verifique o payload JSON enviado

## Observações Importantes

- As variáveis de ambiente do `.htaccess` só funcionam quando o PHP é executado via Apache (não funciona em CLI)
- Após alterar o `.htaccess`, pode ser necessário reiniciar o Apache
- Em produção, prefira usar variáveis de ambiente do servidor ao invés do `.htaccess` quando possível
- O ambiente configurado no `.htaccess` deve corresponder ao ambiente real (sandbox vs production)

