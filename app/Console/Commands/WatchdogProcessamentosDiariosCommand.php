<?php

namespace App\Console\Commands;

use App\Jobs\ProcessarPdfJob;
use App\Models\Diario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WatchdogProcessamentosDiariosCommand extends Command
{
    protected $signature = 'diarios:watchdog-processamentos {--dry-run : Apenas exibe o que seria feito, sem alterar dados}';

    protected $description = 'Detecta diários presos em "processando", encerra os processamentos órfãos e re-enfileira quando permitido.';

    public function handle(): int
    {
        $stuckMinutes = max(5, (int) env('PDF_WATCHDOG_STUCK_MINUTES', 20));
        $maxRetries = max(0, (int) env('PDF_WATCHDOG_MAX_RETRIES', 3));
        $autoRequeue = filter_var((string) env('PDF_WATCHDOG_AUTO_REQUEUE', true), FILTER_VALIDATE_BOOL);
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMinutes($stuckMinutes);

        $stuckDiarios = Diario::query()
            ->where('status', 'processando')
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('updated_at')
            ->get();

        if ($stuckDiarios->isEmpty()) {
            $this->info("Nenhum diário travado encontrado (limite: {$stuckMinutes} minutos).");
            return self::SUCCESS;
        }

        $this->warn("Encontrados {$stuckDiarios->count()} diário(s) travado(s) em processamento.");

        $requeued = 0;
        $markedError = 0;

        foreach ($stuckDiarios as $diario) {
            $tentativas = (int) ($diario->tentativas ?? 0);
            $canRetry = $autoRequeue && ($tentativas < $maxRetries);

            $mensagem = sprintf(
                '[Watchdog] Diario #%d (%s) travado desde %s | tentativas=%d | ação=%s',
                $diario->id,
                $diario->nome_arquivo,
                optional($diario->updated_at)->toDateTimeString(),
                $tentativas,
                $canRetry ? 'requeue' : 'mark_error'
            );

            $this->line($mensagem);
            Log::warning($mensagem);

            if ($dryRun) {
                continue;
            }

            if (Schema::hasTable('diario_processamentos')) {
                $now = now();
                $erro = sprintf(
                    'Processamento encerrado pelo watchdog após %d minutos sem atualização.',
                    $stuckMinutes
                );

                $diario->processamentos()
                    ->where('status', 'processando')
                    ->update([
                        'status' => 'erro',
                        'finalizado_em' => $now,
                        'erro_mensagem' => $erro,
                        'updated_at' => $now,
                    ]);
            }

            if ($canRetry) {
                $diario->update([
                    'status' => 'pendente',
                    'status_processamento' => 'pendente',
                    'erro_mensagem' => 'Reenfileirado automaticamente pelo watchdog.',
                    'erro_processamento' => 'Reenfileirado automaticamente pelo watchdog.',
                ]);

                ProcessarPdfJob::dispatch($diario->fresh(), [
                    'tipo' => 'reprocessamento',
                    'modo' => 'completo',
                    'motivo' => 'Watchdog: diário travado em processamento',
                    'notificar' => false,
                    'limpar_ocorrencias_anteriores' => true,
                ]);

                $requeued++;
                continue;
            }

            $diario->update([
                'status' => 'erro',
                'status_processamento' => 'erro',
                'erro_mensagem' => sprintf(
                    'Watchdog encerrou após travamento (%d min) e limite de tentativas atingido.',
                    $stuckMinutes
                ),
                'erro_processamento' => 'Reprocessamento automático bloqueado pelo limite de tentativas.',
            ]);

            $markedError++;
        }

        if ($dryRun) {
            $this->info('Dry-run concluído: nenhuma alteração foi persistida.');
            return self::SUCCESS;
        }

        $this->info("Watchdog concluído. Reenfileirados: {$requeued}. Marcados como erro: {$markedError}.");

        return self::SUCCESS;
    }
}

