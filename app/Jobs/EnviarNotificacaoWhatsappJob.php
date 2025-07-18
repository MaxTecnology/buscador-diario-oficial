<?php

namespace App\Jobs;

use App\Models\Ocorrencia;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnviarNotificacaoWhatsappJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min backoff exponencial

    private Ocorrencia $ocorrencia;
    private User $usuario;

    /**
     * Create a new job instance.
     */
    public function __construct(Ocorrencia $ocorrencia, User $usuario)
    {
        $this->ocorrencia = $ocorrencia;
        $this->usuario = $usuario;
        $this->tries = SystemConfig::get('notifications.whatsapp_retry_attempts', 3);
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Verificar se já foi notificado
            if ($this->ocorrencia->notificado_whatsapp) {
                Log::info('WhatsApp já enviado anteriormente', [
                    'ocorrencia_id' => $this->ocorrencia->id,
                    'usuario_id' => $this->usuario->id,
                ]);
                return;
            }

            // Verificar se notificações estão habilitadas
            if (!SystemConfig::get('notifications.whatsapp_enabled', true)) {
                Log::info('Notificações por WhatsApp estão desabilitadas');
                return;
            }

            // Verificar se está dentro do horário permitido
            if (!$this->isWithinWhatsappHours()) {
                Log::info('Fora do horário permitido para WhatsApp', [
                    'ocorrencia_id' => $this->ocorrencia->id,
                    'hora_atual' => now()->format('H:i'),
                ]);
                
                // Re-agendar para próximo horário permitido
                $proximoHorario = $this->getNextWhatsappTime();
                static::dispatch($this->ocorrencia, $this->usuario)->delay($proximoHorario);
                return;
            }

            // Verificar se usuário tem telefone
            if (empty($this->usuario->telefone)) {
                Log::warning('Usuário não possui telefone cadastrado', [
                    'usuario_id' => $this->usuario->id,
                    'ocorrencia_id' => $this->ocorrencia->id,
                ]);
                return;
            }

            Log::info('Enviando notificação por WhatsApp', [
                'ocorrencia_id' => $this->ocorrencia->id,
                'usuario_id' => $this->usuario->id,
                'telefone' => $this->usuario->telefone,
                'empresa' => $this->ocorrencia->empresa->nome,
                'attempt' => $this->attempts(),
            ]);

            // Preparar mensagem
            $mensagem = $this->formatarMensagem();

            // Enviar via webhook
            $response = $this->enviarViaWebhook($mensagem);

            if ($response['sucesso']) {
                // Marcar como notificado
                $this->ocorrencia->marcarComoNotificadoPorWhatsapp();

                Log::info('WhatsApp enviado com sucesso', [
                    'ocorrencia_id' => $this->ocorrencia->id,
                    'usuario_telefone' => $this->usuario->telefone,
                    'empresa' => $this->ocorrencia->empresa->nome,
                    'response_id' => $response['message_id'] ?? null,
                ]);
            } else {
                throw new \Exception('Falha no envio do WhatsApp: ' . $response['erro']);
            }

        } catch (\Exception $e) {
            Log::error('Erro ao enviar WhatsApp de notificação', [
                'ocorrencia_id' => $this->ocorrencia->id,
                'usuario_id' => $this->usuario->id,
                'attempt' => $this->attempts(),
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function formatarMensagem(): string
    {
        $empresa = $this->ocorrencia->empresa;
        $diario = $this->ocorrencia->diario;
        
        // Mensagem otimizada para mobile
        $mensagem = "🚨 *DIÁRIO OFICIAL - EMPRESA ENCONTRADA*\n\n";
        $mensagem .= "📋 *Empresa:* {$empresa->nome}\n";
        $mensagem .= "📅 *Data:* " . $diario->data_diario->format('d/m/Y') . "\n";
        $mensagem .= "🏛️ *Estado:* " . strtoupper($diario->estado) . "\n";
        $mensagem .= "🎯 *Tipo:* " . ucfirst(str_replace('_', ' ', $this->ocorrencia->tipo_match)) . "\n";
        $mensagem .= "📊 *Confiança:* " . number_format($this->ocorrencia->score_confianca * 100, 1) . "%\n\n";
        
        // Contexto resumido (máximo 200 chars para WhatsApp)
        $contexto = $this->ocorrencia->contexto_completo;
        if (strlen($contexto) > 200) {
            $contexto = substr($contexto, 0, 197) . '...';
        }
        $mensagem .= "📄 *Contexto:*\n_{$contexto}_\n\n";
        
        $mensagem .= "🔗 Acesse o sistema para mais detalhes";
        
        return $mensagem;
    }

    private function enviarViaWebhook(string $mensagem): array
    {
        $webhookUrl = SystemConfig::get('notifications.whatsapp_webhook_url');
        $token = SystemConfig::get('notifications.whatsapp_token');

        if (empty($webhookUrl)) {
            throw new \Exception('URL do webhook WhatsApp não configurada');
        }

        // Limpar telefone para formato internacional
        $telefone = $this->limparTelefone($this->usuario->telefone);

        $payload = [
            'phone' => $telefone,
            'message' => $mensagem,
            'isGroup' => false,
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (!empty($token)) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($webhookUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'sucesso' => true,
                    'message_id' => $data['id'] ?? null,
                    'response' => $data,
                ];
            } else {
                return [
                    'sucesso' => false,
                    'erro' => "HTTP {$response->status()}: " . $response->body(),
                ];
            }

        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage(),
            ];
        }
    }

    private function limparTelefone(string $telefone): string
    {
        // Remover caracteres especiais
        $telefone = preg_replace('/[^0-9]/', '', $telefone);
        
        // Adicionar código do país se não tiver (Brasil = 55)
        if (strlen($telefone) === 11 && $telefone[0] === '0') {
            $telefone = '55' . substr($telefone, 1);
        } elseif (strlen($telefone) === 10) {
            $telefone = '55' . $telefone;
        } elseif (strlen($telefone) === 11 && !str_starts_with($telefone, '55')) {
            $telefone = '55' . $telefone;
        }

        return $telefone;
    }

    private function isWithinWhatsappHours(): bool
    {
        $horaInicio = SystemConfig::get('notifications.whatsapp_timeout_start', '08:00');
        $horaFim = SystemConfig::get('notifications.whatsapp_timeout_end', '22:00');
        $horaAtual = now()->format('H:i');

        return $horaAtual >= $horaInicio && $horaAtual <= $horaFim;
    }

    private function getNextWhatsappTime(): \Carbon\Carbon
    {
        $horaInicio = SystemConfig::get('notifications.whatsapp_timeout_start', '08:00');
        
        $amanha = now()->addDay()->setTimeFromTimeString($horaInicio);
        
        // Se for final de semana, agendar para segunda
        if ($amanha->isWeekend()) {
            $amanha = $amanha->next(\Carbon\Carbon::MONDAY)->setTimeFromTimeString($horaInicio);
        }

        return $amanha;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de WhatsApp falhou definitivamente', [
            'ocorrencia_id' => $this->ocorrencia->id,
            'usuario_id' => $this->usuario->id,
            'telefone' => $this->usuario->telefone,
            'erro' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Log para investigação manual
        activity()
            ->causedBy($this->usuario)
            ->performedOn($this->ocorrencia)
            ->withProperties([
                'erro' => $exception->getMessage(),
                'tentativas' => $this->attempts(),
                'tipo' => 'falha_whatsapp',
                'telefone' => $this->usuario->telefone,
            ])
            ->log('Falha definitiva no envio de WhatsApp');
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'notification:whatsapp',
            'ocorrencia:' . $this->ocorrencia->id,
            'user:' . $this->usuario->id,
            'empresa:' . $this->ocorrencia->empresa_id,
        ];
    }
}
