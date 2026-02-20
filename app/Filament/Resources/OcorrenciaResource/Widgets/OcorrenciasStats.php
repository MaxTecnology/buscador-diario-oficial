<?php

namespace App\Filament\Resources\OcorrenciaResource\Widgets;

use App\Models\Ocorrencia;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class OcorrenciasStats extends BaseWidget
{
    protected function getCards(): array
    {
        $total = Ocorrencia::count();
        $hoje = Ocorrencia::whereDate('created_at', today())->count();
        $semana = Ocorrencia::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();

        return [
            Card::make('Ocorrências totais', number_format($total))
                ->description('Acumulado')
                ->color('primary'),
            Card::make('Hoje', number_format($hoje))
                ->description('Encontradas nas últimas 24h')
                ->color('success'),
            Card::make('Esta semana', number_format($semana))
                ->description('Seg - Dom')
                ->color('info'),
        ];
    }
}
