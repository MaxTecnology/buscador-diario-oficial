<?php

namespace App\Filament\Pages;

use App\Models\Diario;
use App\Models\Ocorrencia;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class OcorrenciasPorDiario extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    
    protected static ?string $navigationLabel = 'Ocorrências por Diário';
    
    protected static ?string $title = 'Ocorrências por Diário';
    
    protected static ?string $navigationGroup = 'Monitoramento';
    
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.ocorrencias-por-diario';
    
    public $periodo = 'hoje';
    
    public function mount(): void
    {
        $this->periodo = 'hoje';
    }
    
    public function getDiariosComOcorrencias(): Collection
    {
        $query = Diario::with(['ocorrencias.empresa'])
            ->whereHas('ocorrencias')
            ->orderBy('data_diario', 'desc')
            ->orderBy('created_at', 'desc');
            
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
            $diario->empresas_detectadas = $diario->ocorrencias->unique('empresa_id')->count();
            $diario->score_medio = $diario->ocorrencias->avg('score_confianca');
            $diario->nao_notificadas = $diario->ocorrencias->where('notificado_email', false)
                                                          ->where('notificado_whatsapp', false)
                                                          ->count();
            return $diario;
        });
    }
    
    public function getEstatisticasGerais(): array
    {
        $ocorrencias = Ocorrencia::query();
        
        // Aplicar filtro de período
        switch ($this->periodo) {
            case 'hoje':
                $ocorrencias->whereDate('created_at', today());
                break;
            case 'esta_semana':
                $ocorrencias->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'este_mes':
                $ocorrencias->whereMonth('created_at', now()->month)
                           ->whereYear('created_at', now()->year);
                break;
        }
        
        $total = $ocorrencias->count();
        $empresasUnicas = $ocorrencias->distinct('empresa_id')->count();
        $naoNotificadas = $ocorrencias->where('notificado_email', false)
                                    ->where('notificado_whatsapp', false)
                                    ->count();
        $altaConfianca = $ocorrencias->where('score_confianca', '>=', 0.95)->count();
        
        return [
            'total_ocorrencias' => $total,
            'empresas_unicas' => $empresasUnicas,
            'nao_notificadas' => $naoNotificadas,
            'alta_confianca' => $altaConfianca,
        ];
    }
    
    public function setPeriodo(string $periodo): void
    {
        $this->periodo = $periodo;
    }
}
