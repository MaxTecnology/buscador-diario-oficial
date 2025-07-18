<?php

namespace App\Jobs;

use App\Mail\EmpresaEncontradaMail;
use App\Models\Ocorrencia;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarNotificacaoEmailJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries;
    public int $backoff = 300; // 5 minutos entre tentativas

    private Ocorrencia $ocorrencia;
    private User $usuario;

    /**
     * Create a new job instance.
     */
    public function __construct(Ocorrencia $ocorrencia, User $usuario)
    {
        $this->ocorrencia = $ocorrencia;
        $this->usuario = $usuario;
        $this->tries = SystemConfig::get('notifications.email_retry_attempts', 3);
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Verificar se já foi notificado
            if ($this->ocorrencia->notificado_email) {
                Log::info('Email já enviado anteriormente', [
                    'ocorrencia_id' => $this->ocorrencia->id,
                    'usuario_id' => $this->usuario->id,
                ]);
                return;
            }

            // Verificar se notificações estão habilitadas
            if (!SystemConfig::get('notifications.email_enabled', true)) {
                Log::info('Notificações por email estão desabilitadas');
                return;
            }

            Log::info('Enviando notificação por email', [
                'ocorrencia_id' => $this->ocorrencia->id,
                'usuario_id' => $this->usuario->id,
                'empresa' => $this->ocorrencia->empresa->nome,
                'attempt' => $this->attempts(),
            ]);

            // Enviar email
            Mail::to($this->usuario->email)->send(new EmpresaEncontradaMail($this->ocorrencia, $this->usuario));

            // Marcar como notificado
            $this->ocorrencia->marcarComoNotificadoPorEmail();

            Log::info('Email enviado com sucesso', [
                'ocorrencia_id' => $this->ocorrencia->id,
                'usuario_email' => $this->usuario->email,
                'empresa' => $this->ocorrencia->empresa->nome,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao enviar email de notificação', [
                'ocorrencia_id' => $this->ocorrencia->id,
                'usuario_id' => $this->usuario->id,
                'attempt' => $this->attempts(),
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de email falhou definitivamente', [
            'ocorrencia_id' => $this->ocorrencia->id,
            'usuario_id' => $this->usuario->id,
            'erro' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Opcionalmente, criar uma entrada de log para investigação manual
        activity()
            ->causedBy($this->usuario)
            ->performedOn($this->ocorrencia)
            ->withProperties([
                'erro' => $exception->getMessage(),
                'tentativas' => $this->attempts(),
                'tipo' => 'falha_email',
            ])
            ->log('Falha definitiva no envio de email');
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'notification:email',
            'ocorrencia:' . $this->ocorrencia->id,
            'user:' . $this->usuario->id,
            'empresa:' . $this->ocorrencia->empresa_id,
        ];
    }
}
