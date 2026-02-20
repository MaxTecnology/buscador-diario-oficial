<?php

namespace App\Filament\Resources\OcorrenciaResource\Pages;

use App\Filament\Resources\OcorrenciaResource;
use App\Filament\Resources\OcorrenciaResource\Widgets\OcorrenciasStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOcorrencias extends ListRecords
{
    protected static string $resource = OcorrenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(), // Removido pois ocorrências são criadas automaticamente
        ];
    }
    
    public function getTableRecordsPerPageSelectOptions(): array
    {
        return [25, 50, 100, 200];
    }
    
    public function getDefaultTableRecordsPerPageSelectOption(): int
    {
        return 50;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OcorrenciasStats::class,
        ];
    }
}
