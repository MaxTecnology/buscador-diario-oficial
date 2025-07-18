<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConfiguracaoSistemaResource\Pages;
use App\Filament\Resources\ConfiguracaoSistemaResource\RelationManagers;
use App\Models\ConfiguracaoSistema;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ConfiguracaoSistemaResource extends Resource
{
    protected static ?string $model = ConfiguracaoSistema::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?string $navigationGroup = 'Sistema';
    
    protected static ?string $modelLabel = 'Configuração';
    
    protected static ?string $pluralModelLabel = 'Configurações do Sistema';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $slug = 'configuracoes-sistema';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuração')
                    ->schema([
                        Forms\Components\TextInput::make('chave')
                            ->label('Chave')
                            ->required()
                            ->disabled(fn ($operation) => $operation === 'edit')
                            ->maxLength(255),
                            
                        Forms\Components\Select::make('tipo')
                            ->label('Tipo')
                            ->options([
                                'string' => 'Texto',
                                'integer' => 'Número Inteiro',
                                'float' => 'Número Decimal',
                                'boolean' => 'Verdadeiro/Falso',
                                'json' => 'JSON',
                            ])
                            ->required()
                            ->reactive(),
                            
                        Forms\Components\TextInput::make('valor')
                            ->label('Valor')
                            ->required()
                            ->columnSpanFull()
                            ->visible(fn ($get) => !in_array($get('tipo'), ['boolean']) && $get('chave') !== 'notificacoes_whatsapp_template')
                            ->helperText(fn ($get) => match($get('tipo')) {
                                'integer' => 'Digite apenas números inteiros',
                                'float' => 'Digite números com ou sem decimais',
                                'json' => 'Digite um JSON válido',
                                default => null,
                            }),
                            
                        Forms\Components\Textarea::make('valor')
                            ->label('Template WhatsApp')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('chave') === 'notificacoes_whatsapp_template')
                            ->helperText('📝 **Como usar:**
                            
• Para quebrar linha: pressione Enter
• Use {empresa} para o nome da empresa
• Use {diario} para o nome do diário
• Use {score} para o score de confiança
• Use {data} para a data/hora
• Use *texto* para texto em negrito no WhatsApp

**Exemplo:**
🔔 *Nova Ocorrência*

📋 Empresa: {empresa}
📄 Diário: {diario}')
                            ->placeholder('🔔 *Nova Ocorrência Encontrada*

📋 Empresa: {empresa}
📄 Diário: {diario}
📊 Score: {score}
📅 Data: {data}'),
                            
                        Forms\Components\Toggle::make('valor_boolean')
                            ->label('Valor')
                            ->visible(fn ($get) => $get('tipo') === 'boolean')
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('valor', $state ? '1' : '0');
                            }),
                            
                        Forms\Components\Textarea::make('descricao')
                            ->label('Descrição')
                            ->maxLength(500)
                            ->columnSpanFull(),
                            
                        Forms\Components\Select::make('categoria')
                            ->label('Categoria')
                            ->options([
                                'geral' => 'Geral',
                                'notificacoes' => 'Notificações',
                                'processamento' => 'Processamento',
                                'sistema' => 'Sistema',
                            ])
                            ->default('geral')
                            ->required(),
                            
                        Forms\Components\Toggle::make('ativo')
                            ->label('Ativo')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('chave')
                    ->label('Chave')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('categoria')
                    ->label('Categoria')
                    ->colors([
                        'primary' => 'geral',
                        'success' => 'notificacoes',
                        'warning' => 'processamento',
                        'danger' => 'sistema',
                    ]),
                    
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('valor')
                    ->label('Valor')
                    ->limit(50)
                    ->formatStateUsing(function ($state, $record) {
                        return match($record->tipo) {
                            'boolean' => $state === '1' ? 'Sim' : 'Não',
                            default => $state,
                        };
                    }),
                    
                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->descricao),
                    
                Tables\Columns\IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categoria')
                    ->label('Categoria')
                    ->options([
                        'geral' => 'Geral',
                        'notificacoes' => 'Notificações',
                        'processamento' => 'Processamento',
                        'sistema' => 'Sistema',
                    ]),
                    
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'string' => 'Texto',
                        'integer' => 'Número Inteiro',
                        'float' => 'Número Decimal',
                        'boolean' => 'Verdadeiro/Falso',
                        'json' => 'JSON',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('ativo')
                    ->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_ativo')
                    ->label(fn ($record) => $record->ativo ? 'Desativar' : 'Ativar')
                    ->icon(fn ($record) => $record->ativo ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn ($record) => $record->ativo ? 'danger' : 'success')
                    ->action(fn ($record) => $record->update(['ativo' => !$record->ativo])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('ativar')
                        ->label('Ativar Selecionadas')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['ativo' => true])),
                        
                    Tables\Actions\BulkAction::make('desativar')
                        ->label('Desativar Selecionadas')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['ativo' => false])),
                ]),
            ])
            ->defaultSort('categoria');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('testar_notificacoes')
                ->label('Testar Notificações')
                ->icon('heroicon-o-bell')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\Select::make('tipo')
                        ->label('Tipo de Teste')
                        ->options([
                            'email' => 'Email',
                            'whatsapp' => 'WhatsApp',
                            'ambos' => 'Email e WhatsApp',
                        ])
                        ->required(),
                        
                    \Filament\Forms\Components\TextInput::make('destinatario')
                        ->label('Email ou Telefone')
                        ->required()
                        ->helperText('Digite o email ou telefone para teste'),
                ])
                ->action(function (array $data) {
                    // Implementar teste de notificações
                    \Filament\Notifications\Notification::make()
                        ->title('Teste Enviado!')
                        ->body('Notificação de teste enviada para: ' . $data['destinatario'])
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConfiguracaoSistemas::route('/'),
            'create' => Pages\CreateConfiguracaoSistema::route('/create'),
            'edit' => Pages\EditConfiguracaoSistema::route('/{record}/edit'),
        ];
    }
}
