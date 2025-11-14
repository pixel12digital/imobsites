# 🚀 Guia de Deploy - Configuração Asaas em Produção

## Passo a Passo para Configurar em Produção

### 1. Atualizar o .htaccess no Servidor

**Via cPanel File Manager:**
1. Acesse o cPanel do seu servidor
2. Vá em **File Manager**
3. Navegue até a raiz do site (geralmente `public_html` ou `painel.imobsites.com.br`)
4. Localize o arquivo `.htaccess`
5. Clique com botão direito > **Edit**
6. Localize a seção de configuração do Asaas (ou adicione se não existir)

**Substitua ou adicione esta seção:**

```apache
# Configuração Asaas (produção)
<IfModule mod_env.c>
    SetEnv ASAAS_API_KEY "$aact_prod_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjRmN2Q5YzRlLTA4OGQtNDlkYi1iMjBmLWMwN2M3NzkzMGQyMzo6JGFhY2hfYTQ1ZjdmOTctMTAxNC00MjVhLTg1NmUtMzJjMzNhYmI4OTA3"
    SetEnv ASAAS_ENV "production"
    SetEnv ASAAS_API_BASE_URL "https://api.asaas.com/v3"
    SetEnv ASAAS_WEBHOOK_TOKEN "imobsites_production_webhook_token"
</IfModule>
```

**Importante:**
- ✅ A chave deve estar **exatamente** como mostrado acima (com o `$` no início)
- ✅ Não adicione espaços antes ou depois da chave
- ✅ A chave deve estar toda em uma única linha (sem quebras)
- ✅ Salve o arquivo após editar

### 2. Verificar se o Módulo mod_env Está Habilitado

O Apache precisa ter o módulo `mod_env` habilitado para ler as variáveis do `.htaccess`.

**Como verificar (se tiver acesso SSH):**
```bash
apache2ctl -M | grep env
# ou
httpd -M | grep env
```

Se não aparecer `env_module`, entre em contato com o suporte da hospedagem para habilitar.

**Nota:** Na maioria das hospedagens compartilhadas (como cPanel), o módulo já está habilitado.

### 3. Aguardar/Reiniciar Apache

Após salvar o `.htaccess`:
- **Aguarde 1-2 minutos** para as alterações serem aplicadas
- **OU** reinicie o Apache (se tiver acesso)
- **OU** reinicie o serviço via cPanel (se disponível)

### 4. Verificar se as Variáveis Estão Sendo Lidas

**Teste 1: Verificar Variáveis de Ambiente**
```
https://painel.imobsites.com.br/scripts/test_asaas_env.php
```

**O que verificar:**
- ✅ Todas as variáveis devem aparecer como "ENCONTRADA"
- ✅ A chave de API deve começar com `$aact_prod_...`
- ✅ Ambiente deve ser `production`
- ✅ Base URL deve ser `https://api.asaas.com/v3`

**Teste 2: Testar Chave de API**
```
https://painel.imobsites.com.br/scripts/test_asaas_api_key.php
```

**O que verificar:**
- ✅ HTTP Status Code: **200** (sucesso)
- ✅ Mensagem: "Chave de API VÁLIDA!"
- ❌ Se retornar 401: A chave está inválida ou incorreta

### 5. Testar Criação de Pedido

**Teste 3: Testar API Completa**
```
https://painel.imobsites.com.br/scripts/test_order_api_production.php
```

**O que verificar:**
- ✅ Deve criar um pedido de teste com sucesso
- ✅ Deve retornar `"success": true`
- ❌ Se retornar erro, verifique os logs

### 6. Verificar Logs (se houver problemas)

**Locais dos logs:**
- cPanel > **Errors** ou **Error Log**
- Procure por entradas com:
  - `[orders.create]`
  - `[asaas.config]`
  - `[asaas.http.error]`

## Checklist de Verificação

Antes de considerar tudo funcionando, verifique:

- [ ] `.htaccess` atualizado com a chave de produção
- [ ] Chave de API está correta (começa com `$aact_prod_...`)
- [ ] Ambiente configurado como `production`
- [ ] Base URL configurada como `https://api.asaas.com/v3`
- [ ] Variáveis sendo lidas corretamente (teste 1)
- [ ] Chave de API válida (teste 2 - HTTP 200)
- [ ] API criando pedidos com sucesso (teste 3)
- [ ] Aguardou 1-2 minutos após salvar o `.htaccess`

## Problemas Comuns e Soluções

### ❌ Variáveis não estão sendo lidas

**Possíveis causas:**
1. Módulo `mod_env` não habilitado
2. `.htaccess` com erro de sintaxe
3. Espaços extras na chave

**Solução:**
- Verifique a sintaxe do `.htaccess`
- Remova espaços extras
- Entre em contato com suporte se `mod_env` não estiver habilitado

### ❌ Chave de API retorna 401 (inválida)

**Possíveis causas:**
1. Chave copiada incorretamente (espaços extras)
2. Chave expirada ou revogada
3. Chave de sandbox sendo usada em produção

**Solução:**
- Verifique se a chave está completa e sem espaços
- Gere uma nova chave no painel do Asaas
- Certifique-se de usar chave de **produção** (não sandbox)

### ❌ Erro ao criar pedido

**Possíveis causas:**
1. Chave de API inválida
2. Plano não encontrado no banco
3. Dados inválidos na requisição

**Solução:**
- Verifique os logs de erro
- Teste a chave isoladamente (teste 2)
- Verifique se os planos existem no banco de dados

## Configuração Final Recomendada

Após tudo funcionar, recomenda-se:

1. **Configurar Webhook do Asaas:**
   - Acesse: https://www.asaas.com > Configurações > Webhooks
   - Configure a URL: `https://painel.imobsites.com.br/api/webhooks/asaas.php`
   - Use o token: `imobsites_production_webhook_token`

2. **Monitorar Logs:**
   - Verifique periodicamente os logs de erro
   - Monitore pedidos criados com sucesso

3. **Backup:**
   - Mantenha backup do `.htaccess` (sem commit no Git)
   - Documente as credenciais em local seguro

## Suporte

Se após seguir todos os passos ainda houver problemas:

1. Execute os 3 scripts de teste
2. Copie as mensagens de erro
3. Verifique os logs do servidor
4. Entre em contato com suporte técnico fornecendo:
   - Resultados dos testes
   - Mensagens de erro
   - Logs relevantes

