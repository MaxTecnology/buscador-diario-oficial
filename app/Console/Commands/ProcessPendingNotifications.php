<?php

namespace App\Console\Commands;

use App\Models\Ocorrencia;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class ProcessPendingNotifications extends Command
{
    protected $signature = 'notifications:process-pending {--limit=50 : Limite de notificações a processar}';
    protected $description = 'Processa notificações pendentes de ocorrências';

    public function handle(NotificationService $notificationService)
    {
        $limit = $this->option('limit');
        
        $this->info("Processando notificações pendentes (limite: {$limit})...");

        $ocorrenciasPendentes = Ocorrencia::where('notificado_whatsapp', false)
            ->with(['empresa', 'diario'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($ocorrenciasPendentes->isEmpty()) {
            $this->info('Nenhuma notificação pendente encontrada.');
            return 0;
        }

        $this->info("Encontradas {$ocorrenciasPendentes->count()} ocorrências pendentes.");

        $processadas = 0;
        $erros = 0;

        foreach ($ocorrenciasPendentes as $ocorrencia) {
            try {
                $this->line("Processando ocorrência {$ocorrencia->id} - {$ocorrencia->empresa->nome}");
                
                $notificationService->notifyOcorrencia($ocorrencia);
                $processadas++;
                
                $this->info("✅ Ocorrência {$ocorrencia->id} processada");
            } catch (\Exception $e) {
                $erros++;
                $this->error("❌ Erro ao processar ocorrência {$ocorrencia->id}: {$e->getMessage()}");
            }
        }

        $this->info("\n📊 Resumo:");
        $this->info("✅ Processadas: {$processadas}");
        $this->info("❌ Erros: {$erros}");

        return 0;
    }
}