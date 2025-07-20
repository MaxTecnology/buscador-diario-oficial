<?php

namespace App\Filament\Pages;

use App\Models\NotificationLog;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class LogsNotificacoes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    
    protected static ?string $navigationLabel = 'Logs de Notificações';
    
    protected static ?string $title = 'Logs de Email e WhatsApp';
    
    protected static ?string $navigationGroup = 'Sistema';
    
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.logs-notificacoes';

    public $filtroTipo = '';
    public $filtroStatus = '';
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
                        Select::make('filtroTipo')
                            ->label('Tipo de Notificação')
                            ->options([
                                '' => 'Todos',
                                'email' => 'Email',
                                'whatsapp' => 'WhatsApp',
                            ])
                            ->live(),
                        
                        Select::make('filtroStatus')
                            ->label('Status')
                            ->options([
                                '' => 'Todos',
                                'sent' => 'Sucesso',
                                'failed' => 'Falha',
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
        $query = NotificationLog::query()
            ->orderBy('created_at', 'desc');

        if ($this->filtroTipo) {
            $query->where('type', $this->filtroTipo);
        }

        if ($this->filtroStatus) {
            $query->where('status', $this->filtroStatus);
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
            'total' => NotificationLog::count(),
            'emails_sucesso' => NotificationLog::where('type', 'email')->where('status', 'sent')->count(),
            'emails_falha' => NotificationLog::where('type', 'email')->where('status', 'failed')->count(),
            'whatsapp_sucesso' => NotificationLog::where('type', 'whatsapp')->where('status', 'sent')->count(),
            'whatsapp_falha' => NotificationLog::where('type', 'whatsapp')->where('status', 'failed')->count(),
        ];
    }
}
