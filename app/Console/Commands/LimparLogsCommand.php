<?php

namespace App\Console\Commands;

use App\Services\LoggingService;
use Illuminate\Console\Command;

class LimparLogsCommand extends Command
{
    protected $signature = 'logs:limpar {--dias=30 : Número de dias para manter}';
    
    protected $description = 'Limpar logs antigos do sistema';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        
        if ($dias < 7) {
            $this->error('❌ Não é permitido limpar logs com menos de 7 dias!');
            return self::FAILURE;
        }

        $this->info("🧹 Limpando logs com mais de {$dias} dias...");

        $loggingService = new LoggingService();
        $arquivosRemovidos = $loggingService->limparLogsAntigos($dias);

        $this->info("✅ Limpeza concluída!");
        $this->info("📁 {$arquivosRemovidos} arquivo(s) de log removido(s)");

        return self::SUCCESS;
    }
}