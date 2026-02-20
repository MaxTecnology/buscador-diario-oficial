# Matriz de Rotas para Limpeza

Data base do inventario: 2026-02-20

## Objetivo
Definir, por grupo de rotas, o que:
1. manter como core;
2. endurecer/proteger;
3. desabilitar gradualmente.

## Resumo Atual
- Total de rotas mapeadas: 127
- Principais grupos:
  - Backoffice Filament: `/admin/*`
  - API de negocio: `/api/*`
  - Arquivo de diario: `/diarios/{diario}/arquivo`
  - Operacionais/framework: `/horizon/*`, `/livewire/*`, `/sanctum/*`, `/up`
  - Documentacao: `/docs`, `/docs.openapi`, `/docs.postman`

## Grupo 1 - Core (manter)
Estas rotas sustentam operacao e negocio.

1. Filament backoffice
- Prefixo: `/admin/*`
- Status: `manter`
- Acao: manter como painel principal interno.

2. API de negocio
- Prefixo: `/api/auth/*`, `/api/empresas/*`, `/api/diarios/*`, `/api/ocorrencias/*`, `/api/users/*`
- Status: `manter`
- Acao: manter e consolidar contrato.

3. Arquivo de diario
- Rota: `/diarios/{diario}/arquivo`
- Status: `manter`
- Acao: manter (ja protegida por auth).

## Grupo 2 - Proteger/Restringir (nao desabilitar ainda)

1. Rotas de configuracao e monitoramento API
- Prefixo: `/api/configs/*`, `/api/system/*`, `/api/relatorios/*`
- Status: `proteger`
- Acao: revisar middleware/roles e limitar exposicao para perfis internos.

2. Horizon
- Prefixo: `/horizon/*`
- Status: `proteger`
- Acao: garantir acesso apenas interno/admin em ambiente de producao.

3. Storage local route
- Rota: `/storage/{path}`
- Status: `proteger`
- Acao: validar necessidade real em producao e politica de acesso.

## Grupo 3 - Candidatas a Desabilitacao Gradual
Desabilitar somente apos confirmar que nao ha consumo ativo.

1. Scribe publico
- Rotas: `/docs`, `/docs.openapi`, `/docs.postman`
- Status: `candidata desabilitacao`
- Acao recomendada: primeiro restringir para ambiente local/staging ou auth admin; depois remover publico.

2. Endpoints de API redundantes por dominio
- Exemplos candidatos (avaliar uso): rotas de `stats`, `search`, `export`, `test-search` em cada modulo.
- Status: `candidata consolidacao`
- Acao recomendada: consolidar contrato da API e remover endpoint duplicado/sem uso.

## Ordem Recomendada de Execucao

1. Passo 1 (hoje): congelar e documentar baseline
- Arquivo de referencia: este documento.
- Nao desabilitar nada ainda.

2. Passo 2: fechar whitelist do que e core
- Manter oficialmente: Filament `/admin`, API core e `/diarios/{id}/arquivo`.

3. Passo 3: endurecer acesso de rotas operacionais
- Primeiro alvo: `/docs*` e `/horizon*`.
- Regra: restringir antes de remover.

4. Passo 4: limpar API por uso real
- Medir uso (logs) de endpoints menos criticos (`stats`, `export`, `test-search`, etc).
- Marcar deprecacao e desabilitar por lote pequeno.

## Checklist de Desabilitacao Segura (por rota/grupo)
1. Confirmar se a rota esta em uso (frontend, job, integracao externa).
2. Marcar como `deprecated` no documento.
3. Publicar janela de remocao.
4. Desabilitar em ambiente de homologacao.
5. Validar regressao.
6. Aplicar em producao.

## Primeira Tarefa Tecnica Recomendada
Iniciar por **protecao de rotas operacionais**:
1. Restringir `/docs*` para nao ficar publico em producao.
2. Revisar/confirmar protecao de `/horizon*`.

Isso reduz risco de exposicao sem mexer no core de negocio.

## Ajuste Ja Aplicado
1. Scribe (`/docs*`) agora depende de flag:
- Arquivo: `config/scribe.php`
- Config: `laravel.add_routes = env('SCRIBE_ADD_ROUTES', false)`
- Resultado: docs desabilitado por padrao.

2. Flag no exemplo de ambiente:
- Arquivo: `.env.example`
- Variavel: `SCRIBE_ADD_ROUTES=true`
- Uso: em dev/local, habilitar explicitamente quando precisar da documentacao web.
