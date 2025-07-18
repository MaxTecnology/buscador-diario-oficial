<?php

namespace App\Filament\Widgets;

use App\Models\Diario;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DiariosPorEstado extends ChartWidget
{
    protected static ?string $heading = 'Diários por Estado';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $data = Diario::select('estado', DB::raw('count(*) as count'))
            ->groupBy('estado')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Diários processados',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(20, 184, 166, 0.8)',
                        'rgba(251, 146, 60, 0.8)',
                        'rgba(132, 204, 22, 0.8)',
                        'rgba(99, 102, 241, 0.8)',
                    ],
                ],
            ],
            'labels' => $data->pluck('estado')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}