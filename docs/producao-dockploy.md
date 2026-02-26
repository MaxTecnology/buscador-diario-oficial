# Deploy em Produção com Dockploy (GitHub + Autodeploy)

Data base: 2026-02-23

## Objetivo
Subir este projeto Laravel em produção usando:

- VPS com Dockploy
- repositório no GitHub
- autodeploy no fluxo `commit -> push -> deploy`

## Resumo Rápido
Este repositório já está pronto para desenvolvimento, mas para produção precisa de:

1. stack de produção (web + worker + db + redis) + MinIO externo;
2. imagem de produção (sem Sail);
3. variáveis de ambiente corretas;
4. política de deploy segura (rodar migration, evitar seed automático).

Arquivos adicionados para isso:

- `Dockerfile.prod`
- `docker-compose.prod.yml`
- `docker/production/entrypoint.sh`
- `.dockerignore`

## DNS: Quantos Apontamentos Você Precisa?

### Mínimo para começar (recomendado)
`1` apontamento.

Exemplo:
- `app.seudominio.com` -> `A` -> `IP_DA_VPS`

Com isso você já atende:
- painel `/admin`
- API `/api`
- rota de arquivos autenticada `/diarios/{id}/arquivo`

Tudo no mesmo domínio.

### Configuração comum (mais amigável)
`2` apontamentos.

- `@` (raiz) -> `A` -> `IP_DA_VPS`
- `www` -> `CNAME` -> `@`

Obs.: você pode usar só `app.seudominio.com` e pular o `www`.

### Opcionais (não recomendo expor agora)
Cada item abaixo adiciona `+1` apontamento:

- `storage.g2asolucoescontabeis.com.br` (console do MinIO)
- `horizon.g2asolucoescontabeis.com.br` (se você decidir expor Horizon depois)
- `api.g2asolucoescontabeis.com.br` (se quiser separar API do painel em outro host)

## Recomendação de DNS para seu caso (agora)
Faça **1 apontamento** já:

- `diario.g2asolucoescontabeis.com.br` -> `A` -> `IP_DA_VPS`

Os outros (`storage` e `horizon`) podem ficar reservados para uso futuro, mas não precisam ser expostos no Dockploy agora.

Se quiser um domínio alternativo de acesso (opcional), faça **2**:

- `diario.g2asolucoescontabeis.com.br` -> `A` -> `IP_DA_VPS`
- `www.g2asolucoescontabeis.com.br` -> `CNAME` -> `diario.g2asolucoescontabeis.com.br`

## Arquitetura de Produção (proposta)

Serviços no `docker-compose.prod.yml` (app principal):

- `app` (web)
- `worker` (fila)
- `scheduler` (agendador; pode ficar ativo mesmo sem tarefas críticas)
- `mysql`
- `redis`

MinIO fica em **projeto/stack separado** no Dockploy (recomendado), para:
- independência de deploy do app
- reuso com `n8n`
- manutenção e backup isolados

## Pontos Críticos de Produção (já mapeados)

### 1. Fila / jobs pesados
O projeto processa PDFs em job (`ProcessarPdfJob`) com `timeout` alto.

Risco se mal configurado:
- reprocessamento duplicado por `retry_after` baixo.

Ajuste recomendado:
- `QUEUE_CONNECTION=redis`
- `DB_QUEUE_RETRY_AFTER=1200`
- `REDIS_QUEUE_RETRY_AFTER=1200`

### 2. Reverse proxy (Dockploy)
Dockploy normalmente fica atrás de proxy reverso.

Já ajustado no projeto:
- `bootstrap/app.php` agora usa `trustProxies`.

### 3. Rotas de docs
`/docs` (Scribe) deve ficar desligado em produção.

Usar:
- `SCRIBE_ADD_ROUTES=false`

### 4. Seed em produção
Nao rode `db:seed` em todo deploy.

Motivo:
- seus seeders usam `updateOrCreate` e podem sobrescrever configurações e usuários.

Regra segura:
- em autodeploy: rodar só `migrate --force`
- seed: apenas bootstrap inicial e de forma manual

## Variáveis de Ambiente (Dockploy)

### Obrigatórias (mínimo)
- `APP_NAME`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY`
- `APP_URL`
- `TRUSTED_PROXIES=*`

- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_ROOT_PASSWORD`

- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_DOMAIN` (ex.: `.seudominio.com` se necessário)

- `DIARIOS_KEY`
- `DIARIOS_SECRET`
- `DIARIOS_BUCKET=diarios`
- `DIARIOS_ENDPOINT=https://s3.seudominio.com` (ou endpoint privado do seu MinIO)
- `DIARIOS_USE_PATH_STYLE=true`

### Recomendadas
- `APP_LOCALE=pt_BR`
- `APP_FALLBACK_LOCALE=pt_BR`
- `LOG_LEVEL=info`
- `DB_QUEUE_RETRY_AFTER=1200`
- `REDIS_QUEUE_RETRY_AFTER=1200`
- `DIARIOS_URL` (se usar URL pública direta do bucket)
- `DIARIOS_PUBLIC_ENDPOINT` (se endpoint interno do MinIO diferir do público)
- `SCRIBE_ADD_ROUTES=false`

## MinIO em Projeto Separado (Dockploy)

### Objetivo
Usar um MinIO próprio em produção, fora da stack principal do Laravel, para:
- uploads do Laravel
- integração com `n8n`
- downloads automáticos via site / fluxos externos

### Exposição recomendada
- Expor **somente a API S3** (`porta 9000`) com HTTPS:
  - `s3.g2asolucoescontabeis.com.br` -> serviço `minio` -> porta `9000`
- Não deixar bucket público por padrão.
- Não expor console (`9001`) sem necessidade.

Se quiser console web depois:
- usar outro domínio (ex.: `minio-console.g2asolucoescontabeis.com.br`)
- proteger com autenticação forte

### O que criar no MinIO (obrigatório)
1. Bucket `diarios`
2. Bucket **privado**
3. Usuário/credencial para a aplicação Laravel (não usar root)
4. Usuário/credencial para o `n8n` (pode ser outro usuário)

### Exemplo de criação com `mc` (CLI do MinIO)
Exemplo a partir de uma máquina/terminal com `mc` instalado (ajuste host e credenciais root do MinIO):

```bash
mc alias set prod https://s3.g2asolucoescontabeis.com.br MINIO_ROOT_USER MINIO_ROOT_PASSWORD

# cria bucket (idempotente)
mc mb -p prod/diarios || true

# mantém privado
mc anonymous set none prod/diarios

# cria policy para app/n8n (bucket privado com leitura/escrita)
cat > diarios-rw.json <<'JSON'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": ["arn:aws:s3:::diarios"]
    },
    {
      "Effect": "Allow",
      "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],
      "Resource": ["arn:aws:s3:::diarios/*"]
    }
  ]
}
JSON

mc admin policy create prod diarios-rw diarios-rw.json || true

# usuário Laravel
mc admin user add prod laravel-diarios SUA_SENHA_FORTE
mc admin policy attach prod diarios-rw --user laravel-diarios

# usuário n8n (opcional separado)
mc admin user add prod n8n-diarios SUA_SENHA_FORTE
mc admin policy attach prod diarios-rw --user n8n-diarios
```

### Permissões recomendadas (mínimo)
Para Laravel e `n8n`, conceder acesso ao bucket `diarios`:
- `s3:ListBucket`
- `s3:GetObject`
- `s3:PutObject`
- `s3:DeleteObject` (se você quiser exclusão pelo app/fluxo)

### Variáveis do Laravel (stack principal)
No Dockploy do Laravel, usar as credenciais do usuário de aplicação do MinIO:

```dotenv
FILESYSTEM_DISK=diarios
DIARIOS_DISK=diarios
DIARIOS_KEY=SEU_ACCESS_KEY_APP
DIARIOS_SECRET=SEU_SECRET_KEY_APP
DIARIOS_REGION=us-east-1
DIARIOS_BUCKET=diarios
DIARIOS_ENDPOINT=https://s3.g2asolucoescontabeis.com.br
DIARIOS_PUBLIC_ENDPOINT=https://s3.g2asolucoescontabeis.com.br
DIARIOS_USE_PATH_STYLE=true
```

Observações:
- `DIARIOS_ENDPOINT` não deve mais apontar para `http://minio:9000` na stack do app.
- `DIARIOS_PUBLIC_ENDPOINT` ajuda quando o app precisa gerar URL usando o host público.
- Mantenha o bucket privado e use rota autenticada do app ou URL assinada (presigned) quando necessário.

### Variáveis do n8n (S3 / MinIO)
Na credencial S3 do `n8n`:
- Endpoint: `https://s3.g2asolucoescontabeis.com.br`
- Region: `us-east-1`
- Bucket: `diarios`
- Force Path Style: `true`
- Access Key / Secret: credencial dedicada do `n8n`

### Checklist de validação do MinIO (produção)
- [ ] `https://s3.g2asolucoescontabeis.com.br/minio/health/ready` responde
- [ ] bucket `diarios` criado
- [ ] bucket privado (sem acesso anônimo)
- [ ] Laravel consegue uploadar arquivo
- [ ] `n8n` consegue listar/gravar no bucket
- [ ] download pelo app continua funcionando (rota autenticada / presigned)

### Email (produção)
Se for enviar notificações por email:

- `MAIL_MAILER=smtp`
- `MAIL_SCHEME` (`smtp` para 587 / `smtps` para 465 / vazio)
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

Se ainda não tiver SMTP:
- use `MAIL_MAILER=log` temporariamente

### Email (Gmail) pronto para seu caso
Você pode usar `maximizebot@gmail.com` em produção para volume baixo/moderado. Configure no Dockploy assim e preencha só a senha de app:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=maximizebot@gmail.com
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=maximizebot@gmail.com
MAIL_FROM_NAME="G2A Diário"
MAIL_EHLO_DOMAIN=diario.g2asolucoescontabeis.com.br
```

Notas:
- `MAIL_PASSWORD` deve ser a senha de app do Google (16 caracteres); se copiar com espaços, coloque entre aspas ou remova os espaços.
- Para reduzir problemas de envio, mantenha `MAIL_FROM_ADDRESS` igual ao Gmail autenticado.
- Se o volume crescer, migre para um serviço transacional (SES, Postmark, Resend, etc.).

## Configuração no Dockploy (fluxo recomendado)

### 1. Criar app via GitHub
- Conectar repositório no Dockploy
- Branch: `main` (ou branch de produção)
- Habilitar autodeploy para pushes nessa branch

### 2. Escolher stack
Use:
- `docker-compose.prod.yml`

### 3. Definir domínio
Exemplo:
- `diario.g2asolucoescontabeis.com.br`

### 4. Configurar env vars no Dockploy
Preencher todas as obrigatórias listadas acima.

### 5. Post-deploy command (importante)
Use algo simples e seguro:

```bash
php artisan migrate --force && php artisan optimize:clear && php artisan config:cache && php artisan view:cache
```

### 6. Não usar `route:cache` por enquanto
O projeto ainda tem rotas com closure (`routes/web.php`), então `route:cache` pode falhar.

Quando refatorarmos essas rotas para controllers, aí sim pode ativar:
- `php artisan route:cache`

## Primeiro Deploy (ordem sugerida)

1. Configurar DNS (1 apontamento A já resolve)
2. Criar app no Dockploy com `docker-compose.prod.yml`
3. Preencher env vars
4. Deploy manual inicial
5. Validar:
   - `/up`
   - `/admin`
   - login
   - upload de PDF
   - fila processando (`worker`)
6. Só depois ativar autodeploy em produção

## Estratégia de Autodeploy (pragmática)

Fluxo que você descreveu:
- desenvolver localmente
- commit/push
- autodeploy

Isso funciona, mas recomendo mínimo de disciplina:

1. `main` protegida (mesmo que só você use)
2. deploy automático somente após validar localmente:
   - login admin
   - upload PDF
   - processamento em fila
3. migrations destrutivas sempre planejadas

## Checklist de Produção (curto)

- [ ] DNS apontando para VPS
- [ ] HTTPS ativo no domínio (Dockploy)
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` definido
- [ ] `QUEUE_CONNECTION=redis`
- [ ] `worker` ativo
- [ ] MinIO externo ativo + bucket `diarios` criado
- [ ] `SCRIBE_ADD_ROUTES=false`
- [ ] post-deploy rodando `migrate --force`

## Próximo passo técnico recomendado
Antes do deploy final, validar localmente com a stack de produção:

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
```

Isso reduz surpresa no Dockploy.
