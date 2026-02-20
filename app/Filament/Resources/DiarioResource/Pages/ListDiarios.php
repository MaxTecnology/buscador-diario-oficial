<?php

namespace App\Filament\Resources\DiarioResource\Pages;

use App\Filament\Resources\DiarioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use App\Models\Diario;

class ListDiarios extends ListRecords
{
    protected static string $resource = DiarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    
    public function getTableRecordsPerPageSelectOptions(): array
    {
        return [25, 50, 100, 200];
    }
    
    public function getDefaultTableRecordsPerPageSelectOption(): int
    {
        return 50; // Ideal para ~26 PDFs por dia
    }

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos')
                ->badge(Diario::count())
                ->modifyQueryUsing(fn ($query) => $query),
            'pendentes' => Tab::make('Pendentes')
                ->badge(Diario::where('status', 'pendente')->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'pendente')),
            'processando' => Tab::make('Processando')
                ->badge(Diario::where('status', 'processando')->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'processando')),
            'concluidos' => Tab::make('Concluídos')
                ->badge(Diario::where('status', 'concluido')->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'concluido')),
            'erro' => Tab::make('Erro')
                ->badge(Diario::where('status', 'erro')->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'erro')),
        ];
    }
}
