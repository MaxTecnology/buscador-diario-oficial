<?php

namespace App\Filament\Widgets;

use App\Models\Diario;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UltimosProcessamentos extends BaseWidget
{
    protected static ?string $heading = 'Últimos Processamentos';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Diario::query()
                    ->with(['usuario', 'ocorrencias'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('nome_arquivo')
                    ->label('Arquivo')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('data_diario')
                    ->label('Data do Diário')
                    ->date('d/m/Y'),
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
                    }),
                Tables\Columns\TextColumn::make('ocorrencias_count')
                    ->label('Ocorrências')
                    ->counts('ocorrencias')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Usuário')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Diario $record): string => route('filament.admin.resources.diarios.edit', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}