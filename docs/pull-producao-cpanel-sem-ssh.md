# Como Fazer Pull em Produção via cPanel (SEM SSH)

## 🎯 Duas Soluções Disponíveis

Como você não tem acesso SSH, temos **duas opções** para resolver o problema do `.htaccess`:

### Opção 1: Script Automático (Mais Fácil) ⭐
### Opção 2: Manual via File Manager

---

## 🚀 OPÇÃO 1: Script Automático (Recomendado)

### Passo 1: Executar o Script

1. Acesse via navegador: `https://painel.imobsites.com.br/scripts/fix_htaccess_git.php`
2. O script vai automaticamente:
   - Fazer backup do `.htaccess`
   - Renomear temporariamente para `.htaccess.temp`
3. Siga as instruções na tela

### Passo 2: Fazer Pull no cPanel

1. Vá para **Git Version Control** no cPanel
2. Aba **"Pull or Deploy"**
3. Clique em **"Update from Remote"**
4. Deve funcionar agora! ✅

### Passo 3: Restaurar .htaccess

1. Volte para o script: `https://painel.imobsites.com.br/scripts/fix_htaccess_git.php`
2. Clique no botão **"Restaurar .htaccess"**
3. **IMPORTANTE:** Delete o arquivo `scripts/fix_htaccess_git.php` após usar!

---

## 📋 OPÇÃO 2: Manual via File Manager

### Passo 1: Fazer Backup do .htaccess

1. Acesse o **File Manager** do cPanel
2. Navegue até a raiz do seu site: `/home/imobsitescom/painel.imobsites.com.br`
3. Localize o arquivo `.htaccess`
4. Clique com o botão direito no `.htaccess` → **Rename**
5. Renomeie para: `.htaccess.backup` (isso preserva suas credenciais)

### Passo 2: Fazer Pull via Git Version Control

1. Volte para **Git Version Control** no cPanel
2. Vá na aba **"Pull or Deploy"**
3. Clique no botão **"Update from Remote"**
4. Agora deve funcionar! ✅

### Passo 3: Recriar o .htaccess com suas Credenciais

1. Volte para o **File Manager**
2. Você verá o arquivo `.htaccess.backup` (seu backup com credenciais)
3. Abra o arquivo `.htaccess.example` (se existir) ou crie um novo `.htaccess`
4. Copie o conteúdo do `.htaccess.example` para um novo `.htaccess`
5. **IMPORTANTE:** Edite as linhas 7-13 e adicione suas credenciais reais do Asaas:

```apache
<IfModule mod_env.c>
    SetEnv ASAAS_API_KEY "sua_chave_de_api_aqui"
    SetEnv ASAAS_ENV "production"
    SetEnv ASAAS_API_BASE_URL "https://api.asaas.com/v3"
    SetEnv ASAAS_WEBHOOK_TOKEN "seu_webhook_token_aqui"
</IfModule>
```

6. Salve o arquivo `.htaccess`

### Passo 4: Verificar se Funcionou

1. Teste se o site está funcionando normalmente
2. Você pode manter o `.htaccess.backup` como backup de segurança

## 🔄 Para Próximos Pulls

A partir de agora, quando precisar fazer pull:

1. **Opção A (Recomendada):** Simplesmente faça o pull normalmente via cPanel. Como o `.htaccess` não é mais rastreado pelo Git, não haverá conflitos.

2. **Opção B (Se ainda der erro):** 
   - Renomeie `.htaccess` para `.htaccess.temp`
   - Faça o pull
   - Renomeie de volta para `.htaccess`

## ⚠️ Importante

- **NUNCA** faça commit do `.htaccess` com credenciais reais
- Sempre mantenha um backup do `.htaccess` com suas credenciais
- O arquivo `.htaccess.example` no repositório serve como template

## 📝 Nota sobre Credenciais

Se você não lembrar suas credenciais do Asaas, elas estão no arquivo `.htaccess.backup` que você criou no Passo 1. Abra esse arquivo no File Manager para copiar as credenciais.

