<?php

namespace App\Filament\Pages;

use App\Models\Ocorrencia;
use App\Models\Empresa;
use App\Models\Diario;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class RelatorioOcorrencias extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'filament.pages.relatorio-ocorrencias';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $title = 'Relatório de Ocorrências';

    protected static ?int $navigationSort = 1;

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
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('data_inicio')
                                    ->label('Data Início')
                                    ->default(now()->subDays(30))
                                    ->maxDate(now()),
                                
                                DatePicker::make('data_fim')
                                    ->label('Data Fim')
                                    ->default(now())
                                    ->maxDate(now()),
                                
                                Select::make('empresa_id')
                                    ->label('Empresa')
                                    ->options(Empresa::pluck('nome', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Todas as empresas'),
                            ]),
                        
                        Grid::make(3)
                            ->schema([
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
                                
                                Select::make('tipo_match')
                                    ->label('Tipo de Match')
                                    ->options([
                                        'nome' => 'Nome da Empresa',
                                        'cnpj' => 'CNPJ',
                                        'palavras_chave' => 'Palavras-chave',
                                    ])
                                    ->placeholder('Todos os tipos'),
                                
                                Select::make('score_min')
                                    ->label('Score Mínimo')
                                    ->options([
                                        '0.9' => '90% ou mais',
                                        '0.8' => '80% ou mais',
                                        '0.7' => '70% ou mais',
                                        '0.6' => '60% ou mais',
                                        '0.5' => '50% ou mais',
                                    ])
                                    ->placeholder('Qualquer score'),
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
                TextColumn::make('empresa.nome')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('diario.nome')
                    ->label('Diário')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('tipo_match')
                    ->label('Tipo Match')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'nome' => 'info',
                        'cnpj' => 'success',
                        'palavras_chave' => 'warning',
                        default => 'gray',
                    }),
                
                TextColumn::make('score_confianca')
                    ->label('Score')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 1) . '%')
                    ->sortable(),
                
                TextColumn::make('termo_encontrado')
                    ->label('Texto Encontrado')
                    ->limit(50)
                    ->searchable(),
                
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('empresa_id')
                    ->label('Empresa')
                    ->options(Empresa::pluck('nome', 'id'))
                    ->searchable(),
                
                SelectFilter::make('tipo_match')
                    ->label('Tipo Match')
                    ->options([
                        'nome' => 'Nome da Empresa',
                        'cnpj' => 'CNPJ',
                        'palavras_chave' => 'Palavras-chave',
                    ]),
                
                Filter::make('score_alto')
                    ->label('Score Alto (>= 80%)')
                    ->query(fn (Builder $query): Builder => $query->where('score_confianca', '>=', 0.8)),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        $query = Ocorrencia::query()->with(['empresa', 'diario']);

        $data = $this->form->getState();

        if (filled($data['data_inicio'])) {
            $query->whereDate('created_at', '>=', $data['data_inicio']);
        }

        if (filled($data['data_fim'])) {
            $query->whereDate('created_at', '<=', $data['data_fim']);
        }

        if (filled($data['empresa_id'])) {
            $query->where('empresa_id', $data['empresa_id']);
        }

        if (filled($data['estado'])) {
            $query->whereHas('diario', function (Builder $query) use ($data) {
                $query->where('estado', $data['estado']);
            });
        }

        if (filled($data['tipo_match'])) {
            $query->where('tipo_match', $data['tipo_match']);
        }

        if (filled($data['score_min'])) {
            $query->where('score_confianca', '>=', $data['score_min']);
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
            
            Action::make('exportar_excel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action('exportarExcel'),
            
            Action::make('gerar_pdf')
                ->label('Gerar PDF')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->action('gerarPDF'),
        ];
    }

    public function exportarCSV()
    {
        $query = $this->getTableQuery();
        $ocorrencias = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="relatorio_ocorrencias_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($ocorrencias) {
            $file = fopen('php://output', 'w');
            
            // Adicionar BOM para UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Cabeçalho
            fputcsv($file, [
                'Empresa',
                'CNPJ',
                'Diário',
                'Estado',
                'Tipo Match',
                'Score',
                'Texto Encontrado',
                'Data',
            ], ';');

            // Dados
            foreach ($ocorrencias as $ocorrencia) {
                fputcsv($file, [
                    $ocorrencia->empresa->nome,
                    $ocorrencia->empresa->cnpj,
                    $ocorrencia->diario->nome,
                    $ocorrencia->diario->estado,
                    $ocorrencia->tipo_match,
                    number_format($ocorrencia->score_confianca * 100, 1) . '%',
                    $ocorrencia->termo_encontrado,
                    $ocorrencia->created_at->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function exportarExcel()
    {
        // Implementar exportação Excel (requer maatwebsite/excel)
        // Por enquanto, redirecionar para CSV
        return $this->exportarCSV();
    }

    public function gerarPDF(): void
    {
        // Implementar geração de PDF (requer barryvdh/laravel-dompdf)
        // Por enquanto, mostrar notificação
        \Filament\Notifications\Notification::make()
            ->title('Funcionalidade em Desenvolvimento')
            ->body('A exportação PDF será implementada em breve.')
            ->warning()
            ->send();
    }
}