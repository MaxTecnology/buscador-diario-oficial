<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotification;
use App\Models\ConfiguracaoSistema;
use App\Models\Ocorrencia;
use App\Models\User;
use App\Models\SystemConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\LoggingService;
use App\Models\NotificationLog;
use App\Mail\OcorrenciaEncontradaMail;

class NotificationService
{
    protected $loggingService;

    public function __construct()
    {
        $this->loggingService = new LoggingService();
    }

    public function notifyOcorrencia(Ocorrencia $ocorrencia): void
    {
        $this->loggingService->logNotificacao(
            LoggingService::NIVEL_INFO,
            'Iniciando notificações para ocorrência',
            [
                'tipo' => 'ocorrencia',
                'ocorrencia_id' => $ocorrencia->id,
                'empresa' => $ocorrencia->empresa->nome,
                'diario' => $ocorrencia->diario->nome_arquivo
            ]
        );

        // Obter usuários que podem receber notificações desta empresa
        $users = $this->getUsersForNotification($ocorrencia);

        foreach ($users as $user) {
            $this->sendNotificationsToUser($user, $ocorrencia);
        }
    }

    protected function getUsersForNotification(Ocorrencia $ocorrencia): \Illuminate\Support\Collection
    {
        return User::whereHas('empresas', function ($query) use ($ocorrencia) {
            $query->where('empresa_id', $ocorrencia->empresa_id)
                  ->where('pode_receber_whatsapp', true);
        })
        ->where('aceita_whatsapp', true)
        ->whereNotNull('telefone_whatsapp')
        ->get();
    }

    protected function sendNotificationsToUser(User $user, Ocorrencia $ocorrencia): void
    {
        // Verificar se WhatsApp está habilitado
        if ($this->isWhatsAppEnabled()) {
            $this->sendWhatsAppNotification($user, $ocorrencia);
        }

        // Verificar se email está habilitado
        if ($this->isEmailEnabled()) {
            $this->sendEmailNotification($user, $ocorrencia);
        }
    }

    protected function sendWhatsAppNotification(User $user, Ocorrencia $ocorrencia): void
    {
        if (empty($user->telefone_whatsapp)) {
            Log::warning('Usuário sem telefone WhatsApp', [
                'user_id' => $user->id,
                'ocorrencia_id' => $ocorrencia->id
            ]);
            return;
        }

        Log::info('Enviando WhatsApp', [
            'user_id' => $user->id,
            'telefone' => $user->telefone_whatsapp,
            'ocorrencia_id' => $ocorrencia->id
        ]);

        SendWhatsAppNotification::dispatch($ocorrencia, $user->telefone_whatsapp);
    }

    protected function sendEmailNotification(User $user, Ocorrencia $ocorrencia): void
    {
        if (empty($user->email)) {
            Log::warning('Usuário sem email', [
                'user_id' => $user->id,
                'ocorrencia_id' => $ocorrencia->id
            ]);
            return;
        }

        Log::info('Enviando email', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ocorrencia_id' => $ocorrencia->id
        ]);

        try {
            // Enviar email real
            Mail::to($user->email)->send(new OcorrenciaEncontradaMail(
                $ocorrencia->empresa,
                $ocorrencia->diario,
                $ocorrencia
            ));

            // Registrar log de sucesso
            NotificationLog::logEmailSent([
                'ocorrencia_id' => $ocorrencia->id,
                'user_id' => $user->id,
                'empresa_id' => $ocorrencia->empresa_id,
                'diario_id' => $ocorrencia->diario_id,
                'recipient' => $user->email,
                'recipient_name' => $user->name,
                'subject' => "🚨 {$ocorrencia->empresa->nome} encontrada em Diário Oficial - {$ocorrencia->diario->estado}",
                'message' => "Email de notificação automática enviado para {$user->name}",
                'triggered_by' => 'automatic',
                'headers' => [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'X-Notification-Type' => 'ocorrencia_encontrada',
                ]
            ]);

            // Marcar ocorrência como notificada por email
            $ocorrencia->update(['notificado_email' => true]);

            Log::info('Email enviado com sucesso', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ocorrencia_id' => $ocorrencia->id
            ]);

        } catch (\Exception $e) {
            // Registrar log de falha
            NotificationLog::logEmailFailed([
                'ocorrencia_id' => $ocorrencia->id,
                'user_id' => $user->id,
                'empresa_id' => $ocorrencia->empresa_id,
                'diario_id' => $ocorrencia->diario_id,
                'recipient' => $user->email,
                'recipient_name' => $user->name,
                'subject' => "🚨 {$ocorrencia->empresa->nome} encontrada em Diário Oficial - {$ocorrencia->diario->estado}",
                'message' => "Tentativa de email para {$user->name}",
                'error_message' => $e->getMessage(),
                'triggered_by' => 'automatic',
            ]);

            Log::error('Erro ao enviar email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ocorrencia_id' => $ocorrencia->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function testWhatsAppForUser(User $user): array
    {
        if (!$user->aceita_whatsapp) {
            return [
                'success' => false,
                'error' => 'Usuário não aceita WhatsApp'
            ];
        }

        if (empty($user->telefone_whatsapp)) {
            return [
                'success' => false,
                'error' => 'Usuário não tem telefone WhatsApp cadastrado'
            ];
        }

        $whatsappService = new WhatsAppService();
        $message = "🧪 Teste de notificação\n\n" .
                   "Olá {$user->name}!\n\n" .
                   "Este é um teste do sistema de notificações WhatsApp.\n\n" .
                   "📅 " . now()->format('d/m/Y H:i') . "\n\n" .
                   "Sistema de Diários Oficiais";

        return $whatsappService->sendTextMessage($user->telefone_whatsapp, $message);
    }

    public function getNotificationStats(): array
    {
        $totalUsers = User::count();
        $usersWithWhatsApp = User::whereNotNull('telefone_whatsapp')
            ->where('aceita_whatsapp', true)
            ->count();
        
        $ocorrenciasNaoNotificadas = Ocorrencia::where('notificado_whatsapp', false)
            ->count();

        return [
            'total_users' => $totalUsers,
            'users_with_whatsapp' => $usersWithWhatsApp,
            'pending_notifications' => $ocorrenciasNaoNotificadas,
            'whatsapp_enabled' => $this->isWhatsAppEnabled(),
            'email_enabled' => $this->isEmailEnabled(),
        ];
    }

    private function isWhatsAppEnabled(): bool
    {
        return $this->resolveEnabledConfig(
            ['whatsapp_enabled', 'notificacoes_whatsapp_ativo'],
            ['notifications.whatsapp_enabled'],
            false
        );
    }

    private function isEmailEnabled(): bool
    {
        return $this->resolveEnabledConfig(
            ['notificacoes_email_ativo'],
            ['notifications.email_enabled'],
            false
        );
    }

    private function resolveEnabledConfig(array $configuracaoSistemaKeys, array $systemConfigKeys, bool $default): bool
    {
        foreach ($configuracaoSistemaKeys as $key) {
            $entry = ConfiguracaoSistema::query()
                ->where('chave', $key)
                ->where('ativo', true)
                ->first();

            if ($entry !== null) {
                return filter_var($entry->valor, FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach ($systemConfigKeys as $key) {
            $value = SystemConfig::getValue($key, null);

            if ($value !== null) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $default;
    }
}
