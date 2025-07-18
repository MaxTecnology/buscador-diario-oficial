# Sistema de Diários Oficiais - Backend Laravel

**Especificação Técnica para Desenvolvimento Backend**

---

## Objetivo do Projeto

Desenvolver o backend Laravel para sistema de monitoramento de 350+ empresas em Diários Oficiais estaduais, com processamento otimizado de PDF, sistema de usuários, filas de processamento e API completa.

---

## Contexto Crítico

Sistema de compliance legal que processa 26 PDFs/dia de 100-300 páginas cada, buscando 350 empresas por PDF = 236.600 operações de busca diárias. Precisão é CRÍTICA - falhas podem resultar em multas. Todos os processamentos devem ser logados para auditoria.

---

## Stack Tecnológica

### **Framework e Linguagem**
- Laravel 12 + PHP 8.4
- MySQL 8.0 (existente no projeto)
- Redis (existente no projeto)
- Laravel Sail (Docker - já configurado)

### **Bibliotecas Principais**
- Filament 3.x (interface administrativa)
- Spatie Laravel Permission (roles/permissões)
- Spatie Laravel Activity Log (auditoria)
- Spatie PDF-to-Text (extração de texto de PDFs)
- Laravel Sanctum (autenticação API)
- Laravel Scout + TNTSearch (busca avançada)
- Laravel Horizon (monitoramento de filas)

### **Infraestrutura**
- Redis (cache + filas)
- Laravel Queue (processamento assíncrono)
- Laravel Storage (armazenamento de PDFs)
- Laravel Mail (notificações email)

---

## Funcionalidades Backend Requeridas

### **1. Sistema de Usuários Unificado**

#### **Estrutura de Usuários**
- Estender model User existente do Laravel
- Campos adicionais: telefone, pode_fazer_login, created_by
- Sistema de roles: admin, manager, operator, viewer, notification_only
- Relacionamento many-to-many com empresas via tabela pivot
- Permissões granulares por empresa (visualizar, receber_email, receber_whatsapp)

#### **Autenticação e Autorização**
- Laravel Sanctum para autenticação API
- Middleware personalizado para verificação de roles
- Sistema de refresh tokens
- Middleware de auditoria automática para todas as ações

### **2. Sistema de Empresas com Busca Otimizada**

#### **Model Empresa**
- Campos: nome, cnpj, inscricao_estadual, termos_personalizados (JSON), variantes_busca (JSON auto-geradas)
- Prioridade: alta, media, baixa
- Score mínimo configurável por empresa
- Status ativo/inativo
- Auto-geração de variantes de busca (sem acentos, uppercase, abreviações)

#### **Busca Inteligente**
- Laravel Scout para indexação
- Busca exata por CNPJ (com e sem formatação)
- Busca fuzzy por nome da empresa
- Busca por termos personalizados
- Algoritmo de scoring ponderado (CNPJ: 100%, nome: 90%, termos: 85%)
- Busca com similaridade usando similar_text PHP

### **3. Sistema de Processamento PDF**

#### **Upload e Validação**
- Validação de tipo de arquivo (apenas PDF)
- Limite de tamanho (10MB)
- Geração de hash SHA-256 para integridade
- Storage Laravel para persistência
- Metadados: estado, data do diário, usuário que fez upload

#### **Processamento Assíncrono**
- Laravel Job para processamento em background
- Timeout de 300 segundos por PDF
- 3 tentativas automáticas em caso de falha
- Status tracking: pendente, processando, concluido, erro

#### **Extração de Texto**
- Spatie PDF-to-Text como método único (PDFs sempre têm texto)
- Extração de texto completo do PDF para indexação
- Armazenamento do texto extraído na tabela diarios
- Limpeza e normalização do texto extraído
- Contagem automática de páginas

#### **Engine de Busca**
- Service class para busca de empresas no texto extraído
- Busca por palavras-chave no texto completo do PDF
- Busca contextual com identificação de parágrafos relevantes
- Extração de contexto amplo (300 caracteres antes/depois)
- Determinação automática de confiança baseada no tipo de match
- Armazenamento de todas as ocorrências com texto completo
- Indexação do texto extraído para buscas futuras

### **4. Sistema de Notificações**

#### **Email**
- Laravel Mailable com templates personalizados
- Queue para envio assíncrono
- Retry automático (3 tentativas)
- Template com dados da empresa, diário, contexto
- Possibilidade de anexar página específica do PDF

#### **WhatsApp**
- Job separado para integração via webhook
- Formatação de mensagem otimizada para mobile
- Retry automático com backoff exponencial
- Log detalhado de respostas da API

#### **Controle de Notificações**
- Verificação de permissões por usuário/empresa
- Agrupamento de notificações para evitar spam
- Horário limite para WhatsApp configurável
- Dashboard para acompanhar status de envios

### **5. Sistema de Auditoria Completo**

#### **Activity Log**
- Spatie Activity Log para rastreamento automático
- Log de todas as ações de CRUD
- Metadados detalhados: IP, User-Agent, dados da requisição
- Exclusão automática de dados sensíveis (senhas, tokens)

#### **Middleware de Auditoria**
- Interceptação automática de requests importantes
- Log estruturado com contexto completo
- Performance tracking (tempo de resposta)
- Filtros para excluir rotas irrelevantes

#### **Logs de Sistema**
- Upload de PDFs: usuário, arquivo, timestamp, tamanho
- Processamento: empresas encontradas, tempo, algoritmos usados
- Notificações: status de envio, tentativas, erros
- Ações administrativas: criação/edição de usuários e empresas

### **6. Interface Administrativa (Filament)**

#### **Dashboard Principal**
- Widgets com métricas em tempo real
- Total de usuários, empresas ativas, PDFs processados
- Ocorrências encontradas hoje
- Taxa de sucesso de processamento
- PDFs pendentes na fila

#### **Gestão de Usuários**
- CRUD completo com formulários validados
- Atribuição de roles via interface
- Vinculação com empresas e permissões granulares
- Filtros por role, status, empresa
- Ações em lote (ativar/desativar, vincular empresas)

#### **Gestão de Empresas**
- CRUD com campos personalizados
- Upload em lote via CSV
- Teste de busca em tempo real
- Histórico de ocorrências por empresa
- Configuração de score mínimo e prioridade

#### **Monitoramento de Sistema**
- Visualização de filas Redis
- Status de jobs em processamento
- Logs de erro e performance
- Relatórios de compliance para download

### **7. API REST Completa**

#### **Endpoints de Autenticação**
- POST /api/login (email/password → tokens)
- POST /api/logout
- POST /api/refresh-token
- GET /api/me (dados do usuário autenticado)

#### **Endpoints de Usuários**
- GET /api/users (listar com filtros e paginação)
- POST /api/users (criar novo usuário)
- GET /api/users/{id} (detalhes)
- PUT /api/users/{id} (atualizar)
- DELETE /api/users/{id}

#### **Endpoints de Empresas**
- GET /api/empresas (listar com busca e filtros)
- POST /api/empresas (criar)
- GET /api/empresas/{id}
- PUT /api/empresas/{id}
- DELETE /api/empresas/{id}
- GET /api/empresas/{id}/ocorrencias (histórico)

#### **Endpoints de Diários**
- POST /api/diarios (upload de PDF)
- GET /api/diarios (listar com status)
- GET /api/diarios/{id} (detalhes e progresso)
- GET /api/diarios/{id}/ocorrencias (resultados)
- DELETE /api/diarios/{id}

#### **Endpoints de Relatórios**
- GET /api/dashboard/metrics (métricas gerais)
- GET /api/relatorios/compliance (dados para auditoria)
- GET /api/ocorrencias (busca de ocorrências)
- GET /api/logs/atividades (logs de auditoria)

### **8. Sistema de Configurações**

#### **Configurações do Sistema**
- Tabela system_configs para armazenar configurações
- Interface Filament para edição
- Cache automático de configurações
- Tipos: string, number, boolean, json

#### **Configurações Importantes**
- Tamanho máximo de arquivo
- Tipos de arquivo permitidos (apenas PDF)
- Score mínimo padrão para matches
- Tentativas de retry para notificações
- Configurações de extração de texto
- URLs de webhook WhatsApp
- Configurações de busca e indexação

---

## Estrutura de Banco de Dados

### **Migrations Necessárias**
1. **add_campos_to_users_table**: telefone, pode_fazer_login, created_by
2. **create_empresas_table**: todos os campos da empresa
3. **create_user_empresa_permissions_table**: relacionamento com permissões
4. **create_diarios_table**: metadados dos PDFs + campo texto_extraido (LONGTEXT)
5. **create_ocorrencias_table**: resultados das buscas
6. **create_system_configs_table**: configurações do sistema

### **Seeders Obrigatórios**
1. **RoleSeeder**: criar roles padrão com permissões
2. **SystemConfigSeeder**: configurações iniciais
3. **AdminUserSeeder**: usuário admin inicial

### **Índices de Performance**
- MySQL: índices compostos para buscas frequentes
- Full-text search em campos de texto
- Índices para foreign keys e campos de filtro

### **Exemplo de Busca Baseado no PDF Fornecido**

Com base no exemplo do Diário Oficial de Alagoas, o sistema deve:

#### **Padrões de Busca Específicos**
- **CNPJ formatado**: "27.292.082/0001-41" 
- **CNPJ sem formatação**: "27292082000141"
- **Razão Social**: "MEDIPRO COMÉRCIO DE PRODUTOS HOSPITALARES EIRELI"
- **Nome Fantasia**: variações da razão social
- **CPF de sócios**: "96377615515", "RODRIGO DORIA DA ROCHA"

#### **Contexto de Busca**
- Extrair parágrafo completo onde empresa aparece
- Identificar tipo de publicação (edital, notificação, etc.)
- Capturar informações relevantes (endereço, protocolo, data)
- Determinar relevância baseada no contexto

#### **Tipos de Ocorrência no Diário**
- Notificações fiscais
- Editais de cobrança
- Intimações tributárias
- Publicações de processos
- Decisões administrativas

O sistema deve conseguir identificar "MEDIPRO COMÉRCIO DE PRODUTOS HOSPITALARES EIRELI" no texto e extrair todo o contexto do edital GEFIS onde a empresa aparece.

### **Framework de Testes**
- Pest PHP como framework principal
- Factories para geração de dados de teste
- RefreshDatabase para isolamento entre testes

### **Cobertura de Testes Obrigatória**
- Testes unitários: Models, Services, Jobs
- Testes de feature: Controllers, API endpoints
- Testes de integração: Processamento de PDF, notificações
- Performance tests: busca com 350 empresas
- Cobertura mínima: 80%

### **Qualidade de Código**
- PSR-12 coding standards
- Laravel Pint para formatação automática
- PHPStan para análise estática
- Larastan para regras específicas do Laravel

---

## Commands Artisan Personalizados

### **Commands Obrigatórios**
1. **diarios:processar-pendentes**: processar PDFs em lote
2. **relatorio:compliance**: gerar relatórios de auditoria
3. **usuarios:sync-permissions**: sincronizar permissões
4. **sistema:health-check**: verificar saúde do sistema
5. **empresas:gerar-variantes**: regenerar variantes de busca

### **Agendamento (Scheduler)**
- Limpeza de logs antigos
- Backup automático de configurações
- Verificação de integridade dos arquivos
- Relatórios automáticos por email

---

## Performance e Otimizações

### **Métricas Esperadas**
- Extração de texto: < 10 segundos para 200 páginas
- Busca 350 empresas no texto: < 5 segundos
- API response: < 150ms para CRUD
- Throughput: 150 req/s sustentável

### **Otimizações Obrigatórias**
- Eager loading em relacionamentos
- Cache Redis para consultas frequentes
- Índices otimizados no MySQL
- Queue workers para processamento pesado
- Pagination em todas as listagens

---

## Documentação

### **Documentação Automática**
- Scribe para documentação da API
- Docblocks detalhados em todos os métodos
- README com instruções de setup
- Postman collection gerada automaticamente

### **Documentação Técnica**
- Diagramas de entidade-relacionamento
- Fluxo de processamento de PDF
- Arquitetura do sistema de filas
- Guia de troubleshooting

---

## Configuração de Ambiente

### **Docker/Sail**
- Manter configuração existente do Laravel Sail
- Adicionar serviços: Redis, queue workers
- Health checks para todos os containers
- Volumes para persistência de dados

### **Variáveis de Ambiente**
- Configurações de PDF processing
- Credenciais de email e WhatsApp
- Configurações de cache e fila
- Limites de upload e timeout

---

## Instruções de Desenvolvimento

### **Ordem de Implementação**
1. Migrations e seeders básicos
2. Models com relationships
3. Sistema de autenticação e roles
4. CRUD básico de usuários e empresas
5. Upload e storage de PDFs
6. Jobs de processamento
7. Sistema de busca e matching
8. Notificações
9. Interface Filament
10. API REST
11. Testes completos
12. Commands e scheduler
13. Documentação

### **Boas Práticas**
- Usar Form Requests para validação
- Services para lógica de negócio complexa
- Resources para serialização de API
- Events/Listeners para ações assíncronas
- Policies para autorização
- Cache tags para invalidação inteligente

### **Considerações de Segurança**
- Sanitização de uploads
- Rate limiting em APIs
- Validação rigorosa de inputs
- Logs de auditoria para compliance
- Proteção contra ataques comuns

---

## Entregáveis Esperados

1. **Sistema Laravel funcional** integrado ao projeto existente
2. **Migrations** para todas as funcionalidades
3. **Interface Filament** completa e funcional
4. **API REST** documentada e testada
5. **Jobs de processamento** otimizados
6. **Suite de testes** com alta cobertura
7. **Commands personalizados** para operação
8. **Documentação** técnica e de uso
9. **PROGRESS.md** detalhando implementação

---

**IMPORTANTE**: Este backend deve processar Diários Oficiais estaduais (como o exemplo de Alagoas fornecido) extraindo texto completo e realizando buscas precisas por empresas, mantendo logs detalhados para compliance legal. Todos os PDFs contêm texto extraível - não é necessário OCR.