# Fluxo de Checkout → Onboarding do Cliente

## Endpoints expostos

- **POST** `api/orders/create.php`  
  Cria pedido pendente recebido da página de vendas. Retorna `order_id` e `payment_url`.  
  _Auth_: pendente de API key (TODO).

- **GET** `api/plans/public-list.php`  
  Lista planos ativos para consumo pela página de vendas. Retorna `features` já normalizados.

- **POST** `api/webhooks/asaas.php`  
  Webhook para notificações do gateway de pagamento. Confirma pedidos pagos e dispara onboarding.

- **GET/POST** `public/ativar-conta.php`  
  Tela pública para ativação da conta, definição de senha e aceite de termos.

## Serviços internos

- `master/includes/OrderService.php` – CRUD básico da tabela `orders`.
- `master/includes/TenantOnboardingService.php` – Orquestra criação de tenant/usuário após pagamento.
- `master/includes/PlanService.php` – CRUD do catálogo de planos (`plans.code` referenciado em `orders.plan_code`).

## Logs

- Eventos críticos são registrados via `error_log` com prefixos:
  - `[orders.create]` falhas na criação de pedido.
  - `[webhook.asaas]` processamento do webhook.
  - `[tenant_onboarding]` criação de tenants e envio de ativação.

## Pendências / TODOs

- Documentar monitoramento dos eventos do webhook em produção.
- Definir domínio base (`TENANT_DOMAIN_BASE`) e URL de ativação (`TENANT_ACTIVATION_BASE_URL`) em variáveis de ambiente.
- Integrar com serviço de e-mail oficial no envio do link de ativação.
- Adicionar mecanismo de autenticação (API key/IP allowlist) para `api/orders/create.php`.

## Integração Asaas (checkout hospedado)

- **Configuração**
  - Variáveis obrigatórias: `ASAAS_API_KEY`, `ASAAS_ENV` (`sandbox` ou `production`), `ASAAS_API_BASE_URL` (opcional, default conforme ambiente), `ASAAS_WEBHOOK_TOKEN`.
  - Essas variáveis são carregadas por `master/includes/AsaasConfig.php`. Valores ausentes geram log `[asaas.config]` e exceção.
  - Nunca versionar a API key; definir via `.env`/config seguro. Ajustar `ASAAS_WEBHOOK_TOKEN` no painel Asaas para enviar no header `asaas-access-token`.

- **Criação do pedido**
  - Endpoint `POST api/orders/create.php` cria o registro em `orders` e delega para `createPaymentOnAsaas()`.
  - Serviço `master/includes/AsaasPaymentService.php`:
    - Garante cliente no Asaas (`GET /v3/customers?email=` → `POST /v3/customers`).
    - Cria cobrança avulsa via `POST /v3/payments`, usando:
      - `value` = `orders.total_amount` (definido pelo plano).
      - `description` = `ImobSites - {nome_plano} - Pedido #{order_id}`.
      - `externalReference` = `order:{order_id}`.
      - `billingType` = `UNDEFINED` (formas configuradas na conta).
    - Retorna `id` (salvo em `orders.provider_payment_id`) e URL de checkout (`invoiceUrl`/`paymentUrl`, salvo em `orders.payment_url`).
    - Logs de erro usam `[asaas.payment.error]` / `[asaas.http]`.

- **Webhook**
  - Endpoint `POST api/webhooks/asaas.php`.
  - Valida `asaas-access-token` contra `ASAAS_WEBHOOK_TOKEN`; rejeita 403 quando divergente.
  - Registra payload em `[webhook.asaas.payload]` (limite 2000 chars) para auditoria.
  - Usa `payment.externalReference` (`order:{id}`) para localizar o pedido; fallback em `payment.id`.
  - Eventos/Status considerados pagos: `PAYMENT_CONFIRMED`, `PAYMENT_RECEIVED`, `PAYMENT_RECEIVED_IN_CASH`, `PAYMENT_RECEIVED_AFTER_DUE_DATE` ou status `RECEIVED/CONFIRMED/PAID`.
  - Ao confirmar pagamento:
    - `markOrderAsPaid()` atualiza `orders` (`status = paid`, `paid_at`).
    - `onPaidOrderCreateTenantAndSendActivation()` cria tenant e dispara e-mail de ativação (idempotente).
  - Logs de falha: `[webhook.asaas] ...`.

- **Segurança e boas práticas**
  - Não expor `ASAAS_API_KEY` no front-end; somente backend acessa.
  - Monitorar erros `[asaas.http]` (requisições 4xx/5xx) e `[orders.create]`.
  - Preferir ambiente `sandbox` (`https://api-sandbox.asaas.com/v3`) para testes.
  - Após validação, ajustar `ASAAS_ENV=production` e atualizar tokens no painel Asaas.


