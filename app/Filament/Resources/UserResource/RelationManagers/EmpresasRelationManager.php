<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmpresasRelationManager extends RelationManager
{
    protected static string $relationship = 'empresas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('pode_visualizar')
                    ->label('Pode Visualizar')
                    ->default(true),
                Forms\Components\Toggle::make('pode_receber_email')
                    ->label('Pode Receber Email')
                    ->default(true),
                Forms\Components\Toggle::make('pode_receber_whatsapp')
                    ->label('Pode Receber WhatsApp')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome da Empresa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->formatStateUsing(function ($state) {
                        if (!$state || strlen($state) !== 14) return $state;
                        return substr($state, 0, 2) . '.' . substr($state, 2, 3) . '.' . substr($state, 5, 3) . '/' . substr($state, 8, 4) . '-' . substr($state, 12, 2);
                    }),
                Tables\Columns\IconColumn::make('pivot.pode_visualizar')
                    ->label('Pode Visualizar')
                    ->boolean(),
                Tables\Columns\IconColumn::make('pivot.pode_receber_email')
                    ->label('Recebe Email')
                    ->boolean(),
                Tables\Columns\IconColumn::make('pivot.pode_receber_whatsapp')
                    ->label('Recebe WhatsApp')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Toggle::make('pode_visualizar')
                            ->label('Pode Visualizar')
                            ->default(true),
                        Forms\Components\Toggle::make('pode_receber_email')
                            ->label('Pode Receber Email')
                            ->default(true),
                        Forms\Components\Toggle::make('pode_receber_whatsapp')
                            ->label('Pode Receber WhatsApp')
                            ->default(false),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
