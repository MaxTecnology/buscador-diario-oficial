<?php

namespace App\Filament\Pages;

use App\Models\Empresa;
use App\Models\User;
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

class RelatorioEmpresas extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static string $view = 'filament.pages.relatorio-empresas';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $title = 'Relatório de Empresas';

    protected static ?int $navigationSort = 3;

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
                                
                                Select::make('created_by')
                                    ->label('Criado Por')
                                    ->options(User::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Todos os usuários'),
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
                TextColumn::make('nome')
                    ->label('Nome da Empresa')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('inscricao_estadual')
                    ->label('Inscrição Estadual')
                    ->searchable()
                    ->placeholder('Não informado'),
                
                TextColumn::make('prioridade')
                    ->label('Prioridade')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'alta' => 'danger',
                        'media' => 'warning',
                        'baixa' => 'success',
                        default => 'gray',
                    }),
                
                TextColumn::make('score_minimo')
                    ->label('Score Mínimo')
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 0) . '%'),
                
                TextColumn::make('ocorrencias_count')
                    ->label('Ocorrências')
                    ->counts('ocorrencias')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('users_count')
                    ->label('Usuários')
                    ->counts('users')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('createdBy.name')
                    ->label('Criado Por')
                    ->placeholder('Sistema')
                    ->sortable(),
                
                TextColumn::make('created_at')
                    ->label('Data Criação')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                TextColumn::make('updated_at')
                    ->label('Última Atualização')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('created_by')
                    ->label('Criado Por')
                    ->options(User::pluck('name', 'id'))
                    ->searchable(),
                
                Filter::make('com_ocorrencias')
                    ->label('Com Ocorrências')
                    ->query(fn (Builder $query): Builder => $query->has('ocorrencias')),
                
                Filter::make('sem_ocorrencias')
                    ->label('Sem Ocorrências')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('ocorrencias')),
                
                Filter::make('com_usuarios')
                    ->label('Com Usuários')
                    ->query(fn (Builder $query): Builder => $query->has('users')),
                
                Filter::make('sem_usuarios')
                    ->label('Sem Usuários')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('users')),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        $query = Empresa::query()
            ->withCount(['ocorrencias', 'users'])
            ->with('createdBy');

        $data = $this->form->getState();

        if (filled($data['data_inicio'])) {
            $query->whereDate('created_at', '>=', $data['data_inicio']);
        }

        if (filled($data['data_fim'])) {
            $query->whereDate('created_at', '<=', $data['data_fim']);
        }

        if (filled($data['created_by'])) {
            $query->where('created_by', $data['created_by']);
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
                ->modalContent(view('filament.pages.estatisticas-empresas', [
                    'stats' => $this->getEstatisticasDetalhadas()
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    public function exportarCSV()
    {
        $query = $this->getTableQuery();
        $empresas = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="relatorio_empresas_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($empresas) {
            $file = fopen('php://output', 'w');
            
            fwrite($file, "\xEF\xBB\xBF");
            
            fputcsv($file, [
                'Nome da Empresa',
                'CNPJ',
                'Inscrição Estadual',
                'Prioridade',
                'Score Mínimo',
                'Status',
                'Ocorrências',
                'Usuários',
                'Criado Por',
                'Data Criação',
                'Última Atualização',
            ], ';');

            foreach ($empresas as $empresa) {
                fputcsv($file, [
                    $empresa->nome,
                    $empresa->cnpj ?? '',
                    $empresa->inscricao_estadual ?? '',
                    $empresa->prioridade,
                    number_format($empresa->score_minimo * 100, 0) . '%',
                    $empresa->ativo ? 'Ativo' : 'Inativo',
                    $empresa->ocorrencias_count,
                    $empresa->users_count,
                    $empresa->createdBy?->name ?? 'Sistema',
                    $empresa->created_at->format('d/m/Y H:i'),
                    $empresa->updated_at->format('d/m/Y H:i'),
                ], ';');
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    protected function getEstatisticasDetalhadas(): array
    {
        $data = $this->form->getState();
        $baseQuery = Empresa::query();

        if (filled($data['data_inicio'])) {
            $baseQuery->whereDate('empresas.created_at', '>=', $data['data_inicio']);
        }

        if (filled($data['data_fim'])) {
            $baseQuery->whereDate('empresas.created_at', '<=', $data['data_fim']);
        }

        if (filled($data['created_by'])) {
            $baseQuery->where('created_by', $data['created_by']);
        }

        $totalEmpresas = (clone $baseQuery)->count();

        $porCriador = (clone $baseQuery)
            ->join('users', 'empresas.created_by', '=', 'users.id')
            ->groupBy('empresas.created_by', 'users.name')
            ->selectRaw('users.name, COUNT(empresas.id) as total')
            ->pluck('total', 'name')
            ->toArray();

        $porEstado = [];

        return [
            'total_empresas' => $totalEmpresas,
            'por_criador' => $porCriador,
            'por_estado' => $porEstado,
            'com_ocorrencias' => (clone $baseQuery)->has('ocorrencias')->count(),
            'sem_ocorrencias' => (clone $baseQuery)->doesntHave('ocorrencias')->count(),
            'com_usuarios' => (clone $baseQuery)->has('users')->count(),
            'sem_usuarios' => (clone $baseQuery)->doesntHave('users')->count(),
            'media_ocorrencias' => (clone $baseQuery)->withCount('ocorrencias')->get()->avg('ocorrencias_count') ?? 0,
            'media_usuarios' => (clone $baseQuery)->withCount('users')->get()->avg('users_count') ?? 0,
            'ativas' => (clone $baseQuery)->where('ativo', true)->count(),
            'inativas' => (clone $baseQuery)->where('ativo', false)->count(),
        ];
    }
}