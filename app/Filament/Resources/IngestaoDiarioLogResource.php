<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IngestaoDiarioLogResource\Pages;
use App\Models\IngestaoDiarioLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IngestaoDiarioLogResource extends Resource
{
    protected static ?string $model = IngestaoDiarioLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';

    protected static ?string $navigationGroup = 'Admin';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Log de Ingestão';

    protected static ?string $pluralModelLabel = 'Logs de Ingestão';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recebido em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'enfileirado' => 'success',
                        'duplicado' => 'warning',
                        'rejeitado' => 'danger',
                        'erro' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Origem')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('external_id')
                    ->label('External ID')
                    ->searchable()
                    ->copyable()
                    ->limit(32)
                    ->tooltip(fn (IngestaoDiarioLog $record): ?string => $record->external_id),
                Tables\Columns\TextColumn::make('diario_id')
                    ->label('Diário')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('nome_arquivo')
                    ->label('Arquivo')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn (IngestaoDiarioLog $record): ?string => $record->nome_arquivo),
                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('data_diario')
                    ->label('Data Diário')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Tamanho')
                    ->formatStateUsing(function ($state): string {
                        if (! is_numeric($state) || (int) $state <= 0) {
                            return '-';
                        }

                        return number_format(((int) $state) / 1024 / 1024, 2) . ' MB';
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('signature_valid')
                    ->label('Assinatura')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('http_status')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state >= 200 && $state < 300 => 'success',
                        $state >= 400 && $state < 500 => 'warning',
                        $state >= 500 => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('object_key')
                    ->label('Object Key')
                    ->limit(40)
                    ->tooltip(fn (IngestaoDiarioLog $record): ?string => $record->object_key)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('mensagem')
                    ->label('Mensagem')
                    ->limit(45)
                    ->tooltip(fn (IngestaoDiarioLog $record): ?string => $record->mensagem)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('request_ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('idempotency_key')
                    ->label('Idempotency')
                    ->limit(32)
                    ->copyable()
                    ->tooltip(fn (IngestaoDiarioLog $record): ?string => $record->idempotency_key)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sha256')
                    ->label('SHA256')
                    ->limit(24)
                    ->copyable()
                    ->tooltip(fn (IngestaoDiarioLog $record): ?string => $record->sha256)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'recebido' => 'Recebido',
                        'enfileirado' => 'Enfileirado',
                        'duplicado' => 'Duplicado',
                        'rejeitado' => 'Rejeitado',
                        'erro' => 'Erro',
                    ]),
                SelectFilter::make('source')
                    ->options(fn (): array => IngestaoDiarioLog::query()
                        ->select('source')
                        ->distinct()
                        ->orderBy('source')
                        ->pluck('source', 'source')
                        ->toArray()),
                Filter::make('periodo')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('de')->label('De'),
                        \Filament\Forms\Components\DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['de'] ?? null, fn (Builder $q, $de) => $q->whereDate('created_at', '>=', $de))
                            ->when($data['ate'] ?? null, fn (Builder $q, $ate) => $q->whereDate('created_at', '<=', $ate));
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIngestaoDiarioLogs::route('/'),
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
}

