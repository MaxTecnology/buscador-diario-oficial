# Automacao n8n -> MinIO -> Aplicacao (Webhook de Ingestao)

Data: 2026-03-02

## Status de implementação no projeto
Implementado:
- endpoint público `POST /api/v1/ingest/diarios`;
- validação de payload (`source`, `external_id`, `estado`, `data_diario`, `object_key`, `sha256`, `size_bytes`);
- validação de assinatura HMAC (`X-Timestamp` + `X-Signature`) com janela de tempo;
- idempotência por `X-Idempotency-Key`;
- deduplicação por `source + external_id` e por `hash_sha256`;
- criação do diário e envio para `ProcessarPdfJob` (mesma lógica de fila do fluxo manual);
- tabela de controle `ingestao_diario_logs` para auditoria operacional;
- tela no painel: `Admin > Logs de Ingestão`.

Variáveis novas:
- `INGEST_WEBHOOK_SECRET`
- `INGEST_REQUIRE_SIGNATURE`
- `INGEST_SIGNATURE_TOLERANCE_SECONDS`
- `INGEST_VERIFY_OBJECT_HASH`
- `INGEST_NOTIFY_ON_ENQUEUE`
- `INGEST_SYSTEM_USER_EMAIL`
- `INGEST_SYSTEM_USER_ID`
- `INGEST_QUEUE`

## Teste rápido do webhook (local)
1. Defina `INGEST_WEBHOOK_SECRET` no `.env`.
2. Gere assinatura com `timestamp + "." + body` usando HMAC-SHA256.
3. Envie com `X-Idempotency-Key`, `X-Timestamp` e `X-Signature`.

Exemplo de assinatura (pseudo):
- `signature = sha256_hmac(secret, timestamp + "." + raw_json_body)`
- Header: `X-Signature: sha256=<signature>`

## Objetivo
Padronizar a ingestao automatica dos PDFs do Diario Oficial usando `n8n`, salvando primeiro no MinIO e depois disparando processamento na aplicacao via webhook, sem upload binario direto no endpoint.

## Estado atual (fluxo em `docs/diario-oficial.json`)
Fluxo existente:
1. `Manual Trigger`
2. `Buscar Edicoes Disponiveis` (HTTP GET)
3. `Processar Edicoes` (Code)
4. `Download PDF` (HTTP file)

Gap atual:
- ainda nao salva o PDF na bucket;
- ainda nao chama a aplicacao para criar/enfileirar diario;
- sem idempotencia formal entre n8n e app;
- sem contrato de integracao documentado.

## Arquitetura recomendada (profissional)
Modelo: **storage-first**

1. n8n baixa o PDF da fonte oficial.
2. n8n calcula metadados do arquivo (sha256, tamanho, nome, data, estado, external_id).
3. n8n salva PDF no MinIO (bucket `diarios`) em chave padronizada.
4. n8n chama webhook da aplicacao com JSON de metadados (sem binario).
5. aplicacao valida assinatura + idempotencia e cria registro em `diarios`.
6. aplicacao enfileira `ProcessarPdfJob` exatamente como no fluxo manual.
7. worker processa PDF e gera ocorrencias/notificacoes.

## Por que este modelo
- evita payload grande em webhook;
- reduz timeout e falhas por rede;
- facilita retry no n8n sem duplicar processamento;
- permite reprocessamento do mesmo arquivo com rastreabilidade;
- centraliza regras de negocio no Laravel.

## Padrao de armazenamento no MinIO
Usar o mesmo estilo da aplicacao manual (organizado por estado e data):

`diarios/{UF}/{YYYY-MM-DD}/{UF}_{YYYYMMDD}_{edicaoId}_{sha8}.pdf`

Exemplo:
`diarios/AL/2026-03-02/AL_20260302_51234_a1b2c3d4.pdf`

## Contrato do webhook (proposta)
Endpoint sugerido:
- `POST /api/v1/ingest/diarios`

Headers obrigatorios:
- `Content-Type: application/json`
- `X-Idempotency-Key: <uuid>`
- `X-Timestamp: <unix-epoch-seconds>`
- `X-Signature: sha256=<hmac_hex>`

Assinatura HMAC (recomendado):
- chave secreta compartilhada: `INGEST_WEBHOOK_SECRET`
- string assinada: `timestamp + "." + raw_body`
- validar janela de tempo (ex.: 5 min)

Payload JSON minimo:
```json
{
  "source": "n8n-diario-oficial-al",
  "external_id": "al-51234-2026-03-02",
  "estado": "AL",
  "data_diario": "2026-03-02",
  "nome_arquivo": "diario_oficial_al_2026-03-02_n123_id51234.pdf",
  "bucket": "diarios",
  "object_key": "diarios/AL/2026-03-02/AL_20260302_51234_a1b2c3d4.pdf",
  "sha256": "<hash-hex>",
  "size_bytes": 19923456,
  "download_url_origem": "https://diario.imprensaoficial.al.gov.br/apinova/api/editions/downloadPdf/51234",
  "metadata": {
    "edition_id": 51234,
    "edition_number": "123",
    "edition_type": "Diario Oficial",
    "is_suplemento": false,
    "publicado_em": "2026-03-02T00:00:00-03:00"
  }
}
```

Respostas esperadas:
- `202 Accepted`: criado e enfileirado
- `200 OK`: duplicado detectado (ja existente)
- `401/403`: assinatura invalida
- `422`: payload invalido
- `500`: erro interno

## Regras de idempotencia
A aplicacao deve impedir duplicidade por:
1. `hash_sha256` (ja existe unique no banco); e
2. opcionalmente `source + external_id` (melhora rastreabilidade do integrador).

Comportamento recomendado:
- se receber arquivo ja conhecido: retornar `200` com `duplicate=true` e `diario_id` existente;
- nao lancar erro generico para duplicidade.

## Como o webhook conversa com o processamento atual
No backend, o webhook deve reaproveitar a logica do cadastro manual:
- preencher `diarios` com `nome_arquivo`, `estado`, `data_diario`, `hash_sha256`, `caminho_arquivo`, `tamanho_arquivo`, `status`;
- setar `status = processando`;
- disparar `ProcessarPdfJob` com opcoes de processamento inicial.

Ou seja: **mesmo pipeline manual**, apenas mudando a origem do cadastro.

## Desenho do fluxo n8n (versao final)
Fluxo atual + novos nós:

1. `Manual/Cron Trigger`
2. `Buscar Edicoes Disponiveis`
3. `Processar Edicoes` (gera metadados por item)
4. `Download PDF`
5. `Code: Calcular SHA256 + Size + ObjectKey`
6. `S3 (MinIO): Upload Object`
7. `HTTP Request: Webhook Laravel`
8. `IF Sucesso/Falha` + log/alerta

### Node 5 (Code) - responsabilidades
- ler binario do node `Download PDF`;
- calcular `sha256`;
- calcular `size_bytes`;
- montar `object_key` padronizado;
- montar `idempotency_key`;
- preparar payload final para webhook.

### Node 6 (S3 upload)
- credencial S3 do MinIO (usuario dedicado ao n8n);
- bucket: `diarios`;
- key: `{{$json.object_key}}`;
- binary property: `data`.

### Node 7 (webhook app)
- URL: `https://diario.g2asolucoescontabeis.com.br/api/v1/ingest/diarios`
- metodo: `POST`
- body: JSON (metadados)
- headers: `X-Idempotency-Key`, `X-Timestamp`, `X-Signature`
- timeout: 30s
- retry com backoff no n8n para 5xx/timeout.

## Observabilidade minima
Registrar em ambos os lados:
- `correlation_id` (idempotency key)
- `external_id`
- `sha256`
- `object_key`
- status da etapa (`download`, `upload`, `ingest`, `queue`, `processamento`)

Recomendado:
- criar dashboard no n8n para taxa de sucesso;
- no Laravel, log estruturado por ingestao.

## Politica de erro e retry
- Erro no download: retry n8n.
- Erro no upload MinIO: retry n8n.
- Erro no webhook 5xx: retry n8n com backoff.
- Erro 4xx no webhook: parar e enviar alerta (erro de contrato/config).
- Duplicado: tratar como sucesso tecnico (nao reprocessar automaticamente).

## Checklist de implementacao
1. Criar endpoint de ingestao no Laravel (`/api/v1/ingest/diarios`).
2. Implementar validacao de assinatura HMAC.
3. Implementar idempotencia (hash + external_id).
4. Reusar pipeline `ProcessarPdfJob`.
5. Ajustar fluxo n8n com `S3 Upload` e `Webhook`.
6. Testar ponta a ponta com 1 PDF real.
7. Testar duplicidade (mesmo hash).
8. Testar falha de webhook e retry n8n.
9. Documentar variaveis em producao (`INGEST_WEBHOOK_SECRET`, credenciais S3 n8n).

## Fora de escopo nesta etapa
- implementacao do endpoint e migration adicional;
- tela de monitoramento de ingestao no painel;
- callbacks ativos do app para n8n.

## Decisoes tomadas
- nao enviar binario no webhook;
- usar MinIO como origem unica do arquivo;
- processamento acionado por webhook com idempotencia.
