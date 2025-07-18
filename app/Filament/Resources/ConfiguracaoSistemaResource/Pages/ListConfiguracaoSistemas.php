<?php

namespace App\Filament\Resources\ConfiguracaoSistemaResource\Pages;

use App\Filament\Resources\ConfiguracaoSistemaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConfiguracaoSistemas extends ListRecords
{
    protected static string $resource = ConfiguracaoSistemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
