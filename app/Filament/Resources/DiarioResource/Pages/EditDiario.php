<?php

namespace App\Filament\Resources\DiarioResource\Pages;

use App\Filament\Resources\DiarioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDiario extends EditRecord
{
    protected static string $resource = DiarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
