<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $selectedRole = null;

    protected string $empresaScope = 'manual';

    protected array $selectedEmpresas = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->selectedRole = $data['role_name'] ?? UserResource::resolvePrimaryRole($this->record) ?? 'operator';
        $this->empresaScope = $data['empresa_scope'] ?? 'manual';
        $this->selectedEmpresas = $data['empresas'] ?? [];

        unset($data['role_name'], $data['empresa_scope'], $data['empresas']);

        return $data;
    }

    protected function afterSave(): void
    {
        $role = $this->selectedRole ?: 'operator';
        $this->record->syncRoles([$role]);

        $empresaIds = $this->empresaScope === 'all'
            ? UserResource::allEmpresaIds()
            : $this->selectedEmpresas;

        $this->record->empresas()->sync($empresaIds);
    }
}
