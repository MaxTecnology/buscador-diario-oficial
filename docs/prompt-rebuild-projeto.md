# Prompt Mestre v3 - Limpeza e Refatoracao do Projeto Atual

Use este prompt em um chat novo para reorganizar o projeto existente sem recriar do zero.

```text
Nao vamos recriar o projeto.
Quero refatorar e limpar o projeto atual "Diario Oficial Monitor" com foco em producao.

Voce sera meu Tech Lead + Coding Agent.
Trabalhe em entregas pequenas, rastreaveis e com risco controlado.

Contexto atual:
- Projeto em Laravel 12 + Filament.
- Ja existem funcionalidades implementadas (empresas, diarios, ocorrencias, notificacoes, logs).
- Queremos manter o que funciona e remover acoplamento/desorganizacao.
- Filament fica para backoffice (cadastros/operacao interna).
- Area de usuario final pode ser Blade + Bootstrap, separada do backoffice.

Objetivo:
1) padronizar arquitetura por dominio;
2) reduzir duplicidade de codigo e regras espalhadas;
3) manter estabilidade em producao enquanto refatora;
4) preparar base para evolucao da area de usuario;
5) melhorar qualidade (testes, validacao, autorizacao, logs).

Stack alvo (sem mudar o core):
- Backend: Laravel 12 + PHP 8.4
- Backoffice: Filament em /admin
- Front usuario: Blade + Bootstrap (quando aplicavel)
- Banco: MySQL
- Cache/Fila: Redis
- Storage: MinIO (S3 compativel)
- Auth/ACL: Laravel + Spatie Permission

Regras obrigatorias:
1) Nao quebrar fluxo atual:
   - Cada mudanca deve preservar comportamento existente ou explicar mudanca de regra.
2) Refatorar por modulo:
   - Um modulo por vez (Auth, Empresas, Diarios, Ocorrencias, Notificacoes).
3) Controllers finos:
   - Regra de negocio em Actions/Services.
4) Validacao e autorizacao:
   - Form Requests + Policies/Gates.
5) Sem codigo fantasma:
   - remover classes/metodos/rotas/colunas sem uso.
6) Observabilidade minima:
   - logs claros para processamento e notificacoes.

Fluxo de trabalho em toda entrega:
1) Diagnostico curto do modulo.
2) Plano da entrega.
3) Arquivos que serao alterados.
4) Criterios de aceite.
5) Implementacao.
6) Testes/lint.
7) Resumo final com riscos e proximos passos.

Fases recomendadas (projeto atual):
Fase 0 - Diagnostico tecnico:
- Inventario de rotas, modulos, jobs e servicos.
- Mapa de pontos com duplicidade/acoplamento.
- Backlog priorizado de limpeza.

Fase 1 - Estrutura e convencoes:
- Padrao de pastas por dominio.
- Padrao de nomes e responsabilidades.
- Base de requests, actions e policies.

Fase 2 - Auth e acesso:
- Revisao de roles/permissoes.
- Revisao de middlewares e rotas protegidas.
- Endurecimento de login e sessao.

Fase 3 - Empresas e Diarios:
- Consolidar regras de CRUD.
- Limpar validacoes e normalizacao.
- Garantir consistencia com storage MinIO.

Fase 4 - Processamento e Ocorrencias:
- Isolar pipeline de processamento PDF.
- Padronizar score/match e logs.
- Melhorar rastreabilidade de erros e reprocessamento.

Fase 5 - Notificacoes e auditoria:
- Padronizar envio manual por canal.
- Consolidar logs imutaveis de notificacao.
- Garantir status e erros corretos.

Fase 6 - Area de usuario (Blade + Bootstrap):
- Criar base de layout e rotas dedicadas.
- Consumir servicos existentes sem duplicar regra.

Fase 7 - Hardening de producao:
- Testes de fluxos criticos.
- Revisao de config/env e segredos.
- Checklist final de deploy/operacao.

Criterios de qualidade:
- Testes de feature para fluxos criticos:
  auth, upload, processamento, ocorrencia, notificacao manual.
- Nenhuma regra de negocio relevante em Resource/Controller quando puder ir para Service/Action.
- Nao introduzir novas dependencias sem justificativa tecnica.

Formato da resposta:
- Objetivo e direto.
- Decisoes tecnicas justificadas em 1-2 linhas.
- Sempre listar o que foi alterado e como validar.

Agora inicie pela Fase 0 e entregue:
1) diagnostico tecnico do estado atual;
2) arquitetura alvo por dominio;
3) backlog de limpeza priorizado (alto, medio, baixo);
4) primeiro modulo recomendado para executar imediatamente.
```
