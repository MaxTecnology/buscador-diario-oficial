<?php

namespace App\Filament\Pages;

use App\Models\Diario;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class DiariosCompactos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    
    protected static ?string $navigationLabel = 'Diários Compactos';
    
    protected static ?string $title = 'Visualização Compacta';
    
    protected static ?string $navigationGroup = 'Monitoramento';
    
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.diarios-compactos';
    
    public $periodo = 'hoje';
    public $estado = '';
    public $status = '';
    
    public function mount(): void
    {
        $this->periodo = 'hoje';
    }
    
    public function getDiariosCompactos(): Collection
    {
        $query = Diario::with(['ocorrencias', 'usuario'])
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
            case 'todos':
                // Não aplicar filtro de período
                break;
        }
        
        // Aplicar filtro de estado
        if ($this->estado) {
            $query->where('estado', $this->estado);
        }
        
        // Aplicar filtro de status
        if ($this->status) {
            $query->where('status', $this->status);
        }
        
        return $query->limit(50)->get()->map(function ($diario) {
            $diario->total_ocorrencias = $diario->ocorrencias->count();
            $diario->ocorrencias_nao_notificadas = $diario->ocorrencias
                ->where('notificado_email', false)
                ->where('notificado_whatsapp', false)
                ->count();
            $diario->empresas_detectadas = $diario->ocorrencias->unique('empresa_id')->count();
            return $diario;
        });
    }
    
    public function getEstadosDisponiveis(): array
    {
        return [
            '' => 'Todos os Estados',
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá',
            'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará',
            'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
            'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
        ];
    }
    
    public function setPeriodo(string $periodo): void
    {
        $this->periodo = $periodo;
    }
    
    public function setEstado(string $estado): void
    {
        $this->estado = $estado;
    }
    
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
    
    public function reprocessarDiario($diarioId): void
    {
        $diario = Diario::find($diarioId);
        if ($diario && $diario->status === 'erro') {
            $diario->update([
                'status' => 'pendente',
                'erro_mensagem' => null,
                'tentativas' => 0,
            ]);
            
            \Filament\Notifications\Notification::make()
                ->title('Diário marcado para reprocessamento')
                ->success()
                ->send();
        }
    }
}
