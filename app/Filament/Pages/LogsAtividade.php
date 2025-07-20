<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Models\NotificationLog;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class LogsAtividade extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    
    protected static ?string $navigationLabel = 'Timeline de Atividades';
    
    protected static ?string $title = 'Timeline de Atividades dos Usuários';
    
    protected static ?string $navigationGroup = 'Sistema';
    
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.logs-atividade';
    
    public $filtroUsuario = '';
    public $filtroAcao = '';
    public $filtroEntidade = '';
    public $filtroDataInicio = '';
    public $filtroDataFim = '';
    public $atividades = [];
    public $estatisticas = [];
    
    public function mount(): void
    {
        $this->filtroDataInicio = now()->subDays(7)->format('Y-m-d');
        $this->filtroDataFim = now()->format('Y-m-d');
        $this->carregarDados();
    }
    
    public function carregarDados(): void
    {
        $this->atividades = $this->obterAtividades();
        $this->estatisticas = $this->obterEstatisticas();
    }
    
    public function filtrar(): void
    {
        $this->carregarDados();
    }
    
    private function obterAtividades(): Collection
    {
        $query = ActivityLog::with('user')
            ->orderBy('occurred_at', 'desc');
        
        // Aplicar filtros
        if ($this->filtroUsuario) {
            $query->where('user_id', $this->filtroUsuario);
        }
        
        if ($this->filtroAcao) {
            $query->where('action', $this->filtroAcao);
        }
        
        if ($this->filtroEntidade) {
            $query->where('entity_type', $this->filtroEntidade);
        }
        
        if ($this->filtroDataInicio) {
            $query->whereDate('occurred_at', '>=', $this->filtroDataInicio);
        }
        
        if ($this->filtroDataFim) {
            $query->whereDate('occurred_at', '<=', $this->filtroDataFim);
        }
        
        return $query->limit(200)->get()->map(function ($atividade) {
            // Adicionar informações extras para visualização
            $atividade->tempo_relativo = $atividade->occurred_at->diffForHumans();
            $atividade->data_formatada = $atividade->occurred_at->format('d/m/Y H:i:s');
            
            return $atividade;
        });
    }
    
    private function obterEstatisticas(): array
    {
        $dataInicio = $this->filtroDataInicio ? $this->filtroDataInicio : now()->subDays(7)->format('Y-m-d');
        $dataFim = $this->filtroDataFim ? $this->filtroDataFim : now()->format('Y-m-d');
        
        // Estatísticas de atividades
        $atividadesPorAcao = ActivityLog::selectRaw('action, COUNT(*) as total')
            ->whereDate('occurred_at', '>=', $dataInicio)
            ->whereDate('occurred_at', '<=', $dataFim)
            ->groupBy('action')
            ->pluck('total', 'action')
            ->toArray();
        
        $atividadesPorUsuario = ActivityLog::selectRaw('user_name, COUNT(*) as total')
            ->whereDate('occurred_at', '>=', $dataInicio)
            ->whereDate('occurred_at', '<=', $dataFim)
            ->groupBy('user_name')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'user_name')
            ->toArray();
        
        $atividadesPorDia = ActivityLog::selectRaw('DATE(occurred_at) as data, COUNT(*) as total')
            ->whereDate('occurred_at', '>=', $dataInicio)
            ->whereDate('occurred_at', '<=', $dataFim)
            ->groupBy('data')
            ->orderBy('data')
            ->pluck('total', 'data')
            ->toArray();
        
        // Estatísticas de notificações
        $notificacoesPorTipo = NotificationLog::selectRaw('type, status, COUNT(*) as total')
            ->whereDate('created_at', '>=', $dataInicio)
            ->whereDate('created_at', '<=', $dataFim)
            ->groupBy(['type', 'status'])
            ->get()
            ->groupBy('type')
            ->toArray();
        
        $totalNotificacoes = NotificationLog::whereDate('created_at', '>=', $dataInicio)
            ->whereDate('created_at', '<=', $dataFim)
            ->count();
        
        return [
            'periodo' => [
                'inicio' => $dataInicio,
                'fim' => $dataFim,
                'dias' => \Carbon\Carbon::parse($dataInicio)->diffInDays(\Carbon\Carbon::parse($dataFim)) + 1
            ],
            'atividades' => [
                'total' => array_sum($atividadesPorAcao),
                'por_acao' => $atividadesPorAcao,
                'por_usuario' => $atividadesPorUsuario,
                'por_dia' => $atividadesPorDia,
            ],
            'notificacoes' => [
                'total' => $totalNotificacoes,
                'por_tipo' => $notificacoesPorTipo,
            ]
        ];
    }
    
    public function getUsuariosOptions(): array
    {
        return User::orderBy('name')
            ->pluck('name', 'id')
            ->prepend('Todos os usuários', '')
            ->toArray();
    }
    
    public function getAcoesOptions(): array
    {
        $acoes = ActivityLog::distinct('action')
            ->orderBy('action')
            ->pluck('action', 'action')
            ->toArray();
        
        return array_merge(['' => 'Todas as ações'], $acoes);
    }
    
    public function getEntidadesOptions(): array
    {
        $entidades = ActivityLog::distinct('entity_type')
            ->orderBy('entity_type')
            ->pluck('entity_type', 'entity_type')
            ->toArray();
        
        return array_merge(['' => 'Todas as entidades'], $entidades);
    }
    
    public function verDetalhes(int $activityId): void
    {
        $activity = ActivityLog::find($activityId);
        
        if ($activity) {
            \Filament\Notifications\Notification::make()
                ->title("Detalhes da Atividade #$activityId")
                ->body($activity->description)
                ->info()
                ->persistent()
                ->send();
        }
    }
    
    public function exportarRelatorio(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $atividades = $this->obterAtividades();
        
        return response()->streamDownload(function () use ($atividades) {
            $output = fopen('php://output', 'w');
            
            // Cabeçalhos CSV
            fputcsv($output, [
                'Data/Hora',
                'Usuário',
                'Ação',
                'Entidade',
                'Descrição',
                'IP',
                'Origem'
            ]);
            
            foreach ($atividades as $atividade) {
                fputcsv($output, [
                    $atividade->occurred_at->format('d/m/Y H:i:s'),
                    $atividade->user_name,
                    $atividade->action,
                    $atividade->entity_type,
                    $atividade->description,
                    $atividade->ip_address,
                    $atividade->source
                ]);
            }
            
            fclose($output);
        }, 'relatorio-atividades-' . now()->format('Y-m-d-H-i') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}