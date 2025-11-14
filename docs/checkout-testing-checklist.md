# Checklist de Testes - Sistema de Billing Asaas

## 1. ✅ Checklist de Configuração

### Config Asaas Local (Dev)
- [ ] `config/asaas.php` existe e contém:
  - [ ] `env = 'sandbox'`
  - [ ] `api_key` preenchida com chave do sandbox
  - [ ] `base_url = null` (será definido automaticamente como `https://api-sandbox.asaas.com/v3`)
  - [ ] `webhook_token` configurado (opcional, mas recomendado)

### Config Asaas Produção
- [ ] No servidor (`painel.imobsites.com.br`), no `.htaccess` da raiz ou no painel do servidor:
  - [ ] `ASAAS_API_KEY` definida
  - [ ] `ASAAS_ENV = 'production'` (ou `'sandbox'` se ainda em testes)
  - [ ] `ASAAS_API_BASE_URL = 'https://api.asaas.com/v3'` (ou deixar null para auto)
  - [ ] `ASAAS_WEBHOOK_TOKEN` definido (opcional, mas recomendado)

### Planos no Painel
- [ ] Tabela `plans` preenchida com:
  - [ ] `code` único (ex.: `P01_MENSAL`, `P02_ANUAL`)
  - [ ] `billing_mode` = `'recurring_monthly'` ou `'prepaid_parceled'`
  - [ ] `months`, `total_amount`, `max_installments` coerentes
  - [ ] `is_active = 1`

---

## 2. 🧪 Testes via API (Postman/Insomnia/curl)

### 2.1. Teste 1 – Plano Pré-pago Parcelado (Anual) com Cartão

**Endpoint:** `POST https://painel.imobsites.com.br/api/orders/create.php`

**Body JSON:**
```json
{
  "plan_code": "P02_ANUAL",
  "customer_name": "Teste Anual Cartão",
  "customer_email": "teste+anual@example.com",
  "customer_whatsapp": "47999999999",
  "customer_cpf_cnpj": "12345678901",
  "payment_method": "credit_card",
  "payment_installments": 12,
  "card": {
    "holderName": "Teste Checkout",
    "number": "4111111111111111",
    "expiryMonth": "12",
    "expiryYear": "2030",
    "ccv": "123",
    "cpfCnpj": "12345678901",
    "email": "teste+anual@example.com",
    "mobilePhone": "47999999999",
    "postalCode": "89000000",
    "addressNumber": "100",
    "address": "Rua Teste",
    "city": "Blumenau",
    "state": "SC"
  }
}
```

**Resposta Esperada (Sandbox):**
```json
{
  "success": true,
  "order_id": 123,
  "type": "payment",
  "payment_method": "credit_card",
  "status": "pending" // ou "paid" se aprovado imediatamente
}
```

**OU se erro:**
```json
{
  "success": false,
  "message": "Mensagem clara do Asaas (ex: Cartão recusado, CPF inválido, etc.)"
}
```

---

### 2.2. Teste 2 – Plano Pré-pago (Anual) com PIX

**Endpoint:** `POST https://painel.imobsites.com.br/api/orders/create.php`

**Body JSON:**
```json
{
  "plan_code": "P02_ANUAL",
  "customer_name": "Teste Anual Pix",
  "customer_email": "teste+pix@example.com",
  "customer_whatsapp": "47999999999",
  "customer_cpf_cnpj": "12345678901",
  "payment_method": "pix"
}
```

**Resposta Esperada:**
```json
{
  "success": true,
  "order_id": 124,
  "type": "payment",
  "payment_method": "pix",
  "status": "pending",
  "pix_payload": "00020126...",
  "pix_qr_code_image": "data:image/png;base64,...",
  "message": "Pagamento Pix gerado. Escaneie o QR code ou copie o código Pix."
}
```

---

### 2.3. Teste 3 – Plano Mensal Recorrente com Cartão

**Endpoint:** `POST https://painel.imobsites.com.br/api/orders/create.php`

**Body JSON:**
```json
{
  "plan_code": "P01_MENSAL",
  "customer_name": "Teste Mensal Recorrente",
  "customer_email": "teste+mensal@example.com",
  "customer_whatsapp": "47999999999",
  "customer_cpf_cnpj": "12345678901",
  "payment_method": "credit_card",
  "payment_installments": 1,
  "card": {
    "holderName": "Teste Mensal",
    "number": "4111111111111111",
    "expiryMonth": "12",
    "expiryYear": "2030",
    "ccv": "123",
    "cpfCnpj": "12345678901",
    "email": "teste+mensal@example.com",
    "mobilePhone": "47999999999",
    "postalCode": "89000000",
    "addressNumber": "100",
    "address": "Rua Teste",
    "city": "Blumenau",
    "state": "SC"
  }
}
```

**Resposta Esperada:**
```json
{
  "success": true,
  "order_id": 125,
  "type": "subscription",
  "subscription_id": "sub_xxxxx",
  "status": "active" // ou "pending"
}
```

---

## 3. 🔍 O que Verificar nos Testes

### ✅ Sucesso
- [ ] `success: true` na resposta
- [ ] `order_id` retornado
- [ ] Dados específicos conforme método:
  - Cartão: `status` (paid/pending)
  - Pix: `pix_payload` e `pix_qr_code_image`
  - Boleto: `boleto_url` e `boleto_barcode`
  - Subscription: `subscription_id` e `status`

### ❌ Erros Comuns
- [ ] Se `success: false`, copiar **exatamente** o JSON de resposta
- [ ] Verificar logs do servidor (`error_log`)
- [ ] Verificar se o plano existe e está ativo
- [ ] Verificar se as credenciais do Asaas estão corretas

---

## 4. 🔒 Segurança

### ✅ Boas Práticas Implementadas
- [x] Não logar número de cartão completo (apenas últimos 4 dígitos se necessário)
- [x] Não logar CVV
- [x] Logs contêm apenas: `order_id`, `plan_code`, `status HTTP`, mensagens genéricas
- [x] HTTPS obrigatório em produção

### ⚠️ Verificar
- [ ] HTTPS ativo no painel e na landing
- [ ] `.htaccess` protegendo arquivos sensíveis
- [ ] `config/asaas.php` no `.gitignore`

---

## 5. 📝 Notas de Debug

### Campos Aceitos pelo Código
- **Cartão:** Aceita tanto `card` quanto `card_data` no payload
- **Número do cartão:** Aceita tanto `number` quanto `cardNumber`
- **Telefone:** Aceita tanto `phone` quanto `mobilePhone`

### Logs Importantes
- `[orders.create]` - Criação de pedidos
- `[asaas.billing]` - Processamento de billing
- `[asaas.billing.error]` - Erros no billing
- `[webhook.asaas]` - Processamento de webhooks

---

## 6. 🐛 Troubleshooting

### Erro: "Configuração do Asaas ausente: ASAAS_API_KEY"
- Verificar se `config/asaas.php` existe e tem `api_key` preenchida
- OU verificar se variáveis de ambiente estão definidas no servidor

### Erro: "Plano selecionado não foi encontrado"
- Verificar se o `plan_code` existe na tabela `plans`
- Verificar se `is_active = 1`

### Erro: "Dados do cartão de crédito incompletos"
- Verificar se todos os campos obrigatórios estão presentes:
  - `number` ou `cardNumber`
  - `holderName`
  - `expiryMonth`
  - `expiryYear`
  - `ccv`

### Erro do Asaas (ex: "Cartão recusado")
- Verificar se está usando cartão de teste válido do sandbox
- Verificar se CPF/CNPJ está no formato correto (apenas números)
- Verificar logs do Asaas no painel deles

---

## 7. 📞 Próximos Passos Após Testes

1. Se testes API passarem → Testar no site (Repo A)
2. Se testes API falharem → Copiar resposta JSON exata e ajustar código
3. Configurar webhook no painel Asaas apontando para:
   - `https://painel.imobsites.com.br/api/webhooks/asaas.php`
4. Testar webhook com eventos de pagamento confirmado

