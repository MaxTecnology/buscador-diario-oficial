<?php

namespace App\Jobs;

use App\Models\Diario;
use App\Models\SystemConfig;
use App\Services\DiarioProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessarDiarioJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout;
    public int $tries = 3;
    public int $backoff = 60; // 1 minuto entre tentativas

    private Diario $diario;

    /**
     * Create a new job instance.
     */
    public function __construct(Diario $diario)
    {
        $this->diario = $diario;
        $this->timeout = SystemConfig::get('processing.timeout', 300); // 5 minutos padrão
        $this->onQueue('pdf-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(DiarioProcessingService $processingService): void
    {
        Log::info('Iniciando job de processamento de diário', [
            'diario_id' => $this->diario->id,
            'arquivo' => $this->diario->nome_arquivo,
            'attempt' => $this->attempts(),
        ]);

        try {
            $resultado = $processingService->processarDiarioCompleto($this->diario);

            if ($resultado['sucesso']) {
                Log::info('Job de processamento concluído com sucesso', [
                    'diario_id' => $this->diario->id,
                    'ocorrencias_encontradas' => $resultado['ocorrencias_encontradas'],
                    'empresas_encontradas' => $resultado['empresas_encontradas'],
                    'tempo_total_ms' => $resultado['tempo_total_ms'],
                ]);

                // Disparar jobs de notificação se houver ocorrências
                if ($resultado['ocorrencias_encontradas'] > 0) {
                    $this->dispatchNotificationJobs();
                }
            } else {
                throw new \Exception('Processamento falhou: ' . implode(', ', $resultado['erros']));
            }

        } catch (\Exception $e) {
            Log::error('Erro no job de processamento de diário', [
                'diario_id' => $this->diario->id,
                'attempt' => $this->attempts(),
                'erro' => $e->getMessage(),
            ]);

            // Se excedeu tentativas, marcar como erro final
            if ($this->attempts() >= $this->tries) {
                $this->diario->marcarComoErro("Job falhou após {$this->tries} tentativas: " . $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de processamento de diário falhou definitivamente', [
            'diario_id' => $this->diario->id,
            'erro' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->diario->marcarComoErro("Job falhou definitivamente: " . $exception->getMessage());
    }

    private function dispatchNotificationJobs(): void
    {
        $ocorrencias = $this->diario->ocorrencias()
            ->with(['empresa.users'])
            ->where('notificado_email', false)
            ->orWhere('notificado_whatsapp', false)
            ->get();

        foreach ($ocorrencias as $ocorrencia) {
            // Agrupar usuários por empresa para evitar spam
            $usuarios = $ocorrencia->empresa->users()
                ->wherePivot('pode_receber_email', true)
                ->orWherePivot('pode_receber_whatsapp', true)
                ->get();

            foreach ($usuarios as $usuario) {
                $permissions = $usuario->pivot;

                // Email
                if ($permissions->pode_receber_email && SystemConfig::get('notifications.email_enabled', true)) {
                    EnviarNotificacaoEmailJob::dispatch($ocorrencia, $usuario)
                        ->onQueue('notifications')
                        ->delay(now()->addSeconds(5)); // Pequeno delay para não sobrecarregar
                }

                // WhatsApp
                if ($permissions->pode_receber_whatsapp && SystemConfig::get('notifications.whatsapp_enabled', true)) {
                    if ($this->isWithinWhatsappHours()) {
                        EnviarNotificacaoWhatsappJob::dispatch($ocorrencia, $usuario)
                            ->onQueue('notifications')
                            ->delay(now()->addSeconds(10));
                    } else {
                        // Agendar para próximo horário permitido
                        $proximoHorario = $this->getNextWhatsappTime();
                        EnviarNotificacaoWhatsappJob::dispatch($ocorrencia, $usuario)
                            ->onQueue('notifications')
                            ->delay($proximoHorario);
                    }
                }
            }
        }
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
        $proximoDia = now()->addDay()->setTimeFromTimeString($horaInicio);

        return $proximoDia;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'diario:' . $this->diario->id,
            'estado:' . $this->diario->estado,
            'pdf-processing',
        ];
    }
}
