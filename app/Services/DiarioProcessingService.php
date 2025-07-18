<?php

namespace App\Services;

use App\Models\Diario;
use App\Models\SystemConfig;
use Illuminate\Support\Facades\Log;

class DiarioProcessingService
{
    private PdfProcessingService $pdfService;
    private EmpresaSearchService $searchService;

    public function __construct(
        PdfProcessingService $pdfService,
        EmpresaSearchService $searchService
    ) {
        $this->pdfService = $pdfService;
        $this->searchService = $searchService;
    }

    public function processarDiarioCompleto(Diario $diario): array
    {
        $startTime = microtime(true);
        $resultado = [
            'sucesso' => false,
            'etapas_concluidas' => [],
            'ocorrencias_encontradas' => 0,
            'empresas_encontradas' => 0,
            'tempo_total_ms' => 0,
            'erros' => [],
        ];

        try {
            Log::info('Iniciando processamento completo do diário', [
                'diario_id' => $diario->id,
                'arquivo' => $diario->nome_arquivo,
            ]);

            // Etapa 1: Extração de texto do PDF
            if (empty($diario->texto_extraido)) {
                Log::info('Extraindo texto do PDF', ['diario_id' => $diario->id]);
                
                if (!$this->pdfService->extractTextFromPdf($diario)) {
                    throw new \Exception('Falha na extração de texto do PDF: ' . $diario->erro_mensagem);
                }
                
                $resultado['etapas_concluidas'][] = 'extracao_texto';
                Log::info('Texto extraído com sucesso', [
                    'diario_id' => $diario->id,
                    'caracteres_extraidos' => strlen($diario->fresh()->texto_extraido),
                ]);
            } else {
                $resultado['etapas_concluidas'][] = 'extracao_texto';
                Log::info('Texto já extraído anteriormente', ['diario_id' => $diario->id]);
            }

            // Recarregar o diário para pegar o texto extraído
            $diario = $diario->fresh();

            // Etapa 2: Busca de empresas no texto
            Log::info('Iniciando busca de empresas', ['diario_id' => $diario->id]);
            
            $ocorrencias = $this->searchService->buscarEmpresasNoTexto($diario);
            
            $resultado['etapas_concluidas'][] = 'busca_empresas';
            $resultado['ocorrencias_encontradas'] = count($ocorrencias);
            $resultado['empresas_encontradas'] = collect($ocorrencias)->pluck('empresa_id')->unique()->count();

            // Etapa 3: Indexação no Laravel Scout (se configurado)
            if ($this->shouldIndexInScout()) {
                try {
                    $diario->searchable();
                    $resultado['etapas_concluidas'][] = 'indexacao_scout';
                    Log::info('Diário indexado no Scout', ['diario_id' => $diario->id]);
                } catch (\Exception $e) {
                    Log::warning('Falha na indexação Scout', [
                        'diario_id' => $diario->id,
                        'erro' => $e->getMessage(),
                    ]);
                    // Não falha o processo por causa da indexação
                }
            }

            $resultado['sucesso'] = true;

            $endTime = microtime(true);
            $resultado['tempo_total_ms'] = round(($endTime - $startTime) * 1000, 2);

            Log::info('Processamento completo do diário concluído', [
                'diario_id' => $diario->id,
                'ocorrencias_encontradas' => $resultado['ocorrencias_encontradas'],
                'empresas_encontradas' => $resultado['empresas_encontradas'],
                'tempo_total_ms' => $resultado['tempo_total_ms'],
                'etapas' => $resultado['etapas_concluidas'],
            ]);

        } catch (\Exception $e) {
            $resultado['erros'][] = $e->getMessage();
            
            Log::error('Erro no processamento completo do diário', [
                'diario_id' => $diario->id,
                'erro' => $e->getMessage(),
                'etapas_concluidas' => $resultado['etapas_concluidas'],
            ]);
        }

        return $resultado;
    }

    public function reprocessarDiario(Diario $diario): array
    {
        Log::info('Iniciando reprocessamento do diário', [
            'diario_id' => $diario->id,
            'tentativa_atual' => $diario->tentativas,
        ]);

        // Limpar ocorrências anteriores
        $diario->ocorrencias()->delete();

        // Reprocessar PDF se necessário
        if ($diario->status !== 'concluido' || empty($diario->texto_extraido)) {
            if (!$this->pdfService->reprocessPdf($diario)) {
                return [
                    'sucesso' => false,
                    'erro' => 'Falha no reprocessamento do PDF: ' . $diario->erro_mensagem,
                ];
            }
        }

        // Processar completamente
        return $this->processarDiarioCompleto($diario->fresh());
    }

    public function processarDiariosPendentes(int $limite = null): array
    {
        $limite = $limite ?? SystemConfig::get('processing.batch_size', 10);
        
        $diariosPendentes = Diario::pendentes()
            ->orderBy('created_at', 'asc')
            ->limit($limite)
            ->get();

        if ($diariosPendentes->isEmpty()) {
            return [
                'total_processados' => 0,
                'sucessos' => 0,
                'erros' => 0,
                'detalhes' => [],
            ];
        }

        Log::info('Processando lote de diários pendentes', [
            'total_diarios' => $diariosPendentes->count(),
            'limite' => $limite,
        ]);

        $resultado = [
            'total_processados' => 0,
            'sucessos' => 0,
            'erros' => 0,
            'detalhes' => [],
        ];

        foreach ($diariosPendentes as $diario) {
            $resultadoProcessamento = $this->processarDiarioCompleto($diario);
            
            $resultado['total_processados']++;
            
            if ($resultadoProcessamento['sucesso']) {
                $resultado['sucessos']++;
            } else {
                $resultado['erros']++;
            }

            $resultado['detalhes'][] = [
                'diario_id' => $diario->id,
                'arquivo' => $diario->nome_arquivo,
                'sucesso' => $resultadoProcessamento['sucesso'],
                'ocorrencias' => $resultadoProcessamento['ocorrencias_encontradas'],
                'tempo_ms' => $resultadoProcessamento['tempo_total_ms'],
                'erros' => $resultadoProcessamento['erros'],
            ];

            // Pequena pausa entre processamentos para não sobrecarregar
            usleep(100000); // 100ms
        }

        Log::info('Lote de diários processado', [
            'total' => $resultado['total_processados'],
            'sucessos' => $resultado['sucessos'],
            'erros' => $resultado['erros'],
        ]);

        return $resultado;
    }

    public function getDashboardMetrics(): array
    {
        $stats = $this->pdfService->getProcessingStats();
        
        // Adicionar métricas de ocorrências
        $ocorrenciasHoje = \App\Models\Ocorrencia::whereDate('created_at', today())->count();
        $empresasComOcorrenciaHoje = \App\Models\Ocorrencia::whereDate('created_at', today())
            ->distinct('empresa_id')
            ->count();

        return array_merge($stats, [
            'ocorrencias_hoje' => $ocorrenciasHoje,
            'empresas_encontradas_hoje' => $empresasComOcorrenciaHoje,
            'tempo_medio_processamento' => $this->getTempoMedioProcessamento(),
        ]);
    }

    private function getTempoMedioProcessamento(): float
    {
        // Calcular baseado nos logs ou implementar tracking de tempo
        return 0.0; // TODO: Implementar tracking de tempo de processamento
    }

    private function shouldIndexInScout(): bool
    {
        return SystemConfig::get('search.scout_enabled', true);
    }

    public function verificarIntegridadeArquivos(): array
    {
        $diarios = Diario::whereNotNull('hash_sha256')->get();
        $problemas = [];

        foreach ($diarios as $diario) {
            $caminhoCompleto = storage_path('app/' . $diario->caminho_arquivo);
            
            if (!file_exists($caminhoCompleto)) {
                $problemas[] = [
                    'diario_id' => $diario->id,
                    'problema' => 'arquivo_nao_encontrado',
                    'caminho' => $diario->caminho_arquivo,
                ];
                continue;
            }

            $hashAtual = hash_file('sha256', $caminhoCompleto);
            if ($hashAtual !== $diario->hash_sha256) {
                $problemas[] = [
                    'diario_id' => $diario->id,
                    'problema' => 'hash_nao_confere',
                    'hash_esperado' => $diario->hash_sha256,
                    'hash_atual' => $hashAtual,
                ];
            }
        }

        return [
            'total_verificados' => $diarios->count(),
            'problemas_encontrados' => count($problemas),
            'detalhes' => $problemas,
        ];
    }
}