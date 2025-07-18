<?php

namespace App\Filament\Resources\ConfiguracaoSistemaResource\Pages;

use App\Filament\Resources\ConfiguracaoSistemaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConfiguracaoSistema extends EditRecord
{
    protected static string $resource = ConfiguracaoSistemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
