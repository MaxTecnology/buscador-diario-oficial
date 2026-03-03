<?php

namespace App\Filament\Resources\IngestaoDiarioLogResource\Pages;

use App\Filament\Resources\IngestaoDiarioLogResource;
use Filament\Resources\Pages\ListRecords;

class ListIngestaoDiarioLogs extends ListRecords
{
    protected static string $resource = IngestaoDiarioLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTableRecordsPerPageSelectOptions(): array
    {
        return [25, 50, 100, 200];
    }

    public function getDefaultTableRecordsPerPageSelectOption(): int
    {
        return 50;
    }
}

