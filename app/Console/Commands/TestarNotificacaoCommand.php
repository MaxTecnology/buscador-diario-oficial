<?php

namespace App\Console\Commands;

use App\Models\Ocorrencia;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class TestarNotificacaoCommand extends Command
{
    protected $signature = 'notificacao:testar {ocorrencia-id}';
    
    protected $description = 'Testar notificações (email + WhatsApp) para uma ocorrência específica';

    public function handle(): int
    {
        $ocorrenciaId = $this->argument('ocorrencia-id');
        
        $this->info("🔍 Buscando ocorrência ID: {$ocorrenciaId}");

        $ocorrencia = Ocorrencia::with(['empresa', 'diario'])->find($ocorrenciaId);
        
        if (!$ocorrencia) {
            $this->error("❌ Ocorrência não encontrada!");
            return self::FAILURE;
        }

        $this->info("📋 Dados da ocorrência:");
        $this->line("   Empresa: {$ocorrencia->empresa->nome}");
        $this->line("   CNPJ: {$ocorrencia->empresa->cnpj}");
        $this->line("   Diário: {$ocorrencia->diario->nome_arquivo}");
        $this->line("   Score: {$ocorrencia->score}%");
        $this->line("   Termo encontrado: {$ocorrencia->termo_encontrado}");

        // Verificar usuários que podem receber notificações
        $notificationService = new NotificationService();
        
        // Usar reflection para acessar método protegido
        $reflection = new \ReflectionClass($notificationService);
        $method = $reflection->getMethod('getUsersForNotification');
        $method->setAccessible(true);
        $users = $method->invoke($notificationService, $ocorrencia);

        $this->info("👥 Usuários que receberão notificações: {$users->count()}");
        
        if ($users->isEmpty()) {
            $this->warn("⚠️ Nenhum usuário configurado para receber notificações desta empresa!");
            $this->line("Verifique se:");
            $this->line("  - O usuário está associado à empresa");
            $this->line("  - O campo 'pode_receber_whatsapp' está marcado na associação");
            $this->line("  - O usuário tem 'aceita_whatsapp' = true");
            $this->line("  - O usuário tem telefone_whatsapp preenchido");
            return self::FAILURE;
        }

        foreach ($users as $user) {
            $this->line("  - {$user->name} ({$user->email}) - WhatsApp: {$user->telefone_whatsapp}");
        }

        // Verificar configurações do sistema
        $whatsappEnabled = \App\Models\SystemConfig::getValue('notifications.whatsapp_enabled', false);
        $emailEnabled = \App\Models\SystemConfig::getValue('notifications.email_enabled', false);

        $this->info("⚙️ Configurações do sistema:");
        $this->line("   WhatsApp habilitado: " . ($whatsappEnabled ? '✅ SIM' : '❌ NÃO'));
        $this->line("   Email habilitado: " . ($emailEnabled ? '✅ SIM' : '❌ NÃO'));

        if (!$whatsappEnabled && !$emailEnabled) {
            $this->error("❌ Nenhum tipo de notificação está habilitado!");
            return self::FAILURE;
        }

        // Perguntar se deve prosseguir
        if (!$this->confirm('Deseja enviar as notificações agora?')) {
            $this->info("🚫 Cancelado pelo usuário.");
            return self::SUCCESS;
        }

        // Resetar flags de notificação para permitir reenvio
        $this->info("🔄 Resetando flags de notificação...");
        $ocorrencia->update([
            'notificado_email' => false,
            'notificado_whatsapp' => false
        ]);

        // Enviar notificações
        $this->info("📤 Enviando notificações...");
        
        try {
            $notificationService->notifyOcorrencia($ocorrencia);
            
            $this->info("✅ Processo de notificação iniciado!");
            $this->line("");
            $this->info("📊 Para verificar o resultado:");
            $this->line("  1. Acesse Admin > Sistema > Timeline de Atividades");
            $this->line("  2. Filtre por 'Notificação' para ver os logs");
            $this->line("  3. Para email: acesse http://localhost:8025");
            $this->line("  4. Para WhatsApp: verifique o telefone configurado");
            $this->line("");
            $this->info("🔍 Status da ocorrência após processamento:");
            
            // Recarregar ocorrência para ver status atualizado
            $ocorrencia->refresh();
            $this->line("   Email enviado: " . ($ocorrencia->notificado_email ? '✅ SIM' : '❌ NÃO'));
            $this->line("   WhatsApp enviado: " . ($ocorrencia->notificado_whatsapp ? '✅ SIM' : '❌ NÃO'));

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Erro ao enviar notificações: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}