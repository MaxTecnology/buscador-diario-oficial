# Plano de Limpeza do Projeto Atual

## Objetivo
Reorganizar o projeto atual sem recriar do zero, mantendo o que ja funciona e removendo duplicidade, acoplamento e inconsistencias.

## Arquitetura Alvo
- `Filament` em `/admin` para backoffice (operacao interna, cadastros, configuracoes, relatorios).
- `API` com regras de negocio em `Actions/Services` (nao em controllers/resources).
- `Blade + Bootstrap` para area de usuario final (quando entrar essa fase).
- Uma unica camada de configuracao, uma unica camada de notificacao e uma unica pipeline de processamento PDF.

## Diagnostico Tecnico (Fase 0)
Data: 2026-02-20

### Pontos Criticos (Alta Prioridade)
1. Duplicidade de configuracao:
- Existem `app/Models/SystemConfig.php` e `app/Models/ConfiguracaoSistema.php`.
- Existem duas tabelas e dois seeders: `system_configs` e `configuracoes_sistema`.
- Codigo atual usa as duas abordagens ao mesmo tempo.

2. Duplicidade de notificacao:
- Existem `app/Services/NotificationService.php` e `app/Services/NotificacaoService.php`.
- Existem dois jobs de WhatsApp: `app/Jobs/SendWhatsAppNotification.php` e `app/Jobs/EnviarNotificacaoWhatsappJob.php`.
- Chaves de pivot e campos de usuario estao misturados (`notificacao_whatsapp`, `pode_receber_whatsapp`, `telefone`, `telefone_whatsapp`, `name`, `nome`).

3. Duplicidade de processamento de diarios/PDF:
- Existem `app/Services/PdfProcessingService.php`, `app/Services/PdfProcessorService.php` e `app/Services/DiarioProcessingService.php`.
- Existem jobs paralelos com responsabilidade parecida: `app/Jobs/ProcessarDiarioJob.php` e `app/Jobs/ProcessarPdfJob.php`.

4. Risco funcional por inconsistencias de naming:
- `User` usa `name` no model (`app/Models/User.php`), mas trechos do fluxo usam `nome`.
- Notificacao usa ora `telefone`, ora `telefone_whatsapp`.

### Pontos Importantes (Media Prioridade)
1. Controllers API com muita regra e acoplamento de consulta/formatacao:
- Exemplo: `app/Http/Controllers/Api/DiarioController.php` esta grande e com varias responsabilidades.

2. Resources/Pages do Filament instanciam servicos direto e com estrategias diferentes:
- Trechos com `new Service()` e outros com `app(Service::class)`.

3. Padroes de nomenclatura mistos no dominio (pt/en) e comentarios com encoding inconsistente em algumas rotas.

### Pontos de Qualidade (Baixa Prioridade)
1. Suite de testes ainda basica:
- Apenas exemplos em `tests/Feature/ExampleTest.php` e `tests/Unit/ExampleTest.php`.

2. Documentacao funcional desatualizada para o modelo atual de refatoracao continua.

## Backlog Priorizado

### Alta
1. Unificar configuracao de sistema
- Definir fonte unica (`SystemConfig` ou `ConfiguracaoSistema`).
- Criar camada de compatibilidade temporaria para nao quebrar chamadas atuais.
- Migrar leituras/escritas para uma API unica de config.

2. Unificar stack de notificacao
- Escolher um service unico (`NotificacaoService` ou `NotificationService`).
- Escolher jobs unicos por canal (email/whatsapp).
- Padronizar campos de usuario e pivot usados no envio.

3. Unificar pipeline de processamento PDF/diario
- Definir fluxo oficial: upload -> fila -> extracao -> matching -> ocorrencia -> notificacao.
- Remover caminho paralelo legado.

### Media
1. Refatorar controllers API para Actions/Services por caso de uso.
2. Padronizar injecao de dependencia (evitar `new` em Page/Resource quando possivel).
3. Separar claramente contratos de API (requests/resources) da regra de negocio.

### Baixa
1. Expandir testes de feature nos fluxos criticos.
2. Consolidar documentacao operacional e runbook.

## Primeiro Modulo Recomendado (Execucao Imediata)
`Modulo 1: Configuracao e Contratos Internos`

Escopo:
1. Escolher fonte unica de configuracao.
2. Criar facade/service de config interno (`App\Support\Config\AppConfig` ou similar).
3. Atualizar pontos de leitura mais criticos para usar a nova camada.
4. Manter compatibilidade temporaria sem quebra de comportamento.

Criterio de aceite:
1. Nao existe codigo novo acessando diretamente os dois models de config.
2. Leitura/escrita de configuracao passa por uma unica interface.
3. Fluxos de notificacao e processamento continuam funcionando apos mudanca.

## Ordem de Execucao Recomendada
1. Modulo 1: Configuracao (fonte unica).
2. Modulo 2: Notificacao (service/jobs/campos).
3. Modulo 3: Processamento PDF (pipeline unica).
4. Modulo 4: API limpa (actions + requests + resources).
5. Modulo 5: Testes de regressao + hardening.
