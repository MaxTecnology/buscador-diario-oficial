# Reprocessamento de Diários (Arquitetura)

## Objetivo

Permitir reprocessar diários já concluídos (ex.: após cadastrar empresa nova) sem perder histórico e sem duplicar ocorrências em uso pela operação.

## Estratégia adotada

- Histórico de execuções em `diario_processamentos`
- Ocorrências versionadas na mesma tabela `ocorrencias`
- Listagem operacional mostra apenas ocorrências `ativas`
- Histórico permanece no banco para auditoria/comparação futura

## Estrutura

### Tabela `diario_processamentos`

Registra cada execução:

- `tipo`: `inicial` | `reprocessamento`
- `modo`: `completo` | `somente_busca` (reservado para próxima etapa)
- `status`: `pendente` | `processando` | `concluido` | `erro`
- `motivo`
- `notificar`
- `limpar_ocorrencias_anteriores`
- `iniciado_por_user_id`
- `iniciado_em`, `finalizado_em`
- métricas (`total_ocorrencias`, `novas_ocorrencias`, `ocorrencias_desativadas`)

### Tabela `ocorrencias`

Novos campos:

- `diario_processamento_id` (FK)
- `ativo` (bool)

## Fluxo de reprocessamento

1. Usuário aciona `Reprocessar` no painel de Diários.
2. Diário é enfileirado em `ProcessarPdfJob` com contexto (`tipo`, `motivo`, `notificar` etc.).
3. Job cria um registro em `diario_processamentos`.
4. `PdfProcessorService` processa e cria novas ocorrências vinculadas à execução.
5. Em sucesso:
   - ocorrências anteriores ativas são desativadas (se opção marcada)
   - ocorrências da nova execução são ativadas
   - execução é finalizada com métricas
6. Em erro:
   - execução é marcada como erro
   - ocorrências anteriores ativas permanecem intactas

## Comportamento atual no painel

- Ação `Processar`: para diários `pendente`/`erro`
- Ação `Reprocessar`: para diários `concluido` (com motivo + opções)
- Reprocessamento em lote para diários concluídos
- Lista de ocorrências mostra apenas `ativo = true` por padrão

## Próxima etapa recomendada

- Tela de histórico por diário (`diario_processamentos`)
- Filtro/aba para visualizar ocorrências históricas (`ativo = false`)
- Modo `somente_busca` reutilizando `caminho_texto_completo` (sem reparse do PDF)
