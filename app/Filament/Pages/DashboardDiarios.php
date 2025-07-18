<?php

namespace App\Filament\Pages;

use App\Models\Diario;
use App\Models\Ocorrencia;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class DashboardDiarios extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    
    protected static ?string $navigationLabel = 'Dashboard Diários';
    
    protected static ?string $title = 'Dashboard de Diários';
    
    protected static ?string $navigationGroup = 'Monitoramento';
    
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.dashboard-diarios';
    
    public $periodo = 'hoje';
    
    public function mount(): void
    {
        $this->periodo = 'hoje';
    }
    
    public function getEstatisticasGerais(): array
    {
        $query = Diario::query();
        
        // Aplicar filtro de período
        switch ($this->periodo) {
            case 'hoje':
                $query->whereDate('created_at', today());
                break;
            case 'esta_semana':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'este_mes':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }
        
        $totalDiarios = $query->count();
        $concluidos = $query->where('status', 'concluido')->count();
        $comErro = $query->where('status', 'erro')->count();
        $processando = $query->where('status', 'processando')->count();
        
        // Estatísticas de ocorrências no período
        $ocorrenciasQuery = Ocorrencia::query();
        switch ($this->periodo) {
            case 'hoje':
                $ocorrenciasQuery->whereDate('created_at', today());
                break;
            case 'esta_semana':
                $ocorrenciasQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'este_mes':
                $ocorrenciasQuery->whereMonth('created_at', now()->month)
                               ->whereYear('created_at', now()->year);
                break;
        }
        
        $totalOcorrencias = $ocorrenciasQuery->count();
        $diariosComOcorrencias = $query->whereHas('ocorrencias')->count();
        
        // Tamanho total processado
        $tamanhoTotal = $query->where('status', 'concluido')->sum('tamanho_arquivo');
        $paginasTotal = $query->where('status', 'concluido')->sum('total_paginas');
        
        return [
            'total_diarios' => $totalDiarios,
            'concluidos' => $concluidos,
            'com_erro' => $comErro,
            'processando' => $processando,
            'total_ocorrencias' => $totalOcorrencias,
            'diarios_com_ocorrencias' => $diariosComOcorrencias,
            'tamanho_total_mb' => round($tamanhoTotal / 1024 / 1024, 2),
            'paginas_total' => $paginasTotal,
            'taxa_sucesso' => $totalDiarios > 0 ? round(($concluidos / $totalDiarios) * 100, 1) : 0,
        ];
    }
    
    public function getEstatisticasPorEstado(): Collection
    {
        $query = Diario::query();
        
        // Aplicar filtro de período
        switch ($this->periodo) {
            case 'hoje':
                $query->whereDate('created_at', today());
                break;
            case 'esta_semana':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'este_mes':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }
        
        return $query->selectRaw('estado, count(*) as total, 
                                 sum(case when status = "concluido" then 1 else 0 end) as concluidos,
                                 sum(case when status = "erro" then 1 else 0 end) as erros')
                    ->groupBy('estado')
                    ->orderBy('total', 'desc')
                    ->get();
    }
    
    public function getDiariosRecentes(): Collection
    {
        $query = Diario::with(['ocorrencias'])
                      ->orderBy('created_at', 'desc')
                      ->limit(10);
        
        // Aplicar filtro de período
        switch ($this->periodo) {
            case 'hoje':
                $query->whereDate('created_at', today());
                break;
            case 'esta_semana':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'este_mes':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }
        
        return $query->get()->map(function ($diario) {
            $diario->total_ocorrencias = $diario->ocorrencias->count();
            $diario->ocorrencias_nao_notificadas = $diario->ocorrencias
                ->where('notificado_email', false)
                ->where('notificado_whatsapp', false)
                ->count();
            return $diario;
        });
    }
    
    public function getGraficoPorDia(): array
    {
        $dados = [];
        $labels = [];
        
        // Últimos 7 dias
        for ($i = 6; $i >= 0; $i--) {
            $data = now()->subDays($i);
            $count = Diario::whereDate('created_at', $data->toDateString())->count();
            
            $labels[] = $data->format('d/m');
            $dados[] = $count;
        }
        
        return [
            'labels' => $labels,
            'dados' => $dados,
        ];
    }
    
    public function setPeriodo(string $periodo): void
    {
        $this->periodo = $periodo;
    }
}
