<?php

namespace App\Filament\Widgets;

use App\Models\Ocorrencia;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OcorrenciasRecentes extends BaseWidget
{
    protected static ?string $heading = 'Ocorrências Recentes';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ocorrencia::query()
                    ->with(['empresa', 'diario'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('empresa.nome')
                    ->label('Empresa')
                    ->searchable()
                    ->weight('bold')
                    ->limit(25),
                Tables\Columns\TextColumn::make('cnpj')
                    ->label('CNPJ')
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
                    ->searchable(),
                Tables\Columns\TextColumn::make('diario.estado')
                    ->label('Estado')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('termo_encontrado')
                    ->label('Termo')
                    ->limit(20)
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipo_match')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cnpj' => 'success',
                        'nome' => 'info',
                        'termo_personalizado' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('score_confianca')
                    ->label('Confiança')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 1) . '%')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state >= 0.95 => 'success',
                        $state >= 0.85 => 'warning',
                        $state >= 0.70 => 'info',
                        default => 'danger',
                    }),
                Tables\Columns\IconColumn::make('notificado_email')
                    ->label('Email')
                    ->boolean(),
                Tables\Columns\IconColumn::make('notificado_whatsapp')
                    ->label('WhatsApp')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Encontrado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Ocorrencia $record): string => route('filament.admin.resources.ocorrencias.index', ['tableFilters' => ['empresa_id' => [$record->empresa_id]]]))
                    ->openUrlInNewTab(),
            ]);
    }
}