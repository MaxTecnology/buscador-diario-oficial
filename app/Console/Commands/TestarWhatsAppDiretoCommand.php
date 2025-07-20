<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class TestarWhatsAppDiretoCommand extends Command
{
    protected $signature = 'whatsapp:testar-direto {numero}';
    
    protected $description = 'Testar WhatsApp diretamente sem jobs';

    public function handle(): int
    {
        $numero = $this->argument('numero');
        
        $this->info("📱 Testando WhatsApp para: {$numero}");

        $whatsappService = new WhatsAppService();
        
        // Verificar configuração
        if (!$whatsappService->isConfigured()) {
            $this->error("❌ WhatsApp não configurado!");
            return self::FAILURE;
        }

        $this->info("✅ WhatsApp configurado");

        // Verificar horário comercial
        if (!$whatsappService->isBusinessHours()) {
            $this->warn("⚠️ Fora do horário comercial");
        } else {
            $this->info("✅ Dentro do horário comercial");
        }

        // Enviar mensagem
        $message = "🧪 *Teste direto do sistema*\n\n" .
                   "Este é um teste de funcionamento do WhatsApp.\n\n" .
                   "📅 " . now()->format('d/m/Y H:i:s') . "\n\n" .
                   "Sistema de Diários Oficiais";

        $this->info("📤 Enviando mensagem...");

        $result = $whatsappService->sendTextMessage($numero, $message);

        if ($result['success']) {
            $this->info("✅ Mensagem enviada com sucesso!");
            $this->line("Response: " . json_encode($result['response'], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        } else {
            $this->error("❌ Falha ao enviar mensagem");
            $this->line("Erro: " . $result['error']);
            if (isset($result['status'])) {
                $this->line("Status HTTP: " . $result['status']);
            }
            return self::FAILURE;
        }
    }
}