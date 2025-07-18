<?php

namespace App\Filament\Resources\DiarioResource\Pages;

use App\Filament\Resources\DiarioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

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
}
