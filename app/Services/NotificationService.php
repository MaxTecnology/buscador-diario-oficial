<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Ocorrencia;
use App\Models\User;
use App\Models\SystemConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notifyOcorrencia(Ocorrencia $ocorrencia): void
    {
        Log::info('Iniciando notificações para ocorrência', [
            'ocorrencia_id' => $ocorrencia->id,
            'empresa' => $ocorrencia->empresa->nome
        ]);

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
        if (SystemConfig::getValue('notifications.whatsapp_enabled', false)) {
            $this->sendWhatsAppNotification($user, $ocorrencia);
        }

        // Verificar se email está habilitado
        if (SystemConfig::getValue('notifications.email_enabled', false)) {
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
        // TODO: Implementar envio de email
        Log::info('Email notification seria enviado', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ocorrencia_id' => $ocorrencia->id
        ]);
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
            'whatsapp_enabled' => SystemConfig::getValue('notifications.whatsapp_enabled', false),
            'email_enabled' => SystemConfig::getValue('notifications.email_enabled', false),
        ];
    }
}