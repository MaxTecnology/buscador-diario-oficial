<?php

namespace App\Filament\Widgets;

use App\Models\Diario;
use App\Models\Empresa;
use App\Models\Ocorrencia;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalEmpresas = Empresa::count();
        $empresasAtivas = Empresa::where('ativo', true)->count();
        $totalUsuarios = User::count();
        $totalDiarios = Diario::count();
        $diariosProcessados = Diario::where('status', 'concluido')->count();
        $diariosPendentes = Diario::where('status', 'pendente')->count();
        $diariosComErro = Diario::where('status', 'erro')->count();
        $totalOcorrencias = Ocorrencia::count();
        $ocorrenciasHoje = Ocorrencia::whereDate('created_at', today())->count();
        
        // Calcular taxa de sucesso
        $taxaSucesso = $totalDiarios > 0 ? ($diariosProcessados / $totalDiarios) * 100 : 0;
        
        return [
            Stat::make('Total de Empresas', $totalEmpresas)
                ->description($empresasAtivas . ' empresas ativas')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('success'),
                
            Stat::make('Total de Usuários', $totalUsuarios)
                ->description('Usuários cadastrados')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
                
            Stat::make('Diários Processados', $diariosProcessados)
                ->description($diariosPendentes . ' pendentes, ' . $diariosComErro . ' com erro')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($diariosComErro > 0 ? 'warning' : 'success'),
                
            Stat::make('Taxa de Sucesso', number_format($taxaSucesso, 1) . '%')
                ->description('Diários processados com sucesso')
                ->descriptionIcon('heroicon-m-chart-bar-square')
                ->color($taxaSucesso >= 90 ? 'success' : ($taxaSucesso >= 70 ? 'warning' : 'danger')),
                
            Stat::make('Total de Ocorrências', $totalOcorrencias)
                ->description($ocorrenciasHoje . ' encontradas hoje')
                ->descriptionIcon('heroicon-m-magnifying-glass')
                ->color('primary'),
                
            Stat::make('Diários Hoje', Diario::whereDate('created_at', today())->count())
                ->description('Diários enviados hoje')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),
        ];
    }
}