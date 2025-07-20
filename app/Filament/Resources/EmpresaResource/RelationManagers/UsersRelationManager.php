<?php

namespace App\Filament\Resources\EmpresaResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome do Usuário')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                    
                Tables\Columns\IconColumn::make('pivot.notificacao_email')
                    ->label('📧 Email')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                    
                Tables\Columns\IconColumn::make('pivot.notificacao_whatsapp')
                    ->label('📱 WhatsApp')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                    
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('pivot.notificacao_email')
                    ->label('Notificação Email')
                    ->boolean()
                    ->trueLabel('Com Email')
                    ->falseLabel('Sem Email')
                    ->native(false),
                    
                Tables\Filters\TernaryFilter::make('pivot.notificacao_whatsapp')
                    ->label('Notificação WhatsApp')
                    ->boolean()
                    ->trueLabel('Com WhatsApp')
                    ->falseLabel('Sem WhatsApp')
                    ->native(false),
                    
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Adicionar Usuário')
                    ->modalHeading('Adicionar Usuário à Empresa')
                    ->modalSubmitActionLabel('Adicionar')
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->orderBy('nome'))
                    ->form([
                        Forms\Components\Section::make('Configurações de Notificação')
                            ->schema([
                                Forms\Components\Toggle::make('notificacao_email')
                                    ->label('Receber Notificações por Email')
                                    ->default(true)
                                    ->helperText('Usuário receberá emails quando ocorrências desta empresa forem encontradas'),
                                    
                                Forms\Components\Toggle::make('notificacao_whatsapp')
                                    ->label('Receber Notificações por WhatsApp')
                                    ->default(false)
                                    ->helperText('Usuário receberá mensagens WhatsApp quando ocorrências desta empresa forem encontradas'),
                            ])->columns(2),
                    ]),
            ])
            ->actions([
                // Ação rápida para toggle WhatsApp
                Tables\Actions\Action::make('toggle_whatsapp')
                    ->label(fn ($record) => $record->pivot->notificacao_whatsapp ? 'Desabilitar WhatsApp' : 'Habilitar WhatsApp')
                    ->icon(fn ($record) => $record->pivot->notificacao_whatsapp ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->pivot->notificacao_whatsapp ? 'danger' : 'success')
                    ->action(function ($record) {
                        $empresa = $this->getOwnerRecord();
                        $novoValor = !$record->pivot->notificacao_whatsapp;
                        
                        $empresa->users()->updateExistingPivot($record->id, [
                            'notificacao_whatsapp' => $novoValor
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('WhatsApp ' . ($novoValor ? 'habilitado' : 'desabilitado'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DetachAction::make()
                    ->label('Remover')
                    ->requiresConfirmation()
                    ->modalHeading('Remover usuário da empresa?')
                    ->modalDescription('Esta ação removerá as configurações de notificação deste usuário para esta empresa.')
                    ->modalSubmitActionLabel('Sim, remover'),
                    
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->modalHeading('Configurar Notificações')
                    ->modalSubmitActionLabel('Salvar Configurações')
                    ->form([
                        Forms\Components\Section::make('Configurações de Notificação')
                            ->schema([
                                Forms\Components\Toggle::make('notificacao_email')
                                    ->label('Receber Notificações por Email')
                                    ->helperText('Usuário receberá emails quando ocorrências desta empresa forem encontradas'),
                                    
                                Forms\Components\Toggle::make('notificacao_whatsapp')
                                    ->label('Receber Notificações por WhatsApp')
                                    ->helperText('Usuário receberá mensagens WhatsApp quando ocorrências desta empresa forem encontradas'),
                            ])->columns(2),
                    ])
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        // Log para debug da leitura
                        \Illuminate\Support\Facades\Log::info('Lendo dados do usuário para edição', [
                            'user_id' => $record->id,
                            'pivot_data' => $record->pivot->getAttributes(),
                            'notificacao_email' => $record->pivot->notificacao_email,
                            'notificacao_whatsapp' => $record->pivot->notificacao_whatsapp
                        ]);
                        
                        // Preencher com dados do pivot
                        return [
                            'notificacao_email' => (bool) ($record->pivot->notificacao_email ?? true),
                            'notificacao_whatsapp' => (bool) ($record->pivot->notificacao_whatsapp ?? false),
                        ];
                    })
                    ->using(function (array $data, $record): bool {
                        // Preparar dados para atualização do pivot
                        $updateData = [
                            'notificacao_email' => (bool) ($data['notificacao_email'] ?? false),
                            'notificacao_whatsapp' => (bool) ($data['notificacao_whatsapp'] ?? false),
                        ];
                        
                        // Log para debug
                        \Illuminate\Support\Facades\Log::info('Atualizando configurações de usuário', [
                            'user_id' => $record->id,
                            'empresa_id' => $this->getOwnerRecord()->id,
                            'data_recebida' => $data,
                            'data_para_update' => $updateData
                        ]);
                        
                        // Atualizar através da empresa para garantir consistência
                        $empresa = $this->getOwnerRecord();
                        $empresa->users()->updateExistingPivot($record->id, $updateData);
                        
                        // Verificar se foi realmente salvo
                        $verificacao = \Illuminate\Support\Facades\DB::table('user_empresa_permissions')
                            ->where('user_id', $record->id)
                            ->where('empresa_id', $empresa->id)
                            ->first();
                            
                        \Illuminate\Support\Facades\Log::info('Verificação após salvamento', [
                            'user_id' => $record->id,
                            'empresa_id' => $empresa->id,
                            'notificacao_whatsapp_salvo' => $verificacao->notificacao_whatsapp ?? 'null'
                        ]);
                        
                        // Limpar cache
                        \Illuminate\Support\Facades\Cache::flush();
                        
                        \Illuminate\Support\Facades\Log::info('Configurações atualizadas com sucesso', [
                            'user_id' => $record->id,
                            'empresa_id' => $empresa->id
                        ]);
                        
                        return true;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Remover Selecionados')
                        ->requiresConfirmation(),
                        
                    Tables\Actions\BulkAction::make('enable_email')
                        ->label('Ativar Email')
                        ->icon('heroicon-o-envelope')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->pivot->update(['notificacao_email' => true]);
                            }
                        }),
                        
                    Tables\Actions\BulkAction::make('enable_whatsapp')
                        ->label('Ativar WhatsApp')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->pivot->update(['notificacao_whatsapp' => true]);
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('Nenhum usuário configurado')
            ->emptyStateDescription('Adicione usuários para configurar as notificações desta empresa.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
