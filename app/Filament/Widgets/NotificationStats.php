<?php

namespace App\Filament\Widgets;

use App\Services\NotificationService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NotificationStats extends BaseWidget
{
    protected static ?int $sort = 6;

    protected function getStats(): array
    {
        $notificationService = new NotificationService();
        $stats = $notificationService->getNotificationStats();

        return [
            Stat::make('Usuários com WhatsApp', $stats['users_with_whatsapp'])
                ->description('de ' . $stats['total_users'] . ' usuários totais')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('success'),

            Stat::make('Notificações Pendentes', $stats['pending_notifications'])
                ->description('Ocorrências não notificadas')
                ->descriptionIcon('heroicon-m-bell')
                ->color($stats['pending_notifications'] > 0 ? 'warning' : 'success'),

            Stat::make('WhatsApp Status', $stats['whatsapp_enabled'] ? 'Ativo' : 'Inativo')
                ->description($stats['whatsapp_enabled'] ? 'Notificações habilitadas' : 'Notificações desabilitadas')
                ->descriptionIcon('heroicon-m-signal')
                ->color($stats['whatsapp_enabled'] ? 'success' : 'danger'),
        ];
    }
}