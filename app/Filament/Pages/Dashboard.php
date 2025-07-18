<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\OcorrenciasChart;
use App\Filament\Widgets\DiariosPorEstado;
use App\Filament\Widgets\UltimosProcessamentos;
use App\Filament\Widgets\OcorrenciasRecentes;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament-panels::pages.dashboard';

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            \App\Filament\Widgets\NotificationStats::class,
            OcorrenciasChart::class,
            DiariosPorEstado::class,
            UltimosProcessamentos::class,
            OcorrenciasRecentes::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'md' => 3,
            'lg' => 4,
            'xl' => 6,
            '2xl' => 8,
        ];
    }
}