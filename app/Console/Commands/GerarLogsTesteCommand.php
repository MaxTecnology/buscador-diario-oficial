<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\Diario;
use App\Models\Empresa;
use App\Models\Ocorrencia;
use Illuminate\Console\Command;

class GerarLogsTesteCommand extends Command
{
    protected $signature = 'logs:gerar-teste {--quantidade=20}';
    
    protected $description = 'Gerar logs de teste para demonstrar o sistema de auditoria';

    public function handle(): int
    {
        $quantidade = (int) $this->option('quantidade');
        
        $this->info("🔍 Gerando {$quantidade} logs de teste...");

        // Buscar dados existentes para os logs
        $usuarios = User::all();
        $diarios = Diario::with('ocorrencias')->get();
        $empresas = Empresa::all();

        if ($usuarios->isEmpty()) {
            $this->error('❌ Nenhum usuário encontrado. Execute os seeders primeiro.');
            return self::FAILURE;
        }

        $this->info('📋 Gerando logs de atividade...');
        $this->gerarLogsAtividade($quantidade, $usuarios, $diarios, $empresas);

        $this->info('📧 Gerando logs de notificação...');
        $this->gerarLogsNotificacao($quantidade / 2, $usuarios, $diarios, $empresas);

        $this->info('✅ Logs de teste gerados com sucesso!');
        $this->info('📊 Acesse Admin > Sistema > Timeline de Atividades para visualizar');

        return self::SUCCESS;
    }

    private function gerarLogsAtividade(int $quantidade, $usuarios, $diarios, $empresas): void
    {
        $acoesPossiveis = [
            [
                'action' => 'created',
                'entity_type' => 'Diario',
                'description' => "Diário 'documento-teste-{rand}.pdf' foi enviado para processamento",
                'icon' => 'heroicon-o-document-plus',
                'color' => 'green'
            ],
            [
                'action' => 'processed',
                'entity_type' => 'Diario', 
                'description' => "Diário 'documento-teste-{rand}.pdf' processado: {rand} ocorrência(s) encontrada(s)",
                'icon' => 'heroicon-o-cog-6-tooth',
                'color' => 'blue'
            ],
            [
                'action' => 'deleted',
                'entity_type' => 'Diario',
                'description' => "Diário 'documento-antigo-{rand}.pdf' foi removido do sistema",
                'icon' => 'heroicon-o-trash',
                'color' => 'red'
            ],
            [
                'action' => 'created',
                'entity_type' => 'Empresa',
                'description' => "Empresa 'Empresa Teste {rand} LTDA' foi cadastrada no sistema",
                'icon' => 'heroicon-o-building-office',
                'color' => 'green'
            ],
            [
                'action' => 'login',
                'entity_type' => 'User',
                'description' => "Usuário fez login no sistema",
                'icon' => 'heroicon-o-arrow-right-on-rectangle',
                'color' => 'blue'
            ],
            [
                'action' => 'logout',
                'entity_type' => 'User',
                'description' => "Usuário fez logout do sistema",
                'icon' => 'heroicon-o-arrow-left-on-rectangle',
                'color' => 'gray'
            ],
        ];

        for ($i = 0; $i < $quantidade; $i++) {
            $usuario = $usuarios->random();
            $acao = $acoesPossiveis[array_rand($acoesPossiveis)];
            
            // Substituir placeholders
            $description = str_replace('{rand}', rand(100, 999), $acao['description']);
            
            // Selecionar entidade baseada no tipo
            $entityId = null;
            $entityName = null;
            
            switch ($acao['entity_type']) {
                case 'Diario':
                    if ($diarios->isNotEmpty()) {
                        $diario = $diarios->random();
                        $entityId = $diario->id;
                        $entityName = $diario->nome_arquivo;
                    }
                    break;
                case 'Empresa':
                    if ($empresas->isNotEmpty()) {
                        $empresa = $empresas->random();
                        $entityId = $empresa->id;
                        $entityName = $empresa->nome;
                    }
                    break;
                case 'User':
                    $entityId = $usuario->id;
                    $entityName = $usuario->name;
                    $description = "Usuário '{$usuario->name}' " . ($acao['action'] === 'login' ? 'fez login' : 'fez logout') . " no sistema";
                    break;
            }

            // Gerar timestamp aleatório nos últimos 30 dias
            $occurredAt = now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            ActivityLog::create([
                'user_id' => $usuario->id,
                'user_name' => $usuario->name,
                'user_email' => $usuario->email,
                'ip_address' => $this->getRandomIp(),
                'user_agent' => $this->getRandomUserAgent(),
                'action' => $acao['action'],
                'entity_type' => $acao['entity_type'],
                'entity_id' => $entityId,
                'entity_name' => $entityName,
                'description' => $description,
                'icon' => $acao['icon'],
                'color' => $acao['color'],
                'context' => $this->gerarContextoAleatorio($acao),
                'source' => rand(0, 1) ? 'web' : 'api',
                'session_id' => 'test_session_' . rand(1000, 9999),
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);

            if ($i % 5 === 0) {
                $this->info("📝 Gerados " . ($i + 1) . " logs de atividade...");
            }
        }
    }

    private function gerarLogsNotificacao(int $quantidade, $usuarios, $diarios, $empresas): void
    {
        $tipos = ['email', 'whatsapp'];
        $status = ['sent', 'failed'];
        
        for ($i = 0; $i < $quantidade; $i++) {
            $usuario = $usuarios->random();
            $tipo = $tipos[array_rand($tipos)];
            $statusNotif = $status[array_rand($status)];
            
            // Selecionar ocorrência aleatória se disponível
            $ocorrencia = null;
            $empresa = null;
            $diario = null;
            
            if ($diarios->isNotEmpty()) {
                $diarioSelecionado = $diarios->random();
                if ($diarioSelecionado->ocorrencias->isNotEmpty()) {
                    $ocorrencia = $diarioSelecionado->ocorrencias->random();
                    $empresa = $ocorrencia->empresa;
                    $diario = $ocorrencia->diario;
                }
            }
            
            if (!$empresa && $empresas->isNotEmpty()) {
                $empresa = $empresas->random();
            }

            $recipient = $tipo === 'email' ? 
                ($empresa?->email ?? 'teste@empresa.com') : 
                ($empresa?->telefone ?? '5511999999999');

            $message = $this->gerarMensagemTeste($tipo, $empresa?->nome ?? 'Empresa Teste');
            
            $timestamp = now()->subDays(rand(0, 15))->subHours(rand(0, 23));

            NotificationLog::create([
                'ocorrencia_id' => $ocorrencia?->id,
                'user_id' => $usuario->id,
                'empresa_id' => $empresa?->id,
                'diario_id' => $diario?->id,
                'type' => $tipo,
                'status' => $statusNotif,
                'recipient' => $recipient,
                'recipient_name' => $empresa?->nome ?? 'Empresa Teste',
                'subject' => $tipo === 'email' ? 'Empresa encontrada em Diário Oficial' : null,
                'message' => $message,
                'headers' => $this->gerarHeadersAleatorios($tipo),
                'external_id' => 'test_' . rand(10000, 99999),
                'error_message' => $statusNotif === 'failed' ? $this->gerarErroAleatorio($tipo) : null,
                'attempts' => $statusNotif === 'failed' ? rand(1, 3) : 1,
                'triggered_by' => rand(0, 1) ? 'manual' : 'automatic',
                'triggered_by_user_id' => $usuario->id,
                'ip_address' => $this->getRandomIp(),
                'sent_at' => $statusNotif === 'sent' ? $timestamp : null,
                'failed_at' => $statusNotif === 'failed' ? $timestamp : null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            if ($i % 3 === 0) {
                $this->info("📧 Gerados " . ($i + 1) . " logs de notificação...");
            }
        }
    }

    private function gerarContextoAleatorio(array $acao): array
    {
        $contextos = [
            'created' => [
                'tamanho_mb' => round(rand(1, 50) + (rand(0, 99) / 100), 2),
                'ambiente' => 'teste',
                'origem' => 'upload_manual'
            ],
            'processed' => [
                'tempo_processamento' => rand(5, 300),
                'tentativas' => rand(1, 3),
                'ambiente' => 'teste'
            ],
            'login' => [
                'ultimo_login' => now()->subDays(rand(1, 30))->format('d/m/Y H:i:s'),
                'dispositivo' => 'Desktop',
                'ambiente' => 'teste'
            ]
        ];

        return $contextos[$acao['action']] ?? ['ambiente' => 'teste'];
    }

    private function gerarMensagemTeste(string $tipo, string $nomeEmpresa): string
    {
        if ($tipo === 'email') {
            return "Olá!\n\nA empresa {$nomeEmpresa} foi encontrada em um diário oficial.\n\nAcesse o sistema para mais detalhes.\n\nAtenciosamente,\nSistema de Monitoramento";
        } else {
            return "🚨 *Empresa Encontrada!*\n\n📋 Empresa: {$nomeEmpresa}\n📄 Diário: documento-teste.pdf\n\n🔗 Acesse o sistema para mais detalhes";
        }
    }

    private function gerarHeadersAleatorios(string $tipo): array
    {
        if ($tipo === 'email') {
            return [
                'Message-ID' => '<test_' . rand(1000, 9999) . '@diario.com>',
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Mailer' => 'Sistema Diario v1.0'
            ];
        } else {
            return [
                'api_version' => '2.1',
                'instance' => 'test_instance',
                'webhook_url' => 'https://test.webhook.com'
            ];
        }
    }

    private function gerarErroAleatorio(string $tipo): string
    {
        if ($tipo === 'email') {
            $erros = [
                'SMTP connection failed',
                'Invalid email address',
                'Mailbox full',
                'Message rejected by server'
            ];
        } else {
            $erros = [
                'WhatsApp number not registered',
                'API rate limit exceeded',
                'Message template not found',
                'Connection timeout'
            ];
        }

        return $erros[array_rand($erros)];
    }

    private function getRandomIp(): string
    {
        return rand(192, 200) . '.' . rand(168, 192) . '.' . rand(1, 254) . '.' . rand(1, 254);
    }

    private function getRandomUserAgent(): string
    {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1'
        ];

        return $userAgents[array_rand($userAgents)];
    }
}