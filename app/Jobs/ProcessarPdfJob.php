<?php

namespace App\Jobs;

use App\Models\Diario;
use App\Models\DiarioProcessamento;
use App\Models\User;
use App\Services\PdfProcessorService;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
        public Diario $diario,
        public array $opcoes = [],
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $processamento = null;

        try {
            $opcoes = $this->normalizarOpcoes();

            Log::info("Iniciando processamento assíncrono do PDF: {$this->diario->nome_arquivo}");
            $this->diario->update([
                'status' => 'processando',
                'status_processamento' => 'processando',
                'erro_mensagem' => null,
                'erro_processamento' => null,
            ]);

            if (Schema::hasTable('diario_processamentos')) {
                $processamento = DiarioProcessamento::create([
                    'diario_id' => $this->diario->id,
                    'iniciado_por_user_id' => $opcoes['iniciado_por_user_id'],
                    'tipo' => $opcoes['tipo'],
                    'modo' => $opcoes['modo'],
                    'status' => 'processando',
                    'motivo' => $opcoes['motivo'],
                    'notificar' => $opcoes['notificar'],
                    'limpar_ocorrencias_anteriores' => $opcoes['limpar_ocorrencias_anteriores'],
                    'iniciado_em' => now(),
                    'meta' => [
                        'job' => static::class,
                        'queue' => $this->queue,
                    ],
                ]);
            }

            $processorService = new PdfProcessorService();
            $resultado = $processorService->processarPdf($this->diario, [
                'diario_processamento_id' => $processamento?->id,
                'ocorrencias_ativas' => ! $opcoes['limpar_ocorrencias_anteriores'],
                'enviar_notificacoes' => $opcoes['notificar'],
            ]);
            
            if ($resultado['sucesso']) {
                $consolidacao = $this->consolidarOcorrenciasProcessamento($processamento, $resultado);

                if ($processamento) {
                    $processamento->update([
                        'status' => 'concluido',
                        'finalizado_em' => now(),
                        'erro_mensagem' => null,
                        'total_ocorrencias' => (int) ($consolidacao['total_ocorrencias'] ?? 0),
                        'novas_ocorrencias' => (int) ($consolidacao['novas_ocorrencias'] ?? 0),
                        'ocorrencias_desativadas' => (int) ($consolidacao['ocorrencias_desativadas'] ?? 0),
                        'meta' => array_merge($processamento->meta ?? [], [
                            'resultado' => [
                                'texto_extraido' => $resultado['texto_extraido'] ?? null,
                                'ocorrencias_enfileiradas_notificacao' => $opcoes['notificar'],
                            ],
                        ]),
                    ]);
                }

                Log::info("PDF processado com sucesso: {$this->diario->nome_arquivo}. Ocorrências: {$resultado['ocorrencias_encontradas']}");
                $this->enviarNotificacaoPainel(
                    tipo: 'success',
                    titulo: 'Diário processado',
                    corpo: "{$this->diario->nome_arquivo} concluído. Ocorrências: {$resultado['ocorrencias_encontradas']}."
                );
            } else {
                if ($processamento) {
                    $processamento->update([
                        'status' => 'erro',
                        'finalizado_em' => now(),
                        'erro_mensagem' => (string) ($resultado['erro'] ?? 'Erro não informado'),
                    ]);
                }

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

            if ($processamento) {
                $processamento->update([
                    'status' => 'erro',
                    'finalizado_em' => now(),
                    'erro_mensagem' => $e->getMessage(),
                ]);
            }
            
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

        if (Schema::hasTable('diario_processamentos')) {
            DiarioProcessamento::query()
                ->where('diario_id', $this->diario->id)
                ->whereIn('status', ['pendente', 'processando'])
                ->latest('id')
                ->first()
                ?->update([
                    'status' => 'erro',
                    'finalizado_em' => now(),
                    'erro_mensagem' => $exception->getMessage(),
                ]);
        }

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

    private function normalizarOpcoes(): array
    {
        $tipo = $this->opcoes['tipo'] ?? (
            (Schema::hasTable('diario_processamentos') && $this->diario->processamentos()->exists()) ? 'reprocessamento' : 'inicial'
        );

        $modo = $this->opcoes['modo'] ?? 'completo';

        return [
            'tipo' => in_array($tipo, ['inicial', 'reprocessamento'], true) ? $tipo : 'inicial',
            'modo' => in_array($modo, ['completo', 'somente_busca'], true) ? $modo : 'completo',
            'motivo' => isset($this->opcoes['motivo']) ? trim((string) $this->opcoes['motivo']) : null,
            'notificar' => (bool) ($this->opcoes['notificar'] ?? ($tipo === 'inicial')),
            'limpar_ocorrencias_anteriores' => (bool) ($this->opcoes['limpar_ocorrencias_anteriores'] ?? true),
            'iniciado_por_user_id' => isset($this->opcoes['iniciado_por_user_id'])
                ? (int) $this->opcoes['iniciado_por_user_id']
                : null,
        ];
    }

    private function consolidarOcorrenciasProcessamento(?DiarioProcessamento $processamento, array $resultado): array
    {
        if (! $processamento || ! Schema::hasColumn('ocorrencias', 'diario_processamento_id')) {
            return [
                'total_ocorrencias' => (int) ($resultado['ocorrencias_encontradas'] ?? 0),
                'novas_ocorrencias' => (int) ($resultado['ocorrencias_encontradas'] ?? 0),
                'ocorrencias_desativadas' => 0,
            ];
        }

        $novasOcorrencias = 0;
        $ocorrenciasDesativadas = 0;

        DB::transaction(function () use ($processamento, &$novasOcorrencias, &$ocorrenciasDesativadas): void {
            $queryNovas = $this->diario->ocorrencias()
                ->where('diario_processamento_id', $processamento->id);

            $novasOcorrencias = (clone $queryNovas)->count();

            if (Schema::hasColumn('ocorrencias', 'ativo')) {
                if ($processamento->limpar_ocorrencias_anteriores) {
                    $ocorrenciasDesativadas = $this->diario->ocorrencias()
                        ->where('ativo', true)
                        ->where(function ($query) use ($processamento): void {
                            $query->whereNull('diario_processamento_id')
                                ->orWhere('diario_processamento_id', '!=', $processamento->id);
                        })
                        ->update(['ativo' => false]);
                }

                (clone $queryNovas)->update(['ativo' => true]);
            }
        });

        return [
            'total_ocorrencias' => $novasOcorrencias,
            'novas_ocorrencias' => $novasOcorrencias,
            'ocorrencias_desativadas' => $ocorrenciasDesativadas,
        ];
    }
}
