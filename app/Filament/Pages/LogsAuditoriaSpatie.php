<?php

namespace App\Filament\Pages;

use Spatie\Activitylog\Models\Activity;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class LogsAuditoriaSpatie extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    
    protected static ?string $navigationLabel = 'Logs de Auditoria (Sistema)';
    
    protected static ?string $title = 'Logs de Auditoria do Sistema';
    
    protected static ?string $navigationGroup = 'Sistema';
    
    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.logs-auditoria-spatie';

    public $filtroEvent = '';
    public $filtroSubject = '';
    public $filtroDataInicio = '';
    public $filtroDataFim = '';

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Select::make('filtroEvent')
                            ->label('Evento')
                            ->options([
                                '' => 'Todos',
                                'created' => 'Criado',
                                'updated' => 'Atualizado',
                                'deleted' => 'Excluído',
                            ])
                            ->live(),
                        
                        Select::make('filtroSubject')
                            ->label('Tipo de Objeto')
                            ->options([
                                '' => 'Todos',
                                'App\\Models\\User' => 'Usuários',
                                'App\\Models\\Empresa' => 'Empresas',
                                'App\\Models\\Diario' => 'Diários',
                                'App\\Models\\Ocorrencia' => 'Ocorrências',
                            ])
                            ->live(),
                        
                        DatePicker::make('filtroDataInicio')
                            ->label('Data Início')
                            ->live(),
                        
                        DatePicker::make('filtroDataFim')
                            ->label('Data Fim')
                            ->live(),
                    ])
                    ->columns(4)
            ),
        ];
    }

    public function getLogs()
    {
        $query = Activity::with(['causer', 'subject'])
            ->orderBy('created_at', 'desc');

        if ($this->filtroEvent) {
            $query->where('event', $this->filtroEvent);
        }

        if ($this->filtroSubject) {
            $query->where('subject_type', $this->filtroSubject);
        }

        if ($this->filtroDataInicio) {
            $query->whereDate('created_at', '>=', $this->filtroDataInicio);
        }

        if ($this->filtroDataFim) {
            $query->whereDate('created_at', '<=', $this->filtroDataFim);
        }

        return $query->limit(500)->get();
    }

    public function getStats()
    {
        return [
            'total' => Activity::count(),
            'hoje' => Activity::whereDate('created_at', today())->count(),
            'esta_semana' => Activity::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'este_mes' => Activity::whereMonth('created_at', now()->month)->count(),
        ];
    }
}
