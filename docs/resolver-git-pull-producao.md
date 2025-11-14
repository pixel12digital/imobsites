# Como Resolver Erro de Git Pull em Produção

## Problema
Ao tentar fazer `git pull` em produção, o Git bloqueia a operação porque há mudanças locais não commitadas no arquivo `.htaccess` que seriam sobrescritas pelo merge.

**Erro típico:**
```
error: Your local changes to the following files would be overwritten by merge: .htaccess
Please commit your changes or stash them before you merge. Aborting
```

## Solução Recomendada: Usar Git Stash

O `git stash` salva temporariamente suas mudanças locais, permite fazer o pull, e depois você pode reaplicar as mudanças.

### Passo a Passo

#### 1. Fazer backup do `.htaccess` atual (segurança extra)
```bash
cp .htaccess .htaccess.backup
```

#### 2. Salvar as mudanças locais temporariamente (stash)
```bash
git stash push -m "Backup .htaccess produção antes do pull"
```

#### 3. Fazer o pull do repositório remoto
```bash
git pull origin master
```

#### 4. Reaplicar as mudanças locais do stash
```bash
git stash pop
```

Se houver conflitos no passo 4, você precisará resolver manualmente. Nesse caso:
- O Git mostrará os conflitos
- Você precisará editar o `.htaccess` para manter suas credenciais de produção
- Depois execute: `git stash drop` para remover o stash

## Alternativa: Forçar o Pull (CUIDADO!)

⚠️ **ATENÇÃO:** Esta opção descarta as mudanças locais. Use apenas se você tiver certeza de que as mudanças locais não são importantes.

```bash
# Descartar mudanças locais e fazer pull
git reset --hard HEAD
git pull origin master
```

Depois disso, você precisará recriar o `.htaccess` com suas credenciais de produção baseado no `.htaccess.example`.

## Verificar Status

Para verificar o status atual do repositório:
```bash
git status
```

Para ver as diferenças entre sua versão local e a do repositório:
```bash
git diff .htaccess
```

## Prevenção Futura

Para evitar esse problema no futuro:

1. **Certifique-se de que `.htaccess` está no `.gitignore`** (já está ✓)
2. **Nunca faça commit do `.htaccess` com credenciais reais**
3. **Use sempre o `.htaccess.example` como base** e copie para `.htaccess` localmente
4. **Em produção, sempre use `git stash` antes de fazer pull** se houver mudanças locais

## Nota sobre cPanel

Se você estiver usando o Git Version Control do cPanel:

1. Use o terminal SSH do cPanel (se disponível) para executar os comandos acima
2. Ou use o File Manager para fazer backup do `.htaccess` antes de tentar o pull novamente
3. O cPanel pode ter limitações - nesse caso, use SSH diretamente

