<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmpresaResource\Pages;
use App\Filament\Resources\EmpresaResource\RelationManagers;
use App\Models\Empresa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Gestão';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Empresa';

    protected static ?string $pluralModelLabel = 'Empresas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações Básicas')
                    ->schema([
                        Forms\Components\TextInput::make('nome')
                            ->label('Nome da Empresa')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->maxLength(18)
                            ->mask('99.999.999/9999-99')
                            ->unique(Empresa::class, 'cnpj', ignoreRecord: true)
                            ->dehydrateStateUsing(fn ($state) => preg_replace('/[^0-9]/', '', $state))
                            ->formatStateUsing(function ($state) {
                                if (!$state) return '';
                                $cnpj = preg_replace('/[^0-9]/', '', $state);
                                if (strlen($cnpj) === 14) {
                                    return substr($cnpj, 0, 2) . '.' . 
                                           substr($cnpj, 2, 3) . '.' . 
                                           substr($cnpj, 5, 3) . '/' . 
                                           substr($cnpj, 8, 4) . '-' . 
                                           substr($cnpj, 12, 2);
                                }
                                return $state;
                            }),
                        Forms\Components\TextInput::make('inscricao_estadual')
                            ->label('Inscrição Estadual')
                            ->maxLength(255),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Configurações')
                    ->schema([
                        Forms\Components\Select::make('prioridade')
                            ->label('Prioridade da Empresa')
                            ->options([
                                'alta' => 'Alta (+10% no score)',
                                'media' => 'Média (+5% no score)',
                                'baixa' => 'Baixa (sem bônus)',
                            ])
                            ->default('media')
                            ->required()
                            ->helperText('Prioridade mais alta = bonus no score de confiança = mais chances de detectar ocorrências'),
                        Forms\Components\TextInput::make('score_minimo')
                            ->label('Score Mínimo de Confiança')
                            ->numeric()
                            ->step(1)
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(85)
                            ->required()
                            ->suffix('%')
                            ->formatStateUsing(fn ($state) => $state ? round($state * 100) : 85)
                            ->dehydrateStateUsing(fn ($state) => $state ? $state / 100 : 0.85)
                            ->helperText('Score de 1-100%. Apenas ocorrências com score igual ou superior serão registradas.')
                            ->hint('Clique no ℹ️ para entender como funciona')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintColor('info'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('ℹ️ Como Funciona o Score de Confiança')
                    ->schema([
                        Forms\Components\Placeholder::make('score_explicacao')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="space-y-3 text-sm">
                                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                        <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">📊 Como é Calculado o Score:</h4>
                                        <div class="space-y-1 text-blue-700 dark:text-blue-300">
                                            <div><strong>Score Base:</strong> 50%</div>
                                            <div><strong>+ Termo Exato:</strong> +20% (palavra completa, não parcial)</div>
                                            <div><strong>+ CNPJ Próximo:</strong> +25% (CNPJ da empresa no contexto)</div>
                                            <div><strong>+ Prioridade Alta:</strong> +10% | <strong>Média:</strong> +5%</div>
                                            <div><strong>- Termo Muito Comum:</strong> -10% (aparece +10x no texto)</div>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                                            <h5 class="font-semibold text-green-800 dark:text-green-200 mb-1">✅ Exemplo: Score Alto (95%)</h5>
                                            <div class="text-xs text-green-700 dark:text-green-300">
                                                Encontrado: "ACME LTDA" + CNPJ<br>
                                                Cálculo: 50% + 20% + 25% = 95%<br>
                                                <span class="font-semibold">✅ PASSA (≥ 85%)</span>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-lg">
                                            <h5 class="font-semibold text-red-800 dark:text-red-200 mb-1">❌ Exemplo: Score Baixo (75%)</h5>
                                            <div class="text-xs text-red-700 dark:text-red-300">
                                                Encontrado: "ACME" (parcial, sem CNPJ)<br>
                                                Cálculo: 50% + 5% + 20% = 75%<br>
                                                <span class="font-semibold">❌ NÃO PASSA (< 85%)</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg">
                                        <h5 class="font-semibold text-amber-800 dark:text-amber-200 mb-1">⚙️ Dicas de Configuração:</h5>
                                        <div class="text-xs text-amber-700 dark:text-amber-300 space-y-1">
                                            <div><strong>Score Alto (90-95%):</strong> Mais rigoroso, menos falsos positivos</div>
                                            <div><strong>Score Médio (80-85%):</strong> Balanceado (recomendado)</div>
                                            <div><strong>Score Baixo (60-75%):</strong> Mais flexível, pega mais ocorrências</div>
                                        </div>
                                    </div>
                                </div>
                            '))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
                    
                Forms\Components\Section::make('Configurações de Busca')
                    ->schema([
                        Forms\Components\TagsInput::make('termos_personalizados')
                            ->label('Termos de Busca Personalizados')
                            ->placeholder('Digite um termo e pressione Enter')
                            ->helperText('Termos adicionais para buscar esta empresa nos diários'),
                        Forms\Components\Toggle::make('ativo')
                            ->label('Empresa Ativa')
                            ->default(true),
                    ]),
                    
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        if (!$state || strlen($state) !== 14) return $state;
                        return substr($state, 0, 2) . '.' . substr($state, 2, 3) . '.' . substr($state, 5, 3) . '/' . substr($state, 8, 4) . '-' . substr($state, 12, 2);
                    }),
                Tables\Columns\TextColumn::make('inscricao_estadual')
                    ->label('Inscrição Estadual')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('prioridade')
                    ->label('Prioridade')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'alta' => 'danger',
                        'media' => 'warning',
                        'baixa' => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'alta' => 'Alta',
                        'media' => 'Média',
                        'baixa' => 'Baixa',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('score_minimo')
                    ->label('Score Mínimo')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 0) . '%')
                    ->sortable(),
                Tables\Columns\IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ocorrencias_count')
                    ->label('Ocorrências')
                    ->counts('ocorrencias')
                    ->sortable()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuários')
                    ->counts('users')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('prioridade')
                    ->label('Prioridade')
                    ->options([
                        'alta' => 'Alta',
                        'media' => 'Média',
                        'baixa' => 'Baixa',
                    ])
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('ativo')
                    ->label('Status')
                    ->boolean()
                    ->trueLabel('Ativo')
                    ->falseLabel('Inativo')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (Empresa $record) => $record->ativo ? 'Desativar' : 'Ativar')
                    ->icon(fn (Empresa $record) => $record->ativo ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Empresa $record) => $record->ativo ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Empresa $record) {
                        $record->update(['ativo' => !$record->ativo]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Ativar Selecionadas')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['ativo' => true]));
                        })
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Desativar Selecionadas')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(function ($records) {
                            $records->each(fn ($record) => $record->update(['ativo' => false]));
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmpresas::route('/'),
            'create' => Pages\CreateEmpresa::route('/create'),
            'view' => Pages\ViewEmpresa::route('/{record}'),
            'edit' => Pages\EditEmpresa::route('/{record}/edit'),
        ];
    }
}
