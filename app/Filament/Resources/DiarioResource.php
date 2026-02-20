<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiarioResource\Pages;
use App\Filament\Resources\DiarioResource\RelationManagers;
use App\Models\Diario;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class DiarioResource extends Resource
{
    protected static ?string $model = Diario::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Processamento';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Diário';

    protected static ?string $pluralModelLabel = 'Diários';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Upload de Arquivo')
                    ->schema([
                        Forms\Components\FileUpload::make('arquivo')
                            ->label('Arquivo PDF')
                            ->directory('diarios')
                            ->disk(config('filesystems.diarios_disk', 'diarios'))
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(50 * 1024) // 50MB
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Selecione um arquivo PDF do diário oficial')
                            ->storeFileNamesIn('nome_arquivo_original')
                            ->preserveFilenames(),
                    ])
                    ->hiddenOn('edit'),
                    
                Forms\Components\Section::make('Informações do Diário')
                    ->schema([
                        Forms\Components\TextInput::make('nome_arquivo')
                            ->label('Nome do Arquivo')
                            ->required(fn ($operation) => $operation === 'edit')
                            ->maxLength(255)
                            ->disabled(fn ($operation) => $operation === 'edit')
                            ->helperText('Será preenchido automaticamente com o nome do arquivo enviado'),
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá',
                                'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará',
                                'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
                                'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso',
                                'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
                                'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
                                'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro',
                                'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
                                'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
                                'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
                            ])
                            ->required()
                            ->searchable(),
                        Forms\Components\DatePicker::make('data_diario')
                            ->label('Data do Diário')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now()),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Informações Técnicas')
                    ->schema([
                        Forms\Components\TextInput::make('hash_sha256')
                            ->label('Hash SHA256')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('tamanho_arquivo')
                            ->label('Tamanho do Arquivo')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($state) {
                                if (!$state) return '';
                                return number_format($state / 1024 / 1024, 2) . ' MB';
                            }),
                        Forms\Components\TextInput::make('total_paginas')
                            ->label('Total de Páginas')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pendente' => 'Pendente',
                                'processando' => 'Processando',
                                'concluido' => 'Concluído',
                                'erro' => 'Erro',
                            ])
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2)
                    ->hiddenOn('create'),
                    
                Forms\Components\Section::make('Processamento')
                    ->schema([
                        Forms\Components\DateTimePicker::make('processado_em')
                            ->label('Processado em')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('tentativas')
                            ->label('Tentativas')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('erro_mensagem')
                            ->label('Mensagem de Erro')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3),
                        Forms\Components\Select::make('usuario_upload_id')
                            ->label('Usuário do Upload')
                            ->relationship('usuario', 'name')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2)
                    ->hiddenOn('create'),
                    
                Forms\Components\Section::make('Texto Extraído')
                    ->schema([
                        Forms\Components\Textarea::make('texto_extraido')
                            ->label('Texto Extraído')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(10)
                            ->columnSpanFull()
                            ->formatStateUsing(function ($state, $record) {
                                if (!$state) return 'Nenhum texto extraído ainda';
                                
                                $texto = $state;
                                if ($record && $record->caminho_texto_completo) {
                                    $texto .= "\n\n📄 Arquivo completo disponível para download";
                                }
                                
                                return $texto;
                            }),
                    ])
                    ->collapsible()
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome_arquivo')
                    ->label('Nome do Arquivo')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function ($record) {
                        return $record->nome_arquivo;
                    })
                    ->description(function ($record) {
                        // Mostrar se há duplicatas baseado no hash
                        $duplicatas = \App\Models\Diario::where('hash_sha256', $record->hash_sha256)
                            ->where('id', '!=', $record->id)
                            ->count();
                        return $duplicatas > 0 ? "⚠️ {$duplicatas} arquivo(s) similar(es)" : null;
                    }),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_diario')
                    ->label('Data do Diário')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pendente' => 'warning',
                        'processando' => 'info', 
                        'concluido' => 'success',
                        'erro' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pendente' => 'Pendente',
                        'processando' => 'Processando',
                        'concluido' => 'Concluído',
                        'erro' => 'Erro',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('tamanho_arquivo')
                    ->label('Tamanho')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '';
                        return number_format($state / 1024 / 1024, 2) . ' MB';
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_paginas')
                    ->label('Páginas')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ocorrencias_count')
                    ->label('Ocorrências')
                    ->counts('ocorrencias')
                    ->sortable()
                    ->color('primary')
                    ->weight(FontWeight::Bold),
                    
                Tables\Columns\IconColumn::make('texto_disponivel')
                    ->label('Texto')
                    ->icon(fn ($record) => $record->caminho_texto_completo ? 'heroicon-o-document-text' : 'heroicon-o-x-mark')
                    ->color(fn ($record) => $record->caminho_texto_completo ? 'success' : 'gray')
                    ->tooltip(fn ($record) => $record->caminho_texto_completo ? 'Texto extraído disponível' : 'Texto não disponível'),
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('processado_em')
                    ->label('Processado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tentativas')
                    ->label('Tentativas')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
                    
                // Filtros de status com emojis
                Filter::make('processados')
                    ->label('✅ Processados')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'concluido'))
                    ->toggle(),
                    
                Filter::make('com_erro')
                    ->label('❌ Com Erro')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'erro'))
                    ->toggle(),
                    
                Filter::make('com_ocorrencias')
                    ->label('🔍 Com Ocorrências')
                    ->query(fn (Builder $query): Builder => $query->whereHas('ocorrencias'))
                    ->toggle(),
                    
                Filter::make('sem_ocorrencias')
                    ->label('🈳 Sem Ocorrências')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('ocorrencias'))
                    ->toggle(),
                    
                // Filtros de tamanho
                Filter::make('arquivos_grandes')
                    ->label('📁 Arquivos Grandes (>10MB)')
                    ->query(fn (Builder $query): Builder => $query->where('tamanho_arquivo', '>', 10485760))
                    ->toggle(),
                    
                Filter::make('muitas_paginas')
                    ->label('📄 Muitas Páginas (>200)')
                    ->query(fn (Builder $query): Builder => $query->where('total_paginas', '>', 200))
                    ->toggle(),
                    
                SelectFilter::make('estado')
                    ->label('🗺️ Estado')
                    ->options([
                        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá',
                        'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará',
                        'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
                        'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso',
                        'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
                        'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
                        'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro',
                        'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
                        'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
                        'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
                    ])
                    ->multiple()
                    ->searchable(),
                    
                SelectFilter::make('status')
                    ->label('📊 Status')
                    ->options([
                        'pendente' => 'Pendente',
                        'processando' => 'Processando',
                        'concluido' => 'Concluído',
                        'erro' => 'Erro',
                    ])
                    ->multiple(),
                Filter::make('data_diario')
                    ->label('📅 Data do Diário')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('data_diario', '>=', $date),
                            )
                            ->when(
                                $data['data_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data_diario', '<=', $date),
                            );
                    }),
                    
                Filter::make('filtro_paginas')
                    ->label('📄 Filtro de Páginas')
                    ->form([
                        Forms\Components\TextInput::make('min_paginas')
                            ->label('Páginas mínimas')
                            ->numeric()
                            ->placeholder('Ex: 50'),
                        Forms\Components\TextInput::make('max_paginas')
                            ->label('Páginas máximas')
                            ->numeric()
                            ->placeholder('Ex: 500'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_paginas'],
                                fn (Builder $query, $min): Builder => $query->where('total_paginas', '>=', $min),
                            )
                            ->when(
                                $data['max_paginas'],
                                fn (Builder $query, $max): Builder => $query->where('total_paginas', '<=', $max),
                            );
                    }),
                    
                Filter::make('filtro_tamanho')
                    ->label('💾 Filtro de Tamanho')
                    ->form([
                        Forms\Components\Select::make('faixa_tamanho')
                            ->label('Faixa de tamanho')
                            ->options([
                                'pequeno' => 'Pequenos (< 5MB)',
                                'medio' => 'Médios (5MB - 10MB)',
                                'grande' => 'Grandes (> 10MB)',
                            ])
                            ->placeholder('Selecione uma faixa'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['faixa_tamanho'],
                            function (Builder $query, $faixa) {
                                return match($faixa) {
                                    'pequeno' => $query->where('tamanho_arquivo', '<', 5242880), // 5MB
                                    'medio' => $query->whereBetween('tamanho_arquivo', [5242880, 10485760]), // 5-10MB
                                    'grande' => $query->where('tamanho_arquivo', '>', 10485760), // >10MB
                                    default => $query,
                                };
                            }
                        );
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pendente'),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pendente'),
                Tables\Actions\Action::make('processar')
                    ->label('Processar')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['pendente', 'erro']))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'processando',
                            'status_processamento' => 'processando',
                            'erro_mensagem' => null,
                            'erro_processamento' => null,
                        ]);

                        $processamentoAssincrono = \App\Models\ConfiguracaoSistema::get('processamento_assincrono', true);

                        if ($processamentoAssincrono) {
                            \App\Jobs\ProcessarPdfJob::dispatch($record);
                            \Filament\Notifications\Notification::make()
                                ->title('Processamento enfileirado')
                                ->body('O diário foi enviado para a fila. Você será notificado ao concluir.')
                                ->success()
                                ->send();
                        } else {
                            try {
                                $processorService = new \App\Services\PdfProcessorService();
                                $resultado = $processorService->processarPdf($record);

                                if ($resultado['sucesso']) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('PDF Processado!')
                                        ->body("Ocorrências encontradas: {$resultado['ocorrencias_encontradas']}")
                                        ->success()
                                        ->send();
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Erro no Processamento')
                                        ->body($resultado['erro'])
                                        ->danger()
                                        ->send();
                                }
                            } catch (\Throwable $e) {
                                \App\Jobs\ProcessarPdfJob::dispatch($record);
                                \Filament\Notifications\Notification::make()
                                    ->title('Enviado para fila')
                                    ->body('Processamento síncrono falhou; o diário foi enviado para a fila.')
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),
                Tables\Actions\Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn ($record) => $record->caminho_arquivo)
                    ->action(function ($record) {
                        $disk = Storage::disk(config('filesystems.diarios_disk', 'diarios'));
                        $stream = $disk->readStream($record->caminho_arquivo);
                        if (!$stream) {
                            \Filament\Notifications\Notification::make()
                                ->title('Arquivo indisponível')
                                ->body('Não foi possível ler o PDF do storage.')
                                ->danger()
                                ->send();
                            return;
                        }
                        $filename = $record->nome_arquivo ?? basename($record->caminho_arquivo);
                        return response()->streamDownload(function () use ($stream) {
                            fpassthru($stream);
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }, $filename, [
                            'Content-Type' => 'application/pdf',
                        ]);
                    }),
                    
                Tables\Actions\Action::make('download_texto')
                    ->label('Download Texto')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(fn ($record) => $record->caminho_texto_completo && Storage::disk(config('filesystems.diarios_disk', 'diarios'))->exists($record->caminho_texto_completo))
                    ->action(function ($record) {
                        $disk = Storage::disk(config('filesystems.diarios_disk', 'diarios'));
                        $conteudo = $disk->get($record->caminho_texto_completo);
                        $nomeArquivo = 'texto_extraido_' . $record->nome_arquivo . '.txt';
                        
                        return response()->streamDownload(function () use ($conteudo) {
                            echo $conteudo;
                        }, $nomeArquivo, [
                            'Content-Type' => 'text/plain; charset=utf-8',
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('reprocessar')
                        ->label('Processar Selecionados')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $processamentoAssincrono = \App\Models\ConfiguracaoSistema::get('processamento_assincrono', true);
                            
                            $records->each(function ($record) use ($processamentoAssincrono) {
                                if (!in_array($record->status, ['pendente', 'erro'])) {
                                    return;
                                }
                                
                                $record->update([
                                    'status' => 'processando',
                                    'status_processamento' => 'processando',
                                    'erro_mensagem' => null,
                                    'erro_processamento' => null,
                                ]);

                                if ($processamentoAssincrono) {
                                    \App\Jobs\ProcessarPdfJob::dispatch($record);
                                } else {
                                    try {
                                        $processorService = new \App\Services\PdfProcessorService();
                                        $processorService->processarPdf($record);
                                    } catch (\Throwable $e) {
                                        \App\Jobs\ProcessarPdfJob::dispatch($record);
                                    }
                                }
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Processamento iniciado')
                                ->body('Os diários selecionados foram enviados para processamento.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            'index' => Pages\ListDiarios::route('/'),
            'create' => Pages\CreateDiario::route('/create'),
            'edit' => Pages\EditDiario::route('/{record}/edit'),
        ];
    }

    protected static function pdfUrl($record): ?string
    {
        if (!$record?->caminho_arquivo) {
            return null;
        }
        return route('diarios.arquivo', ['diario' => $record]);
    }

    protected static function ajustarEndpointPublico(?string $url): ?string
    {
        if (!$url) return null;
        $publicEndpoint = rtrim(env('DIARIOS_PUBLIC_ENDPOINT', ''), '/');
        $internalEndpoint = rtrim(env('DIARIOS_ENDPOINT', env('AWS_ENDPOINT', '')), '/');

        if ($publicEndpoint && $internalEndpoint) {
            return str_replace($internalEndpoint, $publicEndpoint, $url);
        }

        return $url;
    }
}
