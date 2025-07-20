<?php

namespace App\Filament\Pages;

use App\Models\Empresa;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;

class ImportarEmpresas extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    
    protected static ?string $navigationGroup = 'Gestão';
    
    protected static ?string $title = 'Importar Empresas';
    
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.importar-empresas';
    
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Upload de Arquivo CSV')
                    ->description('Faça upload de um arquivo CSV com as empresas para importar')
                    ->schema([
                        FileUpload::make('arquivo_csv')
                            ->label('Arquivo CSV')
                            ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                            ->maxSize(10240) // 10MB
                            ->helperText('Formato: CNPJ;RAZAO_SOCIAL;NOME_FANTASIA;INSCRICAO_ESTADUAL;OBSERVACOES')
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Ou Cole o Conteúdo CSV')
                    ->description('Você pode colar o conteúdo CSV diretamente aqui')
                    ->schema([
                        Textarea::make('conteudo_csv')
                            ->label('Conteúdo CSV')
                            ->rows(10)
                            ->placeholder('
                            12345678000199;SUPERMERCADO EXEMPLO LTDA;SUPERMERCADO EXEMPLO;100200300;;
98765432000987;LOJA DE TESTE LTDA;LOJA DE TESTE;200300400;;
45678912000456;MERCADO GENÉRICO SA;MERCADO GENÉRICO;300400500;;
                            ')
                            ->helperText('Uma linha por empresa, formato: CNPJ;RAZAO_SOCIAL;NOME_FANTASIA;INSCRICAO_ESTADUAL;OBSERVACOES')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function importar(): void
    {
        $data = $this->form->getState();
        
        $conteudoCsv = '';
        
        // Verificar se foi enviado arquivo ou conteúdo direto
        if (!empty($data['arquivo_csv'])) {
            $caminhoArquivo = Storage::disk('public')->path($data['arquivo_csv']);
            if (file_exists($caminhoArquivo)) {
                $conteudoCsv = file_get_contents($caminhoArquivo);
            }
        } elseif (!empty($data['conteudo_csv'])) {
            $conteudoCsv = $data['conteudo_csv'];
        }
        
        if (empty($conteudoCsv)) {
            Notification::make()
                ->title('Erro')
                ->body('Selecione um arquivo CSV ou cole o conteúdo CSV.')
                ->danger()
                ->send();
            return;
        }
        
        $resultado = $this->processarCsv($conteudoCsv);
        
        if ($resultado['sucesso']) {
            Notification::make()
                ->title('Importação Concluída!')
                ->body("Importadas: {$resultado['importadas']} empresas. Puladas: {$resultado['puladas']} (já existem).")
                ->success()
                ->send();
                
            // Limpar formulário
            $this->form->fill();
        } else {
            Notification::make()
                ->title('Erro na Importação')
                ->body($resultado['erro'])
                ->danger()
                ->send();
        }
    }

    protected function processarCsv(string $conteudoCsv): array
    {
        $linhas = array_filter(explode("\n", $conteudoCsv));
        $importadas = 0;
        $puladas = 0;
        $erros = [];
        
        foreach ($linhas as $numeroLinha => $linha) {
            $linha = trim($linha);
            if (empty($linha)) continue;
            
            $dados = array_map('trim', explode(';', $linha));
            
            // Validar se tem pelo menos CNPJ e Razão Social
            if (count($dados) < 2 || empty($dados[0]) || empty($dados[1])) {
                $erros[] = "Linha " . ($numeroLinha + 1) . ": CNPJ e Razão Social são obrigatórios";
                continue;
            }
            
            $cnpj = preg_replace('/[^0-9]/', '', $dados[0]);
            $razaoSocial = $dados[1];
            $nomeFantasia = $dados[2] ?? $razaoSocial;
            $inscricaoEstadual = $dados[3] ?? '';
            $observacoes = $dados[4] ?? '';
            
            // Validar CNPJ
            if (strlen($cnpj) !== 14) {
                $erros[] = "Linha " . ($numeroLinha + 1) . ": CNPJ deve ter 14 dígitos";
                continue;
            }
            
            // Verificar se já existe
            if (Empresa::where('cnpj', $cnpj)->exists()) {
                $puladas++;
                continue;
            }
            
            try {
                // Preparar termos personalizados
                $termosPersonalizados = [];
                
                // Adicionar nome fantasia se for diferente da razão social
                if (!empty($nomeFantasia) && $nomeFantasia !== $razaoSocial) {
                    $termosPersonalizados[] = $nomeFantasia;
                }
                
                // Adicionar variações do nome fantasia (abreviações, etc.)
                if (!empty($nomeFantasia) && $nomeFantasia !== $razaoSocial) {
                    // Adicionar versão sem LTDA, ME, etc.
                    $nomeFantasiaSemSufixo = preg_replace('/\s+(LTDA|ME|EIRELI|EPP|S\.A\.|SA)\.?\s*$/i', '', $nomeFantasia);
                    if ($nomeFantasiaSemSufixo !== $nomeFantasia && !in_array($nomeFantasiaSemSufixo, $termosPersonalizados)) {
                        $termosPersonalizados[] = $nomeFantasiaSemSufixo;
                    }
                }

                Empresa::create([
                    'nome' => $razaoSocial,
                    'cnpj' => $cnpj,
                    'inscricao_estadual' => $inscricaoEstadual,
                    'termos_personalizados' => $termosPersonalizados,
                    'prioridade' => 'media',
                    'score_minimo' => 0.85,
                    'ativo' => true,
                    'created_by' => auth()->id(),
                ]);
                
                $importadas++;
            } catch (\Exception $e) {
                $erros[] = "Linha " . ($numeroLinha + 1) . ": Erro ao salvar - " . $e->getMessage();
            }
        }
        
        if (!empty($erros)) {
            return [
                'sucesso' => false,
                'erro' => 'Erros encontrados: ' . implode('; ', array_slice($erros, 0, 5))
            ];
        }
        
        return [
            'sucesso' => true,
            'importadas' => $importadas,
            'puladas' => $puladas
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importar')
                ->label('Importar Empresas')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->action('importar'),
        ];
    }
}
