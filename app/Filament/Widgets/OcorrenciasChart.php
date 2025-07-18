<?php

namespace App\Filament\Widgets;

use App\Models\Ocorrencia;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OcorrenciasChart extends ChartWidget
{
    protected static ?string $heading = 'Ocorrências por Dia (Últimos 30 dias)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = Ocorrencia::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as count')
        )
        ->where('created_at', '>=', now()->subDays(30))
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Ocorrências encontradas',
                    'data' => $data->pluck('count')->toArray(),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
            'labels' => $data->pluck('date')->map(function ($date) {
                return \Carbon\Carbon::parse($date)->format('d/m');
            })->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}