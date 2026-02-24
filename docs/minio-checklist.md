# Checklist de Migração para MinIO (Storage de Diários/PDFs)

## Objetivo
Usar MinIO (S3 compatível) no Docker para armazenar PDFs e textos extraídos de diários, permitindo embed seguro dos arquivos na aplicação.

## Infra (Docker)
- [x] Adicionar serviços `minio` e `minio-console` no `docker-compose.yml`, com volumes persistentes.
- [x] Expor portas 9000 (API) e 9001 (console) no host.
- [x] Definir variáveis no `.env`/`.env.example`: `MINIO_ROOT_USER`, `MINIO_ROOT_PASSWORD`, `MINIO_BUCKET=diarios`.
- [x] Criar bucket `diarios` na inicialização (entrypoint ou script).

## Config Laravel (filesystems)
- [x] Criar disk `diarios` em `config/filesystems.php` com driver `s3`, usando `AWS_ENDPOINT=http://minio:9000` e `AWS_USE_PATH_STYLE_ENDPOINT=true`.
- [x] Variáveis `.env` e `.env.example`: `FILESYSTEM_DISK=local` (para outros usos) e `DIARIOS_DISK=diarios`, `AWS_ACCESS_KEY_ID/SECRET`, `AWS_DEFAULT_REGION=us-east-1`, `AWS_BUCKET=diarios`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT=true`.
- [x] Presigned habilitado no código (fallback para presign caso bucket seja privado).

## Código (armazenamento/links)
- [x] Substituir uso de `disk('public')` para diários/textos por `disk(config('filesystems.diarios_disk', 'diarios'))`:
  - Upload (CreateDiario / PdfProcessingService / DiarioProcessingService / API DiarioController).
  - Links/embeds: listar diários, ocorrências, modal de detalhes, e-mails (EmpresaEncontradaMail), relatórios.
  - Exclusão de arquivos (model Diario boot deleting).
- [x] Garantir que textos extraídos (`caminho_texto_completo`) também fiquem no mesmo disk.
- [x] Para parser (PdfProcessorService), baixar o PDF para temp local se o disk não for local, processar, e limpar temp.

## UI/Embed
- [x] Gerar URL pública ou presigned do disk para embeds (iframe) e links (fallback presigned se bucket privado).
- [ ] Ajustar tempo de validade do presigned se necessário (hoje 30 min).
- [x] Ajustar modal de ocorrências para usar a URL do novo disk.
- [x] Rota interna `/diarios/{id}/arquivo` servindo PDF inline (ou download com `?download=1`), funcionando com bucket privado sem depender de URL pública.

## Segurança/CORS
- [ ] Configurar CORS no MinIO para permitir GET do origin do app (frontend/admin).
- [x] Bucket público para embed simples (definido via `mc anonymous set download`). Avaliar privacidade/presign se necessário.

## Jobs/Fila
- [x] Jobs de processamento leem o PDF via disk configurado (baixam para temp se não local).
- [ ] Se usar presign futuramente, workers seguem com Storage direto (sem URL pública).

## Testes/Validação
- [x] Subir stack com MinIO e criar bucket.
- [x] Upload de diário → arquivo salvo no MinIO (validar manualmente).
- [ ] Processar diário → parser lê via temp file; ocorrências criadas (validar).
- [x] Modal de ocorrências abre PDF na página correta (validar).
- [x] Delete de diário remove arquivo do MinIO (validar).
- [x] Download/visualização do PDF pelo app (`/diarios/{id}/arquivo`) com bucket privado.
- [ ] E-mail de ocorrência anexa/usa link correto (conforme escolha público/presign) (validar).

## Rollout
- [x] Atualizar `docs/DOCUMENTACAO.md` com instruções de ambiente/variáveis.
- [ ] Comunicar mudança de storage (limpeza do storage antigo se necessário).

## Produção (MinIO separado / Dockploy)
- [ ] Subir MinIO em projeto/stack separado do Laravel (recomendado).
- [ ] Expor somente API S3 (`9000`) com HTTPS (ex.: `s3.seudominio.com`).
- [ ] Manter bucket `diarios` privado (sem acesso anônimo).
- [ ] Criar credencial dedicada para Laravel (não usar root).
- [ ] Criar credencial dedicada para `n8n` (reuso controlado).
- [ ] Configurar Laravel com `DIARIOS_ENDPOINT` apontando para o host público/privado do MinIO externo.
- [ ] Validar upload no app + leitura/gravação no `n8n`.



### Tornar o bucket diarios privado (revogar acesso anônimo):
sail exec minio sh -c "\
  mc alias set local http://minio:9000 ${MINIO_ROOT_USER:-admin} ${MINIO_ROOT_PASSWORD:-admin123} && \
  mc anonymous set none local/diarios"

### Voltar para público (download anônimo):
sail exec minio sh -c "\
  mc alias set local http://minio:9000 ${MINIO_ROOT_USER:-admin} ${MINIO_ROOT_PASSWORD:-admin123} && \
  mc anonymous set download local/diarios"
