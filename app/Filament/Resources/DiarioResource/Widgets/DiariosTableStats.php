<?php

namespace App\Filament\Resources\DiarioResource\Widgets;

use App\Filament\Resources\DiarioResource\Pages\ListDiarios;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DiariosTableStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected static ?string $pollingInterval = '15s';

    protected function getTablePage(): string
    {
        return ListDiarios::class;
    }

    protected function getStats(): array
    {
        $baseQuery = $this->getPageTableQuery();

        $total = (clone $baseQuery)->count();
        $pendentes = (clone $baseQuery)->where('status', 'pendente')->count();
        $erros = (clone $baseQuery)->where('status', 'erro')->count();
        $processando = (clone $baseQuery)->where('status', 'processando')->count();
        $comOcorrencias = (clone $baseQuery)->whereHas('ocorrencias')->count();

        return [
            Stat::make('Total na visão', number_format($total))
                ->description('Aba + busca + filtros atuais')
                ->icon('heroicon-o-queue-list')
                ->color('gray'),

            Stat::make('A processar', number_format($pendentes + $erros))
                ->description("Pendentes: {$pendentes} | Erro: {$erros}")
                ->icon('heroicon-o-play')
                ->color(($pendentes + $erros) > 0 ? 'warning' : 'success'),

            Stat::make('Processando', number_format($processando))
                ->description('Em andamento agora')
                ->icon('heroicon-o-arrow-path')
                ->color('info'),

            Stat::make('Com ocorrências', number_format($comOcorrencias))
                ->description('Dentro da visão atual')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary'),
        ];
    }
}
