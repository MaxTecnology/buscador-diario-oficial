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
                    
                Tables\Columns\TextColumn::make('pivot.nivel_prioridade')
                    ->label('Prioridade')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'alta' => 'danger',
                        'media' => 'warning',
                        'baixa' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'alta' => 'Alta',
                        'media' => 'Média',
                        'baixa' => 'Baixa',
                        default => 'N/A',
                    }),
                    
                Tables\Columns\IconColumn::make('pivot.notificacao_imediata')
                    ->label('⚡ Imediata')
                    ->boolean()
                    ->toggleable(),
                    
                Tables\Columns\IconColumn::make('pivot.resumo_diario')
                    ->label('📅 Resumo')
                    ->boolean()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('pivot.horario_resumo')
                    ->label('Horário')
                    ->time('H:i')
                    ->toggleable()
                    ->placeholder('N/A'),
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
                    
                Tables\Filters\SelectFilter::make('pivot.nivel_prioridade')
                    ->label('Nível de Prioridade')
                    ->options([
                        'alta' => 'Alta',
                        'media' => 'Média',
                        'baixa' => 'Baixa',
                    ])
                    ->multiple(),
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
                                    
                                Forms\Components\Select::make('nivel_prioridade')
                                    ->label('Nível de Prioridade')
                                    ->options([
                                        'baixa' => 'Baixa - Apenas ocorrências com score muito alto',
                                        'media' => 'Média - Score padrão da empresa',
                                        'alta' => 'Alta - Todas as ocorrências válidas',
                                    ])
                                    ->default('media')
                                    ->required()
                                    ->helperText('Define quais ocorrências disparam notificações para este usuário'),
                            ])->columns(2),
                            
                        Forms\Components\Section::make('Configurações Avançadas')
                            ->schema([
                                Forms\Components\Toggle::make('notificacao_imediata')
                                    ->label('Notificação Imediata')
                                    ->default(true)
                                    ->helperText('Enviar notificação assim que a ocorrência for detectada'),
                                    
                                Forms\Components\Toggle::make('resumo_diario')
                                    ->label('Resumo Diário')
                                    ->default(false)
                                    ->helperText('Receber um resumo diário das ocorrências encontradas'),
                                    
                                Forms\Components\TimePicker::make('horario_resumo')
                                    ->label('Horário do Resumo Diário')
                                    ->default('08:00:00')
                                    ->visible(fn (Forms\Get $get) => $get('resumo_diario'))
                                    ->helperText('Horário para envio do resumo diário'),
                            ])->columns(3),
                    ]),
            ])
            ->actions([
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
                                    
                                Forms\Components\Select::make('nivel_prioridade')
                                    ->label('Nível de Prioridade')
                                    ->options([
                                        'baixa' => 'Baixa - Apenas ocorrências com score muito alto',
                                        'media' => 'Média - Score padrão da empresa',
                                        'alta' => 'Alta - Todas as ocorrências válidas',
                                    ])
                                    ->required()
                                    ->helperText('Define quais ocorrências disparam notificações para este usuário'),
                            ])->columns(2),
                            
                        Forms\Components\Section::make('Configurações Avançadas')
                            ->schema([
                                Forms\Components\Toggle::make('notificacao_imediata')
                                    ->label('Notificação Imediata')
                                    ->helperText('Enviar notificação assim que a ocorrência for detectada'),
                                    
                                Forms\Components\Toggle::make('resumo_diario')
                                    ->label('Resumo Diário')
                                    ->helperText('Receber um resumo diário das ocorrências encontradas'),
                                    
                                Forms\Components\TimePicker::make('horario_resumo')
                                    ->label('Horário do Resumo Diário')
                                    ->visible(fn (Forms\Get $get) => $get('resumo_diario'))
                                    ->helperText('Horário para envio do resumo diário'),
                            ])->columns(3),
                    ])
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        // Preencher com dados do pivot
                        return [
                            'notificacao_email' => (bool) ($record->pivot->notificacao_email ?? true),
                            'notificacao_whatsapp' => (bool) ($record->pivot->notificacao_whatsapp ?? false),
                            'nivel_prioridade' => $record->pivot->nivel_prioridade ?? 'media',
                            'notificacao_imediata' => (bool) ($record->pivot->notificacao_imediata ?? true),
                            'resumo_diario' => (bool) ($record->pivot->resumo_diario ?? false),
                            'horario_resumo' => $record->pivot->horario_resumo ?? '08:00:00',
                        ];
                    })
                    ->using(function (array $data, $record): bool {
                        // Garantir que valores boolean sejam tratados corretamente
                        $data['notificacao_email'] = (bool) ($data['notificacao_email'] ?? false);
                        $data['notificacao_whatsapp'] = (bool) ($data['notificacao_whatsapp'] ?? false);
                        $data['notificacao_imediata'] = (bool) ($data['notificacao_imediata'] ?? true);
                        $data['resumo_diario'] = (bool) ($data['resumo_diario'] ?? false);
                        
                        $record->pivot->update($data);
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
