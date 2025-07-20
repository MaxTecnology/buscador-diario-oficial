<?php

namespace App\Console\Commands;

use App\Services\LoggingService;
use Illuminate\Console\Command;

class TestarLogsCommand extends Command
{
    protected $signature = 'logs:testar {--tipo=todos}';
    
    protected $description = 'Gerar logs de teste para validar o sistema de auditoria';

    public function handle(): int
    {
        $loggingService = new LoggingService();
        $tipo = $this->option('tipo');

        $this->info('🔍 Gerando logs de teste...');

        if ($tipo === 'todos' || $tipo === 'performance') {
            $this->info('⚡ Testando logs de performance...');
            
            // Simular operações com diferentes tempos
            $operacoes = [
                'processamento_pdf_pequeno' => 500,   // 0.5s
                'processamento_pdf_medio' => 2500,    // 2.5s  
                'processamento_pdf_grande' => 8000,   // 8s
                'busca_empresa' => 150,               // 0.15s
                'envio_notificacao' => 300,           // 0.3s
            ];
            
            foreach ($operacoes as $operacao => $tempo) {
                $loggingService->logPerformance($operacao, $tempo, [
                    'ambiente' => 'teste',
                    'detalhes' => "Simulação de {$operacao}"
                ]);
            }
        }

        if ($tipo === 'todos' || $tipo === 'notificacao') {
            $this->info('📧 Testando logs de notificação...');
            
            $tiposNotificacao = ['email', 'whatsapp'];
            $resultados = ['sucesso', 'erro'];
            
            foreach ($tiposNotificacao as $tipoNotif) {
                foreach ($resultados as $resultado) {
                    $nivel = $resultado === 'sucesso' ? 
                        LoggingService::NIVEL_INFO : 
                        LoggingService::NIVEL_ERROR;
                    
                    $loggingService->logNotificacao($nivel, 
                        "Teste de notificação {$tipoNotif} - {$resultado}", [
                        'tipo' => $tipoNotif,
                        'destinatario' => 'teste@exemplo.com',
                        'resultado' => $resultado,
                        'ambiente' => 'teste'
                    ]);
                }
            }
        }

        if ($tipo === 'todos' || $tipo === 'processamento') {
            $this->info('🔄 Testando logs de processamento...');
            
            $eventos = [
                ['nivel' => LoggingService::NIVEL_INFO, 'msg' => 'PDF carregado com sucesso'],
                ['nivel' => LoggingService::NIVEL_INFO, 'msg' => 'Texto extraído do PDF'],
                ['nivel' => LoggingService::NIVEL_WARNING, 'msg' => 'PDF com qualidade baixa detectado'],
                ['nivel' => LoggingService::NIVEL_ERROR, 'msg' => 'Erro ao processar página 15 do PDF'],
                ['nivel' => LoggingService::NIVEL_INFO, 'msg' => 'Processamento concluído'],
            ];
            
            foreach ($eventos as $evento) {
                $loggingService->logProcessamentoPdf($evento['nivel'], $evento['msg'], [
                    'diario_id' => 999,
                    'nome_arquivo' => 'teste-diario.pdf',
                    'ambiente' => 'teste'
                ]);
            }
        }

        if ($tipo === 'todos' || $tipo === 'seguranca') {
            $this->info('🔒 Testando logs de segurança...');
            
            $eventosSeguranca = [
                'Tentativa de login falhada - usuário inexistente',
                'Múltiplas tentativas de login do mesmo IP',
                'Upload de arquivo suspeito bloqueado',
                'Acesso negado a recurso protegido',
            ];
            
            foreach ($eventosSeguranca as $evento) {
                $loggingService->logSeguranca($evento, LoggingService::NIVEL_WARNING, [
                    'ip' => '192.168.1.100',
                    'user_agent' => 'Test Browser',
                    'ambiente' => 'teste'
                ]);
            }
        }

        if ($tipo === 'todos' || $tipo === 'sistema') {
            $this->info('⚙️ Testando logs de sistema...');
            
            $eventsSistema = [
                ['nivel' => LoggingService::NIVEL_INFO, 'msg' => 'Sistema iniciado com sucesso'],
                ['nivel' => LoggingService::NIVEL_WARNING, 'msg' => 'Uso de memória acima de 80%'],
                ['nivel' => LoggingService::NIVEL_ERROR, 'msg' => 'Falha na conexão com serviço externo'],
                ['nivel' => LoggingService::NIVEL_CRITICAL, 'msg' => 'Banco de dados indisponível'],
            ];
            
            foreach ($eventsSistema as $evento) {
                $loggingService->logSistema($evento['nivel'], $evento['msg'], [
                    'memoria_usada' => '1.2GB',
                    'ambiente' => 'teste'
                ]);
            }
        }

        // Gerar estatísticas
        $this->info('📊 Obtendo estatísticas...');
        $stats = $loggingService->getEstatisticasLogs();
        
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Erros hoje', $stats['erros_hoje']],
                ['Usuários ativos', $stats['usuarios_ativos']],
                ['Notificações (tipos)', count($stats['notificacoes'])],
                ['Operações de performance', count($stats['performance'])],
            ]
        );

        $this->info('✅ Logs de teste gerados com sucesso!');
        $this->info('📋 Acesse Admin > Sistema > Logs & Auditoria para visualizar');

        return self::SUCCESS;
    }
}