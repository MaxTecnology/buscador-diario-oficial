<?php

namespace App\Filament\Pages;

use App\Models\Diario;
use App\Models\Ocorrencia;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;

class RelatorioDiarios extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static string $view = 'filament.pages.relatorio-diarios';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $title = 'Relatório de Diários';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'data_inicio' => now()->subDays(30),
            'data_fim' => now(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filtros do Relatório')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                DatePicker::make('data_inicio')
                                    ->label('Data Início')
                                    ->default(now()->subDays(30))
                                    ->maxDate(now()),
                                
                                DatePicker::make('data_fim')
                                    ->label('Data Fim')
                                    ->default(now())
                                    ->maxDate(now()),
                                
                                Select::make('estado')
                                    ->label('Estado')
                                    ->options([
                                        'AC' => 'Acre',
                                        'AL' => 'Alagoas',
                                        'AP' => 'Amapá',
                                        'AM' => 'Amazonas',
                                        'BA' => 'Bahia',
                                        'CE' => 'Ceará',
                                        'DF' => 'Distrito Federal',
                                        'ES' => 'Espírito Santo',
                                        'GO' => 'Goiás',
                                        'MA' => 'Maranhão',
                                        'MT' => 'Mato Grosso',
                                        'MS' => 'Mato Grosso do Sul',
                                        'MG' => 'Minas Gerais',
                                        'PA' => 'Pará',
                                        'PB' => 'Paraíba',
                                        'PR' => 'Paraná',
                                        'PE' => 'Pernambuco',
                                        'PI' => 'Piauí',
                                        'RJ' => 'Rio de Janeiro',
                                        'RN' => 'Rio Grande do Norte',
                                        'RS' => 'Rio Grande do Sul',
                                        'RO' => 'Rondônia',
                                        'RR' => 'Roraima',
                                        'SC' => 'Santa Catarina',
                                        'SP' => 'São Paulo',
                                        'SE' => 'Sergipe',
                                        'TO' => 'Tocantins',
                                    ])
                                    ->searchable()
                                    ->placeholder('Todos os estados'),
                                
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'pendente' => 'Pendente',
                                        'processando' => 'Processando',
                                        'concluido' => 'Concluído',
                                        'erro' => 'Erro',
                                    ])
                                    ->placeholder('Todos os status'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('nome_arquivo')
                    ->label('Nome do Diário')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                
                TextColumn::make('data_diario')
                    ->label('Data do Diário')
                    ->date('d/m/Y')
                    ->sortable(),
                
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pendente' => 'warning',
                        'processando' => 'info',
                        'concluido' => 'success',
                        'erro' => 'danger',
                        default => 'gray',
                    }),
                
                TextColumn::make('ocorrencias_count')
                    ->label('Ocorrências')
                    ->counts('ocorrencias')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('tamanho_arquivo')
                    ->label('Tamanho')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / (1024*1024), 1) . ' MB' : '-')
                    ->alignRight(),
                
                TextColumn::make('total_paginas')
                    ->label('Páginas')
                    ->alignCenter()
                    ->sortable(),
                
                TextColumn::make('created_at')
                    ->label('Data Upload')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                TextColumn::make('updated_at')
                    ->label('Última Atualização')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'AC' => 'Acre',
                        'AL' => 'Alagoas',
                        'AP' => 'Amapá',
                        'AM' => 'Amazonas',
                        'BA' => 'Bahia',
                        'CE' => 'Ceará',
                        'DF' => 'Distrito Federal',
                        'ES' => 'Espírito Santo',
                        'GO' => 'Goiás',
                        'MA' => 'Maranhão',
                        'MT' => 'Mato Grosso',
                        'MS' => 'Mato Grosso do Sul',
                        'MG' => 'Minas Gerais',
                        'PA' => 'Pará',
                        'PB' => 'Paraíba',
                        'PR' => 'Paraná',
                        'PE' => 'Pernambuco',
                        'PI' => 'Piauí',
                        'RJ' => 'Rio de Janeiro',
                        'RN' => 'Rio Grande do Norte',
                        'RS' => 'Rio Grande do Sul',
                        'RO' => 'Rondônia',
                        'RR' => 'Roraima',
                        'SC' => 'Santa Catarina',
                        'SP' => 'São Paulo',
                        'SE' => 'Sergipe',
                        'TO' => 'Tocantins',
                    ])
                    ->searchable(),
                
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pendente' => 'Pendente',
                        'processando' => 'Processando',
                        'concluido' => 'Concluído',
                        'erro' => 'Erro',
                    ]),
                
                Filter::make('com_ocorrencias')
                    ->label('Com Ocorrências')
                    ->query(fn (Builder $query): Builder => $query->has('ocorrencias')),
                
                Filter::make('sem_ocorrencias')
                    ->label('Sem Ocorrências')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('ocorrencias')),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        $query = Diario::query()->withCount('ocorrencias');

        $data = $this->form->getState();

        if (filled($data['data_inicio'])) {
            $query->whereDate('created_at', '>=', $data['data_inicio']);
        }

        if (filled($data['data_fim'])) {
            $query->whereDate('created_at', '<=', $data['data_fim']);
        }

        if (filled($data['estado'])) {
            $query->where('estado', $data['estado']);
        }

        if (filled($data['status'])) {
            $query->where('status', $data['status']);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar_csv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportarCSV'),
            
            Action::make('estatisticas')
                ->label('Estatísticas Detalhadas')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalContent(view('filament.pages.estatisticas-diarios', [
                    'stats' => $this->getEstatisticasDetalhadas()
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    public function exportarCSV()
    {
        $query = $this->getTableQuery();
        $diarios = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="relatorio_diarios_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($diarios) {
            $file = fopen('php://output', 'w');
            
            fwrite($file, "\xEF\xBB\xBF");
            
            fputcsv($file, [
                'Nome do Diário',
                'Estado',
                'Data do Diário',
                'Status',
                'Ocorrências',
                'Possui PDF',
                'Tamanho (MB)',
                'Total Páginas',
                'Data Upload',
                'Última Atualização',
            ], ';');

            foreach ($diarios as $diario) {
                fputcsv($file, [
                    $diario->nome_arquivo,
                    $diario->estado,
                    $diario->data_diario ? $diario->data_diario->format('d/m/Y') : '',
                    $diario->status,
                    $diario->ocorrencias_count,
                    $diario->caminho_arquivo ? 'Sim' : 'Não',
                    $diario->tamanho_arquivo ? number_format($diario->tamanho_arquivo / (1024*1024), 2) : '',
                    $diario->total_paginas ?? '',
                    $diario->created_at->format('d/m/Y H:i'),
                    $diario->updated_at->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    protected function getEstatisticasDetalhadas(): array
    {
        $data = $this->form->getState();

        // Query base com filtros aplicados
        $baseQuery = Diario::query();
        
        if (filled($data['data_inicio'])) {
            $baseQuery->whereDate('created_at', '>=', $data['data_inicio']);
        }

        if (filled($data['data_fim'])) {
            $baseQuery->whereDate('created_at', '<=', $data['data_fim']);
        }

        if (filled($data['estado'])) {
            $baseQuery->where('estado', $data['estado']);
        }

        if (filled($data['status'])) {
            $baseQuery->where('status', $data['status']);
        }

        // Estatísticas separadas para evitar conflitos
        $totalDiarios = (clone $baseQuery)->count();
        
        $porEstado = (clone $baseQuery)
            ->select('estado')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();
            
        $porStatus = (clone $baseQuery)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
            
        $comOcorrencias = (clone $baseQuery)->has('ocorrencias')->count();
        $semOcorrencias = (clone $baseQuery)->doesntHave('ocorrencias')->count();
        $comPdf = (clone $baseQuery)->whereNotNull('caminho_arquivo')->count();
        $semPdf = (clone $baseQuery)->whereNull('caminho_arquivo')->count();
        
        // Calcular média de ocorrências de forma diferente
        $diarios = (clone $baseQuery)->withCount('ocorrencias')->get();
        $mediaOcorrencias = $diarios->avg('ocorrencias_count') ?? 0;
        
        return [
            'total_diarios' => $totalDiarios,
            'por_estado' => $porEstado,
            'por_status' => $porStatus,
            'com_ocorrencias' => $comOcorrencias,
            'sem_ocorrencias' => $semOcorrencias,
            'com_pdf' => $comPdf,
            'sem_pdf' => $semPdf,
            'media_ocorrencias' => $mediaOcorrencias,
        ];
    }
}