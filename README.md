# =� Sistema de Monitoramento de Di�rios Oficiais

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Filament](https://img.shields.io/badge/Filament-3.x-F59E0B?style=for-the-badge&logo=laravel&logoColor=white)

**Sistema web para monitoramento automatizado de empresas em di�rios oficiais brasileiros com notifica��es granulares via Email e WhatsApp**

[=� Instala��o](#-instala��o) " [=� Documenta��o](#-documenta��o) " [( Funcionalidades](#-funcionalidades-principais) " [<� Como Usar](#-como-usar)

</div>

---

## <� **Sobre o Projeto**

Sistema completo para monitorar empresas em di�rios oficiais brasileiros, com processamento manual de PDFs e **sistema de notifica��es granular** que oferece controle total sobre quando, para quem e como notificar via Email/WhatsApp.

### =% **Principais Diferenciais**

-  **Controle Granular de Notifica��es** - Escolha usu�rios e tipos individualmente
-  **Processamento de PDFs Brasileiro** - Otimizado para CNPJ, inscri��es estaduais e documentos BR
-  **Interface Administrativa Completa** - Filament 3.x com dashboards e relat�rios
-  **Sistema de Logs Imut�veis** - Auditoria completa e organizada
-  **WhatsApp Integration** - Evolution API para notifica��es m�veis
-  **Pronto para Produ��o** - Testado e otimizado para uso real

---

## <� **Stack Tecnol�gica**

| Tecnologia | Vers�o | Fun��o |
|------------|---------|---------|
| **Laravel** | 12.x | Framework backend |
| **PHP** | 8.4+ | Linguagem principal |
| **Filament** | 3.x | Interface administrativa |
| **MySQL** | 8.0+ | Banco de dados |
| **Redis** | 7.0+ | Cache e sess�es |
| **Docker** | Latest | Containeriza��o |
| **smalot/pdfparser** | Latest | Processamento de PDFs |
| **Evolution API** | Latest | Integra��o WhatsApp |

---

## ( **Funcionalidades Principais**

### = **Sistema de Notifica��es Granular**
- **Controle Total**: Notifica��es autom�ticas desabilitadas por padr�o
- **Sele��o Individual**: Escolha usu�rios espec�ficos por ocorr�ncia  
- **Tipos Independentes**: Email e WhatsApp separados por usu�rio
- **Interface Intuitiva**: Modal com checkboxes para sele��o f�cil

### =� **Processamento de PDFs**
- **Algoritmo Brasileiro**: Otimizado para CNPJ, inscri��es estaduais e nomes
- **Scores de Confian�a**: CNPJ (95%), Inscri��o (85%), Nome (70%+)
- **Normaliza��o**: Remove acentos, pontua��o e trata varia��es
- **Upload Manual**: Interface drag-and-drop com processamento imediato

### =� **Interface Administrativa**
- **Dashboard Completo**: M�tricas em tempo real
- **Relat�rios Avan�ados**: Filtros por data, empresa, estado, score
- **Gest�o de Usu�rios**: Roles, permiss�es e relacionamentos empresa-usu�rio
- **Configura��es Din�micas**: Sistema config via interface

### =� **Sistema de Logs Organizado**
- **Logs de Notifica��es**: Rastreamento de emails e WhatsApp
- **Timeline de Atividades**: A��es b�sicas de usu�rios
- **Auditoria do Sistema**: Logs detalhados com Spatie ActivityLog
- **Dados Imut�veis**: Logs n�o podem ser alterados ap�s cria��o

---

## =� **Instala��o**

### **Pr�-requisitos**
- Docker & Docker Compose
- Git
- 4GB+ RAM dispon�vel

### **Passo a Passo**

```bash
# 1. Clonar o reposit�rio
git clone [url-do-repositorio]
cd diario

# 2. Copiar arquivo de ambiente
cp .env.example .env

# 3. Instalar depend�ncias
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# 4. Configurar .env (edite os valores necess�rios)
nano .env

# 5. Subir containers
./vendor/bin/sail up -d

# 6. Gerar chave da aplica��o
./vendor/bin/sail artisan key:generate

# 7. Executar migra��es
./vendor/bin/sail artisan migrate

# 8. Executar seeders (dados iniciais)
./vendor/bin/sail artisan db:seed

# 9. Limpar caches
./vendor/bin/sail artisan optimize:clear
```

### **Configura��o do .env**

```env
# Banco de dados
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=diario
DB_USERNAME=sail
DB_PASSWORD=password

# WhatsApp API (Evolution API)
WHATSAPP_API_URL=http://sua-evolution-api:8080
WHATSAPP_API_KEY=sua-chave-api
WHATSAPP_INSTANCE=sua-instancia

# Email
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

### **Usu�rios Padr�o**

Ap�s executar os seeders:

| Email | Senha | Fun��o |
|-------|-------|---------|
| admin@diario.com | admin123 | Administrador |
| manager@diario.com | manager123 | Gerente |
| operator@diario.com | operator123 | Operador |

---

## <� **Como Usar**

### **1. =� Upload de Di�rios**
1. Acesse **"Di�rios"** no menu
2. Clique em **"Novo Di�rio"**
3. Fa�a upload do PDF
4. Aguarde o processamento autom�tico

### **2. = Visualizar Ocorr�ncias**
1. Acesse **"Ocorr�ncias"** no menu
2. Use filtros por empresa, estado, score
3. Visualize detalhes de cada ocorr�ncia encontrada

### **3. =� Enviar Notifica��es (NOVO)**
1. Na lista de ocorr�ncias, clique **"Enviar Notifica��es"**
2. Selecione os usu�rios da empresa
3. Para cada usu�rio, marque:
   -  **Email** (se quiser enviar por email)
   -  **WhatsApp** (se quiser enviar por WhatsApp)
4. Clique **"Enviar"**
5. Veja feedback: "Enviado: X email(s) e Y WhatsApp(s)"

### **4. =� Acompanhar Logs**
- **Logs de Notifica��es**: Rastreamento de emails/WhatsApp
- **Timeline de Atividades**: A��es de usu�rios
- **Auditoria do Sistema**: Logs detalhados de mudan�as

### **5. � Configura��es**
- **Configura��es do Sistema**: Ajustes gerais
- **WhatsApp Settings**: Configurar API e testar conectividade
- **Templates de Notifica��o**: Personalizar mensagens

---

## =� **Screenshots**

### Dashboard Principal
Interface com m�tricas em tempo real e widgets informativos.

### Sistema de Notifica��es Granular
Modal intuitivo para sele��o de usu�rios e tipos de notifica��o.

### Logs Organizados
Tr�s interfaces dedicadas para diferentes tipos de logs.

---

## =� **Comandos �teis**

```bash
# Executar testes
./vendor/bin/sail artisan test

# Limpar todos os caches
./vendor/bin/sail artisan optimize:clear

# Executar jobs (se usar queues)
./vendor/bin/sail artisan queue:work

# Testar WhatsApp
./vendor/bin/sail artisan whatsapp:test

# Reprocessar notifica��es pendentes
./vendor/bin/sail artisan notifications:process

# Ver logs em tempo real
./vendor/bin/sail logs -f
```

---

## =� **Capacidade do Sistema**

| M�trica | Valor | Observa��o |
|---------|--------|-------------|
| **PDFs por dia** | ~26 | Testado e otimizado |
| **Tamanho m�ximo** | 50MB | Por arquivo PDF |
| **P�ginas m�ximas** | 500+ | Por PDF |
| **Usu�rios simult�neos** | 10-15 | Interface web |
| **Empresas** | Ilimitado | Sem restri��es |

---

## =' **Configura��o de Produ��o**

### **Servidor Recomendado**
- **CPU**: 2+ cores
- **RAM**: 4GB+ 
- **Storage**: 50GB+ SSD
- **PHP**: 8.4+ com extens�es: gd, zip, pdo_mysql, redis
- **Banco**: MySQL 8.0+ otimizado
- **Cache**: Redis 7.0+
- **Proxy**: Nginx
- **SSL**: Certificado v�lido obrigat�rio

### **Seguran�a**
- Firewall configurado (80, 443, 22)
- Senhas fortes para todos usu�rios
- Backup autom�tico di�rio
- Logs de auditoria ativos
- Rate limiting configurado

---

## =� **Documenta��o**

- =� **[DOCUMENTACAO.md](DOCUMENTACAO.md)** - Documenta��o t�cnica completa
- <� **[Estrutura do Projeto](#estrutura-do-projeto)** - Organiza��o de arquivos
- = **[Problemas Conhecidos](#problemas-conhecidos)** - Issues e solu��es
- = **[Changelog](#changelog)** - Hist�rico de vers�es

---

## <� **Estrutura do Projeto**

```
app/
   Filament/
      Resources/          # Interfaces CRUD
      Pages/             # P�ginas customizadas  
      Widgets/           # Widgets do dashboard
   Models/                # Models Eloquent
   Services/              # Servi�os (WhatsApp, Notifica��o)
   Jobs/                  # Jobs ass�ncronos
   Commands/              # Comandos Artisan

database/
   migrations/            # Migra��es do banco
   seeders/              # Dados iniciais

resources/
   views/filament/       # Views customizadas
```

### **Arquivos Importantes**
- `app/Services/NotificacaoService.php` - Sistema de notifica��es
- `app/Services/PdfProcessorService.php` - Processamento de PDFs  
- `app/Services/WhatsAppService.php` - Integra��o WhatsApp
- `app/Filament/Resources/OcorrenciaResource.php` - Interface de ocorr�ncias

---

## = **Problemas Conhecidos**

### **Resolvidos **
- Sistema de notifica��es granular implementado
- Status de logs corrigido (sucesso/falha)
- Processamento de PDFs otimizado
- Interface responsiva para alto volume
- Integra��o WhatsApp funcional

### **Limita��es**
- Processamento de PDFs muito grandes (>50MB) pode ser lento
- Requer configura��o manual da Evolution API para WhatsApp
- Interface otimizada para ~26 PDFs por dia (expans�vel)

---

## = **Changelog**

### **v2.1 (19/07/2025) - Sistema Granular**
-  Sistema de notifica��es granular implementado
-  Controle individual por usu�rio e tipo
-  Notifica��es autom�ticas desabilitadas por padr�o
-  Logs imut�veis organizados em interfaces dedicadas
-  Status de logs corrigido

### **v2.0 (18/07/2025) - Sistema Completo**
-  Sistema completo de processamento de PDFs
-  Interface administrativa com Filament 3.x
-  Integra��o WhatsApp funcional
-  Sistema de relat�rios e dashboards
-  Pronto para produ��o

---

## > **Contribuindo**

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/nova-funcionalidade`)
3. Commit suas mudan�as (`git commit -am 'Adiciona nova funcionalidade'`)
4. Push para a branch (`git push origin feature/nova-funcionalidade`)
5. Abra um Pull Request

---

## =� **Licen�a**

Este projeto est� sob a licen�a [MIT](LICENSE). Veja o arquivo `LICENSE` para mais detalhes.

---

## =� **Suporte**

- =� **Email**: [seu-email@domain.com]
- =� **WhatsApp**: [seu-whatsapp]
- =� **Issues**: [Link para issues do GitHub]
- =� **Documenta��o**: [DOCUMENTACAO.md](DOCUMENTACAO.md)

---

<div align="center">

**Desenvolvido com d para facilitar o monitoramento de di�rios oficiais brasileiros**

P **Se este projeto foi �til, considere dar uma estrela!** P

</div>