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
                            00000000000123;EMPRESA FICTICIA UM;EMPRESA FICTICIA UM;0;;
12345678000199;SUPERMERCADO EXEMPLO LTDA;SUPERMERCADO EXEMPLO;100200300;;
98765432000111;LOJA DEMO LTDA;LOJA DEMO;200300400;;
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
        $hasErros = !empty($resultado['erros']);
        $mensagemBase = "Importadas: {$resultado['importadas']} | Atualizadas: {$resultado['atualizadas']}";
        if ($hasErros) {
            $mensagemBase .= " | Erros: " . implode('; ', array_slice($resultado['erros'], 0, 5));
        }

        Notification::make()
            ->title($hasErros ? 'Importação concluída com avisos' : 'Importação concluída')
            ->body($mensagemBase)
            ->color($hasErros ? 'warning' : 'success')
            ->send();

        // Limpar formulário
        $this->form->fill();
    }

    protected function processarCsv(string $conteudoCsv): array
    {
        $linhas = preg_split("/\r\n|\n|\r/", trim($conteudoCsv));
        $importadas = 0;
        $atualizadas = 0;
        $erros = [];
        
        foreach ($linhas as $numeroLinha => $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }
            
            $dados = array_map('trim', explode(';', $linha));
            
            // Validar se tem pelo menos CNPJ/CPF e Razão Social
            if (count($dados) < 2 || empty($dados[0]) || empty($dados[1])) {
                $erros[] = "Linha " . ($numeroLinha + 1) . ": CNPJ/CPF e Razão Social são obrigatórios";
                continue;
            }
            
            $documentoRaw = preg_replace('/[^0-9]/', '', $dados[0]);
            $razaoSocial = $dados[1];
            $nomeFantasia = $dados[2] ?? $razaoSocial;
            $inscricaoEstadual = $dados[3] ?? '';
            $inscricaoEstadual = preg_replace('/[^0-9A-Za-z]/', '', $inscricaoEstadual);
            if ($inscricaoEstadual === '' || $inscricaoEstadual === '0') {
                $inscricaoEstadual = null;
            }
            // Observações (coluna 5) é ignorada por enquanto
            
            // Normalizar documento: CPF (11) ou CNPJ (12-14 -> pad left)
            $documento = null;
            $lenDoc = strlen($documentoRaw);
            if ($lenDoc === 11) {
                $documento = $documentoRaw;
            } elseif ($lenDoc >= 12 && $lenDoc <= 14) {
                $documento = str_pad($documentoRaw, 14, '0', STR_PAD_LEFT);
            } else {
                $erros[] = "Linha " . ($numeroLinha + 1) . ": CNPJ/CPF deve ter 11 ou até 14 dígitos (será completado com zeros se menor que 14)";
                continue;
            }
            
            try {
                $empresa = Empresa::firstOrNew(['cnpj' => $documento]);
                $jaExiste = $empresa->exists;
                
                $termosPersonalizados = $empresa->termos_personalizados ?? [];
                if (!is_array($termosPersonalizados)) {
                    $termosPersonalizados = [];
                }
                
                if (!empty($nomeFantasia) && $nomeFantasia !== $razaoSocial) {
                    $termosPersonalizados[] = $nomeFantasia;
                    $nomeFantasiaSemSufixo = preg_replace('/\s+(LTDA|ME|EIRELI|EPP|S\.A\.|SA)\.?\s*$/i', '', $nomeFantasia);
                    if (!empty($nomeFantasiaSemSufixo) && $nomeFantasiaSemSufixo !== $nomeFantasia) {
                        $termosPersonalizados[] = $nomeFantasiaSemSufixo;
                    }
                }

                $empresa->fill([
                    'nome' => $razaoSocial,
                    'inscricao_estadual' => $inscricaoEstadual,
                    'termos_personalizados' => array_values(array_unique(array_filter($termosPersonalizados))),
                    'prioridade' => $empresa->prioridade ?? 'media',
                    'score_minimo' => $empresa->score_minimo ?? 0.85,
                    'ativo' => $empresa->ativo ?? true,
                ]);

                if (!$jaExiste && auth()->id()) {
                    $empresa->created_by = auth()->id();
                }
                
                $empresa->save();
                
                if ($jaExiste) {
                    $atualizadas++;
                } else {
                    $importadas++;
                }
            } catch (\Throwable $e) {
                $erros[] = "Linha " . ($numeroLinha + 1) . ": Erro ao salvar - " . $e->getMessage();
            }
        }
        
        return [
            'importadas' => $importadas,
            'atualizadas' => $atualizadas,
            'erros' => $erros,
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
