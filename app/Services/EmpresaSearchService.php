<?php

namespace App\Services;

use App\Models\Diario;
use App\Models\Empresa;
use App\Models\Ocorrencia;
use App\Models\SystemConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EmpresaSearchService
{
    private array $scoreConfig;

    public function __construct()
    {
        $this->scoreConfig = [
            'cnpj' => SystemConfig::get('search.cnpj_score', 1.0),
            'nome' => SystemConfig::get('search.nome_score', 0.9),
            'termo' => SystemConfig::get('search.termo_score', 0.85),
        ];
    }

    public function buscarEmpresasNoTexto(Diario $diario): array
    {
        $startTime = microtime(true);
        
        if (empty($diario->texto_extraido)) {
            throw new \Exception('Diário não possui texto extraído.');
        }

        // Buscar empresas ativas
        $empresas = Empresa::where('ativo', true)->get();
        
        if ($empresas->isEmpty()) {
            Log::warning('Nenhuma empresa ativa encontrada para busca', ['diario_id' => $diario->id]);
            return [];
        }

        $ocorrenciasEncontradas = [];
        $textoNormalizado = $this->normalizeTextForSearch($diario->texto_extraido);

        Log::info('Iniciando busca de empresas', [
            'diario_id' => $diario->id,
            'total_empresas' => $empresas->count(),
            'tamanho_texto' => strlen($diario->texto_extraido),
        ]);

        foreach ($empresas as $empresa) {
            $matches = $this->buscarEmpresaNoTexto($empresa, $textoNormalizado, $diario->texto_extraido);
            
            foreach ($matches as $match) {
                // Verificar se atende o score mínimo
                if ($match['score'] >= $empresa->score_minimo) {
                    $ocorrenciasEncontradas[] = $this->criarOcorrencia($diario, $empresa, $match);
                }
            }
        }

        $endTime = microtime(true);
        $tempoProcessamento = round(($endTime - $startTime) * 1000, 2);

        Log::info('Busca de empresas concluída', [
            'diario_id' => $diario->id,
            'ocorrencias_encontradas' => count($ocorrenciasEncontradas),
            'tempo_ms' => $tempoProcessamento,
        ]);

        return $ocorrenciasEncontradas;
    }

    private function buscarEmpresaNoTexto(Empresa $empresa, string $textoNormalizado, string $textoOriginal): array
    {
        $matches = [];

        // 1. Busca por CNPJ (score: 100%)
        if ($empresa->cnpj) {
            $cnpjMatches = $this->buscarCNPJ($empresa->cnpj, $textoNormalizado, $textoOriginal);
            $matches = array_merge($matches, $cnpjMatches);
        }

        // 2. Busca por nome da empresa (score: 90%)
        if ($empresa->nome) {
            $nomeMatches = $this->buscarNome($empresa->nome, $textoNormalizado, $textoOriginal);
            $matches = array_merge($matches, $nomeMatches);
        }

        // 3. Busca por variantes auto-geradas
        if ($empresa->variantes_busca) {
            foreach ($empresa->variantes_busca as $variante) {
                if (empty($variante) || strlen($variante) < 3) continue;
                
                $varianteMatches = $this->buscarVariante($variante, $textoNormalizado, $textoOriginal);
                $matches = array_merge($matches, $varianteMatches);
            }
        }

        // 4. Busca por termos personalizados (score: 85%)
        if ($empresa->termos_personalizados) {
            foreach ($empresa->termos_personalizados as $termo) {
                if (empty($termo) || strlen($termo) < 3) continue;
                
                $termoMatches = $this->buscarTermoPersonalizado($termo, $textoNormalizado, $textoOriginal);
                $matches = array_merge($matches, $termoMatches);
            }
        }

        // Remover duplicatas baseado na posição
        return $this->removeDuplicateMatches($matches);
    }

    private function buscarCNPJ(string $cnpj, string $textoNormalizado, string $textoOriginal): array
    {
        $matches = [];
        
        // Buscar CNPJ formatado e sem formatação
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);
        $cnpjFormatado = $cnpj;

        $padroes = [$cnpjLimpo, $cnpjFormatado];

        foreach ($padroes as $padrao) {
            if (empty($padrao)) continue;

            $posicoes = $this->findAllPositions($padrao, $textoOriginal);
            
            foreach ($posicoes as $posicao) {
                $matches[] = [
                    'tipo_match' => 'cnpj',
                    'termo_encontrado' => $padrao,
                    'posicao' => $posicao,
                    'score' => $this->scoreConfig['cnpj'],
                    'contexto' => $this->extrairContexto($textoOriginal, $posicao, strlen($padrao)),
                ];
            }
        }

        return $matches;
    }

    private function buscarNome(string $nome, string $textoNormalizado, string $textoOriginal): array
    {
        $matches = [];
        
        // Busca exata do nome
        $posicoes = $this->findAllPositions(strtoupper($nome), strtoupper($textoOriginal));
        
        foreach ($posicoes as $posicao) {
            $matches[] = [
                'tipo_match' => 'nome',
                'termo_encontrado' => $nome,
                'posicao' => $posicao,
                'score' => $this->scoreConfig['nome'],
                'contexto' => $this->extrairContexto($textoOriginal, $posicao, strlen($nome)),
            ];
        }

        // Busca fuzzy com similar_text para nomes similares
        $palavrasNome = explode(' ', $nome);
        if (count($palavrasNome) > 1) {
            $matches = array_merge($matches, $this->buscarNomeFuzzy($nome, $textoOriginal));
        }

        return $matches;
    }

    private function buscarNomeFuzzy(string $nome, string $textoOriginal): array
    {
        $matches = [];
        $minSimilarity = 80; // 80% de similaridade mínima
        
        // Dividir texto em chunks para análise
        $chunks = $this->getTextChunks($textoOriginal, strlen($nome) * 2);
        
        foreach ($chunks as $chunk) {
            $similarity = 0;
            similar_text(strtoupper($nome), strtoupper($chunk['text']), $similarity);
            
            if ($similarity >= $minSimilarity) {
                $score = $this->scoreConfig['nome'] * ($similarity / 100);
                
                $matches[] = [
                    'tipo_match' => 'nome',
                    'termo_encontrado' => $nome,
                    'posicao' => $chunk['position'],
                    'score' => $score,
                    'contexto' => $this->extrairContexto($textoOriginal, $chunk['position'], strlen($chunk['text'])),
                ];
            }
        }

        return $matches;
    }

    private function buscarVariante(string $variante, string $textoNormalizado, string $textoOriginal): array
    {
        $matches = [];
        
        $posicoes = $this->findAllPositions(strtoupper($variante), strtoupper($textoOriginal));
        
        foreach ($posicoes as $posicao) {
            $matches[] = [
                'tipo_match' => 'nome',
                'termo_encontrado' => $variante,
                'posicao' => $posicao,
                'score' => $this->scoreConfig['nome'] * 0.95, // Pequena redução para variantes
                'contexto' => $this->extrairContexto($textoOriginal, $posicao, strlen($variante)),
            ];
        }

        return $matches;
    }

    private function buscarTermoPersonalizado(string $termo, string $textoNormalizado, string $textoOriginal): array
    {
        $matches = [];
        
        $posicoes = $this->findAllPositions(strtoupper($termo), strtoupper($textoOriginal));
        
        foreach ($posicoes as $posicao) {
            $matches[] = [
                'tipo_match' => 'termo_personalizado',
                'termo_encontrado' => $termo,
                'posicao' => $posicao,
                'score' => $this->scoreConfig['termo'],
                'contexto' => $this->extrairContexto($textoOriginal, $posicao, strlen($termo)),
            ];
        }

        return $matches;
    }

    private function findAllPositions(string $needle, string $haystack): array
    {
        $positions = [];
        $offset = 0;
        
        while (($pos = strpos($haystack, $needle, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + 1;
        }
        
        return $positions;
    }

    private function extrairContexto(string $texto, int $posicao, int $tamanhoTermo): array
    {
        $contextChars = SystemConfig::get('search.context_chars', 300);
        
        $inicio = max(0, $posicao - $contextChars);
        $fim = min(strlen($texto), $posicao + $tamanhoTermo + $contextChars);
        
        $contextoCompleto = substr($texto, $inicio, $fim - $inicio);
        
        // Tentar encontrar início e fim de parágrafo
        $paragrafoInicio = $this->findParagraphStart($texto, $posicao);
        $paragrafoFim = $this->findParagraphEnd($texto, $posicao + $tamanhoTermo);
        
        $contextoParágrafo = substr($texto, $paragrafoInicio, $paragrafoFim - $paragrafoInicio);
        
        return [
            'contexto_completo' => trim($contextoCompleto),
            'contexto_paragrafo' => trim($contextoParágrafo),
            'posicao_inicio' => $posicao,
            'posicao_fim' => $posicao + $tamanhoTermo,
        ];
    }

    private function findParagraphStart(string $texto, int $posicao): int
    {
        $inicio = $posicao;
        
        // Voltar até encontrar quebra de linha dupla ou início do texto
        while ($inicio > 0) {
            if (substr($texto, $inicio - 2, 2) === "\n\n") {
                break;
            }
            $inicio--;
        }
        
        return max(0, $inicio);
    }

    private function findParagraphEnd(string $texto, int $posicao): int
    {
        $fim = $posicao;
        $tamanho = strlen($texto);
        
        // Avançar até encontrar quebra de linha dupla ou fim do texto
        while ($fim < $tamanho - 2) {
            if (substr($texto, $fim, 2) === "\n\n") {
                break;
            }
            $fim++;
        }
        
        return min($tamanho, $fim);
    }

    private function getTextChunks(string $texto, int $chunkSize): array
    {
        $chunks = [];
        $tamanho = strlen($texto);
        
        for ($i = 0; $i < $tamanho; $i += $chunkSize) {
            $chunks[] = [
                'text' => substr($texto, $i, $chunkSize),
                'position' => $i,
            ];
        }
        
        return $chunks;
    }

    private function removeDuplicateMatches(array $matches): array
    {
        $unique = [];
        $positions = [];
        
        foreach ($matches as $match) {
            $key = $match['posicao'] . '_' . $match['tipo_match'];
            
            if (!isset($positions[$key]) || $match['score'] > $positions[$key]['score']) {
                $positions[$key] = $match;
            }
        }
        
        return array_values($positions);
    }

    private function criarOcorrencia(Diario $diario, Empresa $empresa, array $match): Ocorrencia
    {
        $contexto = $match['contexto'];
        
        return Ocorrencia::create([
            'diario_id' => $diario->id,
            'empresa_id' => $empresa->id,
            'tipo_match' => $match['tipo_match'],
            'termo_encontrado' => $match['termo_encontrado'],
            'contexto_completo' => $contexto['contexto_completo'],
            'posicao_inicio' => $contexto['posicao_inicio'],
            'posicao_fim' => $contexto['posicao_fim'],
            'score_confianca' => $match['score'],
            'pagina' => $this->estimarPagina($diario->texto_extraido, $match['posicao']),
        ]);
    }

    private function estimarPagina(string $texto, int $posicao): ?int
    {
        // Estimar página baseado na posição no texto
        $textAtéPosicao = substr($texto, 0, $posicao);
        $caracteresPorPagina = 3000; // Estimativa
        
        return max(1, ceil(strlen($textAtéPosicao) / $caracteresPorPagina));
    }

    private function normalizeTextForSearch(string $texto): string
    {
        // Normalizar para busca (remover acentos, converter para uppercase)
        $texto = strtoupper($texto);
        
        $acentos = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ];
        
        return strtr($texto, $acentos);
    }

    public function getSearchStats(Diario $diario): array
    {
        $ocorrencias = Ocorrencia::where('diario_id', $diario->id)->get();
        
        return [
            'total_ocorrencias' => $ocorrencias->count(),
            'empresas_encontradas' => $ocorrencias->pluck('empresa_id')->unique()->count(),
            'tipos_match' => $ocorrencias->groupBy('tipo_match')->map->count(),
            'score_medio' => $ocorrencias->avg('score_confianca'),
            'score_maximo' => $ocorrencias->max('score_confianca'),
        ];
    }
}