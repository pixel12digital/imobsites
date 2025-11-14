# Como Fazer Pull em Produção Após Remover .htaccess do Git

## ✅ O que foi feito no repositório

1. Removido o `.htaccess` do tracking do Git (agora ele não será mais rastreado)
2. Commit e push realizados
3. O `.htaccess.example` está atualizado como referência

## 📋 Passos para Executar em Produção

Execute estes comandos **no servidor de produção** (via SSH ou terminal do cPanel):

```bash
# 1. Fazer backup de segurança (IMPORTANTE!)
cp .htaccess .htaccess.backup

# 2. Salvar temporariamente suas mudanças locais (credenciais do Asaas)
git stash push -m "Backup .htaccess produção com credenciais"

# 3. Fazer o pull do repositório
git pull origin master

# 4. Reaplicar suas configurações de produção
git stash pop
```

## ⚠️ Se o passo 4 gerar conflitos

Se aparecer conflitos ao fazer `git stash pop`, significa que o Git tentou mesclar mudanças. Nesse caso:

1. **Edite o `.htaccess` manualmente** e mantenha suas credenciais do Asaas (linhas 7-13)
2. Depois execute: `git stash drop` para remover o stash

## ✏️ Editar .htaccess Diretamente em Produção

Agora que o `.htaccess` não é mais rastreado pelo Git, você pode:

- ✅ Editar diretamente em produção sem problemas
- ✅ Fazer pull normalmente sem conflitos
- ✅ Manter suas credenciais locais seguras

**Importante:** Sempre mantenha suas credenciais do Asaas nas linhas 7-13 do `.htaccess`:

```apache
<IfModule mod_env.c>
    SetEnv ASAAS_API_KEY "sua_chave_aqui"
    SetEnv ASAAS_ENV "production"
    SetEnv ASAAS_API_BASE_URL "https://api.asaas.com/v3"
    SetEnv ASAAS_WEBHOOK_TOKEN "seu_token_aqui"
</IfModule>
```

## 🔄 Próximos Pulls

A partir de agora, você pode fazer `git pull` normalmente em produção sem problemas com o `.htaccess`!

