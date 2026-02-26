<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OcorrenciaResource\Pages;
use App\Filament\Resources\OcorrenciaResource\RelationManagers;
use App\Models\Ocorrencia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use App\Services\NotificationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Filament\Tables\Actions\Action;

class OcorrenciaResource extends Resource
{
    protected static ?string $model = Ocorrencia::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationGroup = 'Resultados';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Ocorrência';

    protected static ?string $pluralModelLabel = 'Ocorrências';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações da Ocorrência')
                    ->schema([
                        Forms\Components\Select::make('diario_id')
                            ->label('Diário')
                            ->relationship('diario', 'nome_arquivo')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(),
                        Forms\Components\Select::make('empresa_id')
                            ->label('Empresa')
                            ->relationship('empresa', 'nome')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(),
                        Forms\Components\TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->disabled()
                            ->formatStateUsing(function ($state) {
                                if (!$state) return '-';
                                // Formatar CNPJ: 00.000.000/0000-00
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
                        Forms\Components\Select::make('tipo_match')
                            ->label('Tipo de Match')
                            ->options([
                                'cnpj' => 'CNPJ',
                                'nome' => 'Nome',
                                'inscricao_estadual' => 'Inscrição Estadual',
                                'variante' => 'Variante',
                                'termo_personalizado' => 'Termo Personalizado',
                            ])
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('termo_encontrado')
                            ->label('Termo Encontrado')
                            ->required()
                            ->maxLength(255)
                            ->disabled(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Detalhes do Match')
                    ->schema([
                        Forms\Components\TextInput::make('score_confianca')
                            ->label('Score de Confiança')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->disabled(),
                        Forms\Components\TextInput::make('pagina')
                            ->label('Página')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('posicao_inicio')
                            ->label('Posição Inicial')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('posicao_fim')
                            ->label('Posição Final')
                            ->numeric()
                            ->disabled(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Contexto')
                    ->schema([
                        Forms\Components\Textarea::make('contexto_completo')
                            ->label('Contexto Completo')
                            ->required()
                            ->rows(6)
                            ->columnSpanFull()
                            ->disabled(),
                    ]),
                    
                Forms\Components\Section::make('Notificações')
                    ->schema([
                        Forms\Components\Toggle::make('notificado_email')
                            ->label('Notificado por Email')
                            ->disabled(),
                        Forms\Components\Toggle::make('notificado_whatsapp')
                            ->label('Notificado por WhatsApp')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('notificado_em')
                            ->label('Notificado em')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('empresa.nome')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),
                Tables\Columns\TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        // Formatar CNPJ: 00.000.000/0000-00
                        $cnpj = preg_replace('/[^0-9]/', '', $state);
                        if (strlen($cnpj) === 14) {
                            return substr($cnpj, 0, 2) . '.' . 
                                   substr($cnpj, 2, 3) . '.' . 
                                   substr($cnpj, 5, 3) . '/' . 
                                   substr($cnpj, 8, 4) . '-' . 
                                   substr($cnpj, 12, 2);
                        }
                        return $state;
                    })
                    ->copyable()
                    ->tooltip('Clique para copiar'),
                Tables\Columns\TextColumn::make('diario.nome_arquivo')
                    ->label('Diário')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->tooltip(function ($record) {
                        return $record->diario->nome_arquivo;
                    }),
                Tables\Columns\TextColumn::make('diario.estado')
                    ->label('Estado')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('diario.data_diario')
                    ->label('Data do Diário')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('termo_encontrado')
                    ->label('Termo Encontrado')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(function ($record) {
                        return $record->termo_encontrado;
                    }),
                // Contexto removido da tabela para manter a lista mais limpa; detalhes via ação
                Tables\Columns\TextColumn::make('tipo_match')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cnpj' => 'success',
                        'inscricao_estadual' => 'warning',
                        'nome' => 'info',
                        'variante' => 'secondary',
                        'termo_personalizado' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cnpj' => 'CNPJ',
                        'inscricao_estadual' => 'Inscrição Estadual',
                        'nome' => 'Nome',
                        'variante' => 'Variante',
                        'termo_personalizado' => 'Termo Personalizado',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('confiabilidade')
                    ->label('Confiança')
                    ->badge()
                    ->color(fn ($state) => $state === 'alta' ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state === 'alta' ? 'Alta' : 'Suspeito'),
                Tables\Columns\TextColumn::make('status_revisao')
                    ->label('Revisão')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'aprovado' => 'success',
                        'falso_positivo' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'aprovado' => 'Aprovado',
                        'falso_positivo' => 'Falso positivo',
                        default => 'Pendente',
                    }),
                Tables\Columns\TextColumn::make('score_confianca')
                    ->label('Confiança')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 1) . '%')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state >= 0.95 => 'success',
                        $state >= 0.85 => 'warning',
                        $state >= 0.70 => 'info',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('pagina')
                    ->label('Página')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pdf_link')
                    ->label('PDF')
                    ->formatStateUsing(fn () => 'Abrir')
                    ->url(fn ($record) => static::pdfUrl($record), true)
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->tooltip('Abrir PDF na página encontrada'),
                Tables\Columns\IconColumn::make('notificado_email')
                    ->label('Email')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('notificado_whatsapp')
                    ->label('WhatsApp')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('notificado_em')
                    ->label('Notificado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Encontrado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('diario_id')
                    ->label('Diário')
                    ->relationship('diario', 'nome_arquivo')
                    ->searchable(),
                SelectFilter::make('diario_estado')
                    ->label('Estado')
                    ->options(fn () => \App\Models\Diario::query()->select('estado')->distinct()->pluck('estado', 'estado')->filter())
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn ($q, $estado) => $q->whereHas('diario', fn ($d) => $d->where('estado', $estado))
                        );
                    })
                    ->searchable(),
                SelectFilter::make('tipo_match')
                    ->label('🔍 Tipo de Match')
                    ->options([
                        'cnpj' => 'CNPJ',
                        'inscricao_estadual' => 'Inscrição Estadual',
                        'nome' => 'Nome',
                        'variante' => 'Variante',
                        'termo_personalizado' => 'Termo Personalizado',
                    ])
                    ->multiple(),
                SelectFilter::make('confiabilidade')
                    ->label('Confiança')
                    ->options([
                        'alta' => 'Alta',
                        'suspeito' => 'Suspeito',
                    ])
                    ->multiple(),
                SelectFilter::make('status_revisao')
                    ->label('Revisão')
                    ->options([
                        'pendente' => 'Pendente',
                        'aprovado' => 'Aprovado',
                        'falso_positivo' => 'Falso positivo',
                    ])
                    ->multiple(),
                Filter::make('score_range')
                    ->label('Score de Confiança')
                    ->form([
                        Forms\Components\TextInput::make('score_min')
                            ->label('Score mínimo')
                            ->numeric()
                            ->step(0.01)
                            ->placeholder('0.70'),
                        Forms\Components\TextInput::make('score_max')
                            ->label('Score máximo')
                            ->numeric()
                            ->step(0.01)
                            ->placeholder('1.00'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['score_min'] ?? null,
                                fn (Builder $query, $score): Builder => $query->where('score_confianca', '>=', $score),
                            )
                            ->when(
                                $data['score_max'] ?? null,
                                fn (Builder $query, $score): Builder => $query->where('score_confianca', '<=', $score),
                            );
                    }),
                Filter::make('periodo_ocorrencia')
                    ->label('Período (Encontrado em)')
                    ->form([
                        Forms\Components\DatePicker::make('de'),
                        Forms\Components\DatePicker::make('ate'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['de'] ?? null, fn ($q, $de) => $q->whereDate('created_at', '>=', $de))
                            ->when($data['ate'] ?? null, fn ($q, $ate) => $q->whereDate('created_at', '<=', $ate));
                    }),
                Filter::make('data_diario')
                    ->label('Data do Diário')
                    ->form([
                        Forms\Components\DatePicker::make('data_from')
                            ->label('Data inicial'),
                        Forms\Components\DatePicker::make('data_until')
                            ->label('Data final'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['data_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereHas('diario', fn ($q) => $q->whereDate('data_diario', '>=', $date)),
                            )
                            ->when(
                                $data['data_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereHas('diario', fn ($q) => $q->whereDate('data_diario', '<=', $date)),
                            );
                    }),
                // Filtros rápidos de período
                Filter::make('hoje')
                    ->label('🗓️ Hoje')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today()))
                    ->toggle(),
                    
                Filter::make('esta_semana')
                    ->label('📅 Esta Semana')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]))
                    ->toggle(),
                    
                Filter::make('este_mes')
                    ->label('📆 Este Mês')
                    ->query(fn (Builder $query): Builder => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year))
                    ->toggle(),
                    
                // Filtros de confiança rápidos
                Filter::make('score_alto')
                    ->label('🟢 Alta Confiança (>95%)')
                    ->query(fn (Builder $query): Builder => $query->where('score_confianca', '>=', 0.95))
                    ->toggle(),
                    
                Filter::make('score_medio')
                    ->label('🟡 Confiança Média (85-95%)')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('score_confianca', [0.85, 0.95]))
                    ->toggle(),
                    
                // Filtros de notificação
                Filter::make('nao_notificadas')
                    ->label('🔔 Não Notificadas')
                    ->query(fn (Builder $query): Builder => $query->where('notificado_email', false)->where('notificado_whatsapp', false))
                    ->toggle(),
                    
                SelectFilter::make('empresa_id')
                    ->label('🏢 Empresa')
                    ->relationship('empresa', 'nome')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                    
                TernaryFilter::make('notificado_email')
                    ->label('Notificado por Email')
                    ->boolean()
                    ->trueLabel('Sim')
                    ->falseLabel('Não')
                    ->native(false),
                TernaryFilter::make('notificado_whatsapp')
                    ->label('Notificado por WhatsApp')
                    ->boolean()
                    ->trueLabel('Sim')
                    ->falseLabel('Não')
                    ->native(false),
                Filter::make('data_diario')
                    ->label('Data do Diário')
                    ->form([
                        Forms\Components\DatePicker::make('data_from')
                            ->label('Data inicial'),
                        Forms\Components\DatePicker::make('data_until')
                            ->label('Data final'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['data_from'],
                                fn (Builder $query, $date): Builder => $query->whereHas('diario', fn ($q) => $q->whereDate('data_diario', '>=', $date)),
                            )
                            ->when(
                                $data['data_until'],
                                fn (Builder $query, $date): Builder => $query->whereHas('diario', fn ($q) => $q->whereDate('data_diario', '<=', $date)),
                            );
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Action::make('detalhes')
                        ->label('Detalhes')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detalhes da Ocorrência')
                    ->modalWidth('4xl')
                    ->modalContent(function ($record) {
                        $pdfUrl = $record->diario?->caminho_arquivo ? route('diarios.arquivo', ['diario' => $record->diario]) : null;
                        $pagina = $record->pagina ?? 1;
                        $contexto = e($record->contexto_completo ?? '');
                        if ($record->termo_encontrado) {
                            $pattern = '/' . preg_quote($record->termo_encontrado, '/') . '/i';
                            $contexto = preg_replace($pattern, '<mark>$0</mark>', $contexto);
                            }

                            return view('filament.ocorrencias.detalhes', [
                                'record' => $record,
                                'pdfUrl' => $pdfUrl ? $pdfUrl . '#page=' . $pagina : null,
                                'contextoHtml' => $contexto,
                            ]);
                        }),
                    Action::make('aprovar')
                        ->label('Aprovar')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn ($record) => $record->status_revisao !== 'aprovado')
                        ->requiresConfirmation()
                        ->action(fn ($record) => $record->update([
                            'status_revisao' => 'aprovado',
                            'confiabilidade' => $record->confiabilidade === 'suspeito' ? 'alta' : $record->confiabilidade,
                        ])),
                    Action::make('falso_positivo')
                        ->label('Falso positivo')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->visible(fn ($record) => $record->status_revisao !== 'falso_positivo')
                        ->requiresConfirmation()
                        ->action(fn ($record) => $record->update(['status_revisao' => 'falso_positivo'])),
                    Tables\Actions\Action::make('enviar_notificacoes')
                        ->label('Enviar Notificações')
                        ->icon('heroicon-o-bell')
                        ->color('primary')
                        ->form([
                            Forms\Components\Section::make('Selecionar Usuários e Tipos de Notificação')
                                ->schema([
                                    Forms\Components\Repeater::make('usuarios_notificacao')
                                        ->label('Usuários para Notificar')
                                        ->schema([
                                            Forms\Components\Select::make('user_id')
                                                ->label('Usuário')
                                                ->options(fn ($record) => static::usuariosNotificacaoOptions($record))
                                                ->required()
                                                ->searchable(),
                                            Forms\Components\CheckboxList::make('tipos')
                                                ->label('Tipos de Notificação')
                                                ->options([
                                                    'email' => 'Email',
                                                    'whatsapp' => 'WhatsApp',
                                                ])
                                                ->required()
                                                ->columns(2),
                                        ])
                                        ->default(fn ($record) => static::usuariosNotificacaoDefault($record))
                                        ->defaultItems(1)
                                        ->minItems(1)
                                        ->addActionLabel('Adicionar Usuário')
                                        ->columns(2),
                                ])
                        ])
                        ->action(function (array $data, $record) {
                            $resultados = ['email' => 0, 'whatsapp' => 0, 'erros' => []];
                            
                            foreach ($data['usuarios_notificacao'] as $usuarioNotif) {
                                $user = \App\Models\User::find($usuarioNotif['user_id']);
                                if (!$user) continue;
                                
                                $notificationService = app(\App\Services\NotificacaoService::class);
                                
                                foreach ($usuarioNotif['tipos'] as $tipo) {
                                    if ($tipo === 'email') {
                                        $enviadoEmail = $notificationService->enviarEmailParaUsuario($record, $user);
                                        if ($enviadoEmail) $resultados['email']++;
                                    } elseif ($tipo === 'whatsapp') {
                                        $enviadoWhatsapp = $notificationService->enviarWhatsAppParaUsuario($record, $user);
                                        if ($enviadoWhatsapp) $resultados['whatsapp']++;
                                    }
                                }
                            }
                            
                            $mensagens = [];
                            if ($resultados['email'] > 0) $mensagens[] = "{$resultados['email']} email(s)";
                            if ($resultados['whatsapp'] > 0) $mensagens[] = "{$resultados['whatsapp']} WhatsApp(s)";
                            
                            if (!empty($mensagens)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Notificações Enviadas!')
                                    ->body('Enviado: ' . implode(' e ', $mensagens))
                                    ->success()
                                    ->send();
                                    
                                // Atualizar flags de notificação
                                if ($resultados['email'] > 0) $record->marcarComoNotificadoPorEmail();
                                if ($resultados['whatsapp'] > 0) $record->marcarComoNotificadoPorWhatsapp();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Nenhuma Notificação Enviada')
                                    ->body('Verifique as configurações dos usuários.')
                                    ->warning()
                                    ->send();
                            }
                        })
                ])
                    ->label('Notificações')
                    ->icon('heroicon-o-bell')
                    ->size('sm'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('notificar_em_lote')
                        ->label('Notificar Selecionadas')
                        ->icon('heroicon-o-bell')
                        ->color('success')
                        ->action(function ($records) {
                            $notificationService = app(\App\Services\NotificacaoService::class);
                            $ocorrenciaIds = $records->pluck('id')->toArray();
                            
                            $resultados = $notificationService->notificarMultiplasOcorrencias($ocorrenciaIds, true);
                            
                            $totalEnviados = 0;
                            foreach ($resultados as $resultado) {
                                if ($resultado['email'] || $resultado['whatsapp']) {
                                    $totalEnviados++;
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Notificações Enviadas!')
                                ->body("Enviadas notificações para {$totalEnviados} ocorrências.")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Enviar Notificações em Lote')
                        ->modalDescription('Deseja enviar notificações para todas as ocorrências selecionadas?'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOcorrencias::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Schema::hasColumn('ocorrencias', 'ativo')) {
            $query->where('ativo', true);
        }

        return $query;
    }

    protected static function pdfUrl($record): ?string
    {
        if (!$record->diario?->caminho_arquivo) {
            return null;
        }
        $baseUrl = route('diarios.arquivo', ['diario' => $record->diario]);
        return $baseUrl . '#page=' . ($record->pagina ?? 1);
    }

    protected function ajustarEndpointPublico(?string $url): ?string
    {
        if (!$url) return null;
        $publicEndpoint = rtrim(env('DIARIOS_PUBLIC_ENDPOINT', ''), '/');
        $internalEndpoint = rtrim(env('DIARIOS_ENDPOINT', env('AWS_ENDPOINT', '')), '/');

        if ($publicEndpoint && $internalEndpoint) {
            return str_replace($internalEndpoint, $publicEndpoint, $url);
        }

        return $url;
    }

    protected static function usuariosNotificacaoOptions($record): array
    {
        $users = $record?->empresa?->users ?? collect();

        return $users->mapWithKeys(function ($user) {
            $nome = $user->nome ?? $user->name ?? 'Sem nome';
            $email = $user->email ?? 'sem-email';
            $telefone = $user->telefone_whatsapp ? " | WhatsApp: {$user->telefone_whatsapp}" : '';

            return [$user->id => "{$nome} ({$email}){$telefone}"];
        })->toArray();
    }

    protected static function usuariosNotificacaoDefault($record): array
    {
        $users = $record?->empresa?->users ?? collect();

        return $users->map(function ($user) {
            $tipos = [];
            $pivot = $user->pivot ?? null;

            if ($pivot?->notificacao_email) {
                $tipos[] = 'email';
            }

            if ($pivot?->notificacao_whatsapp) {
                $tipos[] = 'whatsapp';
            }

            return [
                'user_id' => $user->id,
                'tipos' => $tipos,
            ];
        })->values()->toArray();
    }
}
