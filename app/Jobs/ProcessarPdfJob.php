<?php

namespace App\Jobs;

use App\Models\Diario;
use App\Services\PdfProcessorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
            
            $processorService = new PdfProcessorService();
            $resultado = $processorService->processarPdf($this->diario);
            
            if ($resultado['sucesso']) {
                Log::info("PDF processado com sucesso: {$this->diario->nome_arquivo}. Ocorrências: {$resultado['ocorrencias_encontradas']}");
            } else {
                Log::error("Erro no processamento do PDF: {$this->diario->nome_arquivo}. Erro: {$resultado['erro']}");
            }
            
        } catch (\Exception $e) {
            Log::error("Falha no job de processamento de PDF: " . $e->getMessage(), [
                'diario_id' => $this->diario->id,
                'arquivo' => $this->diario->nome_arquivo
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
            'erro_mensagem' => 'Falha no processamento: ' . $exception->getMessage()
        ]);
    }
}
