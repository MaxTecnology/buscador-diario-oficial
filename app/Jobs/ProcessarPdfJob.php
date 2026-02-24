<?php

namespace App\Jobs;

use App\Models\Diario;
use App\Models\User;
use App\Services\PdfProcessorService;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessarPdfJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 600; // 10 minutos
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Diario $diario
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Iniciando processamento assíncrono do PDF: {$this->diario->nome_arquivo}");
            $this->diario->update([
                'status' => 'processando',
                'status_processamento' => 'processando',
                'erro_mensagem' => null,
                'erro_processamento' => null,
            ]);

            $processorService = new PdfProcessorService();
            $resultado = $processorService->processarPdf($this->diario);
            
            if ($resultado['sucesso']) {
                Log::info("PDF processado com sucesso: {$this->diario->nome_arquivo}. Ocorrências: {$resultado['ocorrencias_encontradas']}");
                $this->enviarNotificacaoPainel(
                    tipo: 'success',
                    titulo: 'Diário processado',
                    corpo: "{$this->diario->nome_arquivo} concluído. Ocorrências: {$resultado['ocorrencias_encontradas']}."
                );
            } else {
                Log::error("Erro no processamento do PDF: {$this->diario->nome_arquivo}. Erro: {$resultado['erro']}");
                $this->enviarNotificacaoPainel(
                    tipo: 'danger',
                    titulo: 'Erro no processamento do diário',
                    corpo: "{$this->diario->nome_arquivo}: {$resultado['erro']}"
                );
            }
            
        } catch (\Throwable $e) {
            Log::error("Falha no job de processamento de PDF: " . $e->getMessage(), [
                'diario_id' => $this->diario->id,
                'arquivo' => $this->diario->nome_arquivo,
                'tipo_erro' => get_class($e),
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job de processamento de PDF falhou definitivamente", [
            'diario_id' => $this->diario->id,
            'arquivo' => $this->diario->nome_arquivo,
            'erro' => $exception->getMessage()
        ]);
        
        $this->diario->update([
            'status' => 'erro',
            'status_processamento' => 'erro',
            'erro_mensagem' => 'Falha no processamento: ' . $exception->getMessage(),
            'erro_processamento' => $exception->getMessage(),
        ]);

        $this->enviarNotificacaoPainel(
            tipo: 'danger',
            titulo: 'Falha definitiva no processamento',
            corpo: "{$this->diario->nome_arquivo}: {$exception->getMessage()}"
        );
    }

    private function enviarNotificacaoPainel(string $tipo, string $titulo, string $corpo): void
    {
        try {
            if (! Schema::hasTable('notifications')) {
                return;
            }

            $diario = $this->diario->fresh(['usuario']);
            $usuarioUpload = $diario?->usuario;

            $recipients = User::query()
                ->where('pode_fazer_login', true)
                ->role('admin')
                ->get();

            if ($usuarioUpload && $usuarioUpload->pode_fazer_login) {
                $recipients->prepend($usuarioUpload);
            }

            $recipients = $recipients
                ->unique('id')
                ->values();

            if ($recipients->isEmpty()) {
                return;
            }

            $notification = FilamentNotification::make()
                ->title($titulo)
                ->body($corpo);

            match ($tipo) {
                'success' => $notification->success(),
                'danger' => $notification->danger(),
                'warning' => $notification->warning(),
                default => $notification->info(),
            };

            $notification->sendToDatabase($recipients, true);
        } catch (\Throwable $e) {
            Log::warning('Não foi possível enviar notificação do painel após processamento.', [
                'diario_id' => $this->diario->id ?? null,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
