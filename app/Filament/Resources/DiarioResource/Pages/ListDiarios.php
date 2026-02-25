<?php

namespace App\Filament\Resources\DiarioResource\Pages;

use App\Filament\Resources\DiarioResource;
use App\Filament\Resources\DiarioResource\Widgets\DiariosTableStats;
use App\Jobs\ProcessarPdfJob;
use App\Models\Diario;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListDiarios extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = DiarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('enfileirarFiltrados')
                ->label('Enfileirar Filtrados')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\TextInput::make('limite')
                        ->label('Limite de registros')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(500)
                        ->default(100)
                        ->required()
                        ->helperText('Aplica somente aos registros da visão atual (aba + busca + filtros) com status pendente/erro.'),
                ])
                ->requiresConfirmation()
                ->modalDescription('Os registros serão marcados como processando e enviados para a fila. Use limites menores em operações recorrentes.')
                ->action(function (array $data): void {
                    $limite = max(1, min((int) ($data['limite'] ?? 100), 500));

                    $query = clone $this->getFilteredTableQuery();

                    $ids = $query
                        ->whereIn('status', ['pendente', 'erro'])
                        ->limit($limite)
                        ->pluck('id');

                    if ($ids->isEmpty()) {
                        Notification::make()
                            ->title('Nenhum diário elegível')
                            ->body('Não há registros pendentes/erro na visão atual para enfileirar.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $registros = Diario::query()
                        ->whereIn('id', $ids)
                        ->get();

                    $enfileirados = 0;

                    foreach ($registros as $record) {
                        if (! in_array($record->status, ['pendente', 'erro'], true)) {
                            continue;
                        }

                        $record->update([
                            'status' => 'processando',
                            'status_processamento' => 'processando',
                            'erro_mensagem' => null,
                            'erro_processamento' => null,
                        ]);

                        ProcessarPdfJob::dispatch($record, [
                            'tipo' => 'inicial',
                            'modo' => 'completo',
                            'motivo' => 'Enfileirado pela ação "Filtrados"',
                            'notificar' => true,
                            'limpar_ocorrencias_anteriores' => true,
                            'iniciado_por_user_id' => Auth::id(),
                        ]);
                        $enfileirados++;
                    }

                    $this->flushCachedTableRecords();

                    Notification::make()
                        ->title('Processamento enfileirado')
                        ->body("{$enfileirados} diário(s) enviado(s) para a fila.")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
    
    public function getTableRecordsPerPageSelectOptions(): array
    {
        return [25, 50, 100];
    }
    
    public function getDefaultTableRecordsPerPageSelectOption(): int
    {
        return 25;
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'pendentes';
    }

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos')
                ->badge(Diario::count())
                ->badgeColor('gray')
                ->icon('heroicon-o-queue-list')
                ->modifyQueryUsing(fn (Builder $query) => $query->reorder('created_at', 'desc')),
            'pendentes' => Tab::make('Pendentes')
                ->badge(Diario::where('status', 'pendente')->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', 'pendente')
                    ->reorder('data_diario', 'desc')
                    ->orderBy('created_at', 'desc')),
            'processando' => Tab::make('Processando')
                ->badge(Diario::where('status', 'processando')->count())
                ->badgeColor('info')
                ->icon('heroicon-o-arrow-path')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', 'processando')
                    ->reorder('updated_at', 'desc')),
            'concluidos' => Tab::make('Concluídos')
                ->badge(Diario::where('status', 'concluido')->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', 'concluido')
                    ->reorder('processado_em', 'desc')
                    ->orderBy('created_at', 'desc')),
            'erro' => Tab::make('Erro')
                ->badge(Diario::where('status', 'erro')->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', 'erro')
                    ->reorder('updated_at', 'desc')
                    ->orderByDesc('tentativas')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DiariosTableStats::class,
        ];
    }
}
