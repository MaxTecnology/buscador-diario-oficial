<?php

namespace App\Services;

use App\Models\Diario;
use App\Models\Empresa;
use App\Models\Ocorrencia;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;
use App\Services\LoggingService;

class PdfProcessorService
{
    protected $parser;
    protected $loggingService;

    public function __construct()
    {
        $this->parser = new Parser();
        $this->loggingService = new LoggingService();
    }

    public function processarPdf(Diario $diario): array
    {
        $startTime = microtime(true);
        
        try {
            // Aumentar limite de tempo e memória para PDFs grandes
            set_time_limit(300); // 5 minutos
            ini_set('memory_limit', '512M');
            
            $this->loggingService->logProcessamentoPdf(
                LoggingService::NIVEL_INFO,
                "Iniciando processamento de PDF",
                [
                    'diario_id' => $diario->id,
                    'nome_arquivo' => $diario->nome_arquivo,
                    'tamanho_arquivo' => $diario->tamanho_arquivo,
                ]
            );
            
            // Arquivo está no disco público
            $caminhoArquivo = Storage::disk('public')->path($diario->caminho_arquivo);
            
            if (!file_exists($caminhoArquivo)) {
                throw new \Exception("Arquivo PDF não encontrado: {$caminhoArquivo}");
            }

            // Verificar tamanho do arquivo
            $tamanhoMB = filesize($caminhoArquivo) / 1024 / 1024;
            
            $this->loggingService->logProcessamentoPdf(
                LoggingService::NIVEL_INFO,
                "PDF carregado para processamento",
                [
                    'diario_id' => $diario->id,
                    'tamanho_mb' => round($tamanhoMB, 2),
                    'caminho' => $caminhoArquivo
                ]
            );

            $pdf = $this->parser->parseFile($caminhoArquivo);
            $textoCompleto = $pdf->getText();
            
            // Contar número de páginas do PDF
            $totalPaginas = $this->contarPaginasPdf($pdf);
            Log::info("PDF {$diario->nome_arquivo}: {$totalPaginas} página(s) detectadas");
            
            // Limpar caracteres UTF-8 inválidos de forma mais robusta
            $textoCompleto = mb_convert_encoding($textoCompleto, 'UTF-8', 'UTF-8');
            $textoCompleto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $textoCompleto);
            
            // Remover caracteres de controle adicionais e sequências problemáticas
            $textoCompleto = preg_replace('/[\x{0080}-\x{009F}]/u', '', $textoCompleto);
            $textoCompleto = preg_replace('/[\x{FEFF}\x{FFFF}\x{FFFE}]/u', '', $textoCompleto);
            
            // Garantir que seja UTF-8 válido
            if (!mb_check_encoding($textoCompleto, 'UTF-8')) {
                $textoCompleto = mb_convert_encoding($textoCompleto, 'UTF-8', mb_detect_encoding($textoCompleto, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true));
            }
            
            // Normalizar espaços em branco
            $textoCompleto = preg_replace('/\s+/', ' ', $textoCompleto);
            $textoCompleto = trim($textoCompleto);
            
            // Salvar texto completo em arquivo separado para melhor performance
            $nomeArquivoTexto = 'texto_' . $diario->id . '_' . time() . '.txt';
            $caminhoTextoCompleto = 'diarios/textos/' . $nomeArquivoTexto;
            Storage::disk('public')->put($caminhoTextoCompleto, $textoCompleto);
            
            // Para o banco, salvar apenas um preview
            $textoPreview = mb_substr($textoCompleto, 0, 2000);
            if (strlen($textoCompleto) > 2000) {
                $textoPreview .= "\n\n... [texto completo salvo em arquivo - " . number_format(strlen($textoCompleto)) . " caracteres]";
            }

            $updateData = [
                'texto_extraido' => $textoPreview,
                'tamanho_arquivo' => filesize($caminhoArquivo),
                'total_paginas' => $totalPaginas,
                'hash_sha256' => hash_file('sha256', $caminhoArquivo),
                'status' => 'concluido',
                'processado_em' => now()
            ];
            
            // Verificar se a coluna caminho_texto_completo existe
            if (\Illuminate\Support\Facades\Schema::hasColumn('diarios', 'caminho_texto_completo')) {
                $updateData['caminho_texto_completo'] = $caminhoTextoCompleto;
            }
            
            // Verificar se as colunas novas existem
            if (\Illuminate\Support\Facades\Schema::hasColumn('diarios', 'texto_completo')) {
                $updateData['texto_completo'] = $textoCompleto;
                $updateData['status_processamento'] = 'processado';
            }
            
            $diario->update($updateData);

            // Usar o texto completo para buscar ocorrências (não o preview)
            $ocorrenciasEncontradas = $this->buscarOcorrencias($diario, $textoCompleto);

            // Enviar notificações automáticas se configurado
            if (\App\Models\ConfiguracaoSistema::get('notificacao_automatica_apos_processamento', true)) {
                $notificacaoService = app(\App\Services\NotificacaoService::class);
                foreach ($ocorrenciasEncontradas as $ocorrencia) {
                    $notificacaoService->notificarOcorrencia($ocorrencia);
                }
            }

            return [
                'sucesso' => true,
                'texto_extraido' => strlen($textoCompleto),
                'ocorrencias_encontradas' => count($ocorrenciasEncontradas),
                'ocorrencias' => $ocorrenciasEncontradas
            ];

        } catch (\Exception $e) {
            Log::error("Erro ao processar PDF: " . $e->getMessage(), [
                'diario_id' => $diario->id,
                'arquivo' => $diario->caminho_arquivo
            ]);

            $errorData = [
                'status' => 'erro',
                'erro_mensagem' => $e->getMessage()
            ];
            
            // Verificar se as colunas novas existem
            if (\Illuminate\Support\Facades\Schema::hasColumn('diarios', 'status_processamento')) {
                $errorData['status_processamento'] = 'erro';
                $errorData['erro_processamento'] = $e->getMessage();
            }
            
            $diario->update($errorData);

            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }

    protected function buscarOcorrencias(Diario $diario, string $texto): array
    {
        $ocorrenciasEncontradas = [];
        
        // Buscar apenas empresas ativas
        $empresas = Empresa::where('ativo', true)->get();
            
        Log::info("Iniciando busca de ocorrências", [
            'diario_id' => $diario->id,
            'empresas_ativas' => $empresas->count(),
            'tamanho_texto' => strlen($texto)
        ]);
        
        // Normalizar texto como no seu Python (mais agressivo)
        $textoNormalizado = $this->normalizarTexto($texto);

        foreach ($empresas as $empresa) {
            $melhorMatch = $this->buscarMelhorMatchEmpresa($empresa, $textoNormalizado, $texto);
            
            if ($melhorMatch && $melhorMatch['score'] >= $empresa->score_minimo) {
                
                Log::info("Empresa {$empresa->nome}: Detectada com {$melhorMatch['tipo']} - Score: {$melhorMatch['score']}");
                
                // Limpar dados antes de salvar
                $termoLimpo = mb_substr($melhorMatch['termo'], 0, 255);
                $contextoLimpo = mb_substr($melhorMatch['contexto'], 0, 1000);
                

                $ocorrencia = Ocorrencia::create([
                    'diario_id' => $diario->id,
                    'empresa_id' => $empresa->id,
                    'cnpj' => $empresa->cnpj,
                    'termo_encontrado' => $termoLimpo,
                    'contexto_completo' => $contextoLimpo,
                    'score_confianca' => $melhorMatch['score'],
                    'posicao_inicio' => $melhorMatch['posicao'],
                    'posicao_fim' => $melhorMatch['posicao'] + strlen($melhorMatch['termo']),
                    'tipo_match' => $melhorMatch['tipo'],
                ]);

                $ocorrenciasEncontradas[] = $ocorrencia;
                
                Log::info("Ocorrência criada", [
                    'empresa_id' => $empresa->id,
                    'empresa_nome' => $empresa->nome,
                    'score' => $melhorMatch['score'],
                    'tipo' => $melhorMatch['tipo']
                ]);
            }
        }

        Log::info("Busca finalizada", [
            'total_ocorrencias_encontradas' => count($ocorrenciasEncontradas)
        ]);

        return $ocorrenciasEncontradas;
    }

    protected function buscarTermosEmpresa(Empresa $empresa, string $textoLower, string $textoOriginal): array
    {
        $termosEncontrados = [];
        $variantesBusca = $empresa->variantes_busca ?? [];
        
        // Limitar a 10 variantes para evitar timeout
        $variantesBusca = array_slice($variantesBusca, 0, 10);
        
        foreach ($variantesBusca as $variante) {
            if (empty($variante) || strlen($variante) < 3) {
                continue; // Pular termos muito curtos
            }
            
            $varianteLower = mb_strtolower($variante);
            $posicoes = $this->encontrarTermoNoTexto($varianteLower, $textoLower);
            
            // Limitar a 5 ocorrências por termo para evitar spam
            $posicoes = array_slice($posicoes, 0, 5);
            
            foreach ($posicoes as $posicao) {
                $contexto = $this->extrairContexto($textoOriginal, $posicao, strlen($variante));
                
                $termosEncontrados[] = [
                    'termo' => $variante,
                    'posicao' => $posicao,
                    'contexto' => $contexto
                ];
            }
        }

        return $termosEncontrados;
    }

    protected function encontrarTermoNoTexto(string $termo, string $texto): array
    {
        $posicoes = [];
        $offset = 0;
        $maxPosicoes = 10; // Limitar para evitar timeout
        
        // Usar mb_strpos para texto já convertido em lowercase
        while (($pos = mb_strpos($texto, $termo, $offset)) !== false && count($posicoes) < $maxPosicoes) {
            $posicoes[] = $pos;
            $offset = $pos + mb_strlen($termo);
        }
        
        return $posicoes;
    }

    protected function extrairContexto(string $texto, int $posicao, int $tamanhoTermo, int $contextoTamanho = 200): string
    {
        $inicio = max(0, $posicao - $contextoTamanho);
        $fim = min(strlen($texto), $posicao + $tamanhoTermo + $contextoTamanho);
        
        $contexto = substr($texto, $inicio, $fim - $inicio);
        
        // Limpar o contexto de caracteres problemáticos
        $contexto = mb_convert_encoding($contexto, 'UTF-8', 'UTF-8');
        $contexto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $contexto);
        $contexto = preg_replace('/[\x{0080}-\x{009F}]/u', '', $contexto);
        $contexto = preg_replace('/\s+/', ' ', $contexto);
        $contexto = trim($contexto);
        
        return $contexto;
    }

    /**
     * Normalizar texto de forma mais agressiva (baseado no seu Python)
     */
    protected function normalizarTexto(string $texto): string
    {
        // Converter para maiúsculo primeiro
        $texto = mb_strtoupper($texto);
        
        // Mapeamento manual de acentos (mais confiável que iconv)
        $acentos = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N'
        ];
        
        $texto = strtr($texto, $acentos);
        
        // Fallback com iconv para casos não cobertos
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        
        // Remover pontuação como no seu código: [./-]
        $texto = preg_replace('/[.\\/\-]/', '', $texto);
        
        // Remover quebras de linha
        $texto = preg_replace('/\n/', '', $texto);
        
        // Normalizar espaços
        $texto = preg_replace('/\s+/', ' ', $texto);
        
        return trim($texto);
    }

    /**
     * Buscar melhor match para empresa (baseado na sua lógica de prioridade)
     */
    protected function buscarMelhorMatchEmpresa(Empresa $empresa, string $textoNormalizado, string $textoOriginal): ?array
    {
        $melhorMatch = null;
        $melhorScore = 0;

        // 1. CNPJ (prioridade máxima - como no seu Python)
        if ($empresa->cnpj) {
            $cnpjLimpo = preg_replace('/[^0-9]/', '', $empresa->cnpj);
            if (strlen($cnpjLimpo) === 14) {
                // Buscar CNPJ completo (14 dígitos)
                if (strpos($textoNormalizado, $cnpjLimpo) !== false) {
                    $posicao = strpos($textoNormalizado, $cnpjLimpo);
                    $contexto = $this->extrairContexto($textoOriginal, $posicao, strlen($cnpjLimpo));
                    
                    $melhorMatch = [
                        'termo' => $cnpjLimpo,
                        'tipo' => 'cnpj',
                        'score' => 0.95, // Score alto para CNPJ
                        'posicao' => $posicao,
                        'contexto' => $contexto
                    ];
                    $melhorScore = 0.95;
                }
                // Buscar CNPJ sem zeros à esquerda (como 5251761000130)
                else {
                    $cnpjSemZeros = ltrim($cnpjLimpo, '0');
                    if (strlen($cnpjSemZeros) >= 10 && strpos($textoNormalizado, $cnpjSemZeros) !== false) {
                        $posicao = strpos($textoNormalizado, $cnpjSemZeros);
                        $contexto = $this->extrairContexto($textoOriginal, $posicao, strlen($cnpjSemZeros));
                        
                        $melhorMatch = [
                            'termo' => $cnpjSemZeros,
                            'tipo' => 'cnpj',
                            'score' => 0.90, // Score um pouco menor para CNPJ sem zeros
                            'posicao' => $posicao,
                            'contexto' => $contexto
                        ];
                        $melhorScore = 0.90;
                    }
                }
            }
        }

        // 2. Inscrição Estadual (segunda prioridade - como no seu Python)
        if ($empresa->inscricao_estadual && strlen($empresa->inscricao_estadual) > 2) {
            $inscricaoOriginal = trim($empresa->inscricao_estadual);
            $inscricaoLimpa = preg_replace('/[^0-9A-Z]/', '', $this->normalizarTexto($inscricaoOriginal));
            
            // Buscar inscrição completa (com todos os dígitos)
            if (strpos($textoNormalizado, $inscricaoLimpa) !== false) {
                $posicao = strpos($textoNormalizado, $inscricaoLimpa);
                $contexto = $this->extrairContexto($textoOriginal, $posicao, strlen($inscricaoLimpa));
                
                $scoreInscricao = 0.85;
                if ($scoreInscricao > $melhorScore) {
                    $melhorMatch = [
                        'termo' => $inscricaoLimpa,
                        'tipo' => 'inscricao_estadual',
                        'score' => $scoreInscricao,
                        'posicao' => $posicao,
                        'contexto' => $contexto
                    ];
                    $melhorScore = $scoreInscricao;
                }
            }
            // Buscar inscrição sem o dígito verificador e outras variações
            else if (strlen($inscricaoLimpa) > 3) {
                $variacoesInscricao = [];
                
                // 1. Remover último dígito (dígito verificador): 24843807-7 → 24843807
                if (strlen($inscricaoLimpa) > 6) {
                    $variacoesInscricao[] = substr($inscricaoLimpa, 0, -1);
                }
                
                // 2. Remover dois últimos dígitos (alguns formatos): 24843807-77 → 24843807
                if (strlen($inscricaoLimpa) > 8) {
                    $variacoesInscricao[] = substr($inscricaoLimpa, 0, -2);
                }
                
                // 3. Remover zeros à esquerda da inscrição completa
                $inscricaoSemZeros = ltrim($inscricaoLimpa, '0');
                if ($inscricaoSemZeros !== $inscricaoLimpa && strlen($inscricaoSemZeros) >= 6) {
                    $variacoesInscricao[] = $inscricaoSemZeros;
                }
                
                // Testar cada variação
                foreach ($variacoesInscricao as $variacao) {
                    if (strlen($variacao) >= 6 && strpos($textoNormalizado, $variacao) !== false) {
                        $posicao = strpos($textoNormalizado, $variacao);
                        $contexto = $this->extrairContexto($textoOriginal, $posicao, strlen($variacao));
                        
                        $scoreInscricao = 0.80; // Score um pouco menor para variações
                        if ($scoreInscricao > $melhorScore) {
                            $melhorMatch = [
                                'termo' => $variacao,
                                'tipo' => 'inscricao_estadual',
                                'score' => $scoreInscricao,
                                'posicao' => $posicao,
                                'contexto' => $contexto
                            ];
                            $melhorScore = $scoreInscricao;
                            break; // Parar na primeira variação encontrada
                        }
                    }
                }
            }
        }

        // 3. Razão Social (terceira prioridade - como no seu Python)
        $nomeNormalizado = $this->normalizarTexto($empresa->nome);
        
        // Log para debug
        Log::debug("Buscando empresa: {$empresa->nome}", [
            'nome_normalizado' => $nomeNormalizado,
            'cnpj' => $empresa->cnpj
        ]);
        
        if (strpos($textoNormalizado, $nomeNormalizado) !== false) {
            $posicao = strpos($textoNormalizado, $nomeNormalizado);
            $contexto = $this->extrairContexto($textoOriginal, $posicao, strlen($nomeNormalizado));
            
            $scoreNome = $this->calcularScoreNome($nomeNormalizado, $textoNormalizado, $empresa);
            
            // Bônus se encontrar CNPJ próximo no contexto (alta confiança)
            if ($empresa->cnpj && $this->encontrarCnpjProximo($empresa->cnpj, $contexto)) {
                $scoreNome += 0.15;
                Log::debug("Bônus CNPJ próximo aplicado para: {$empresa->nome}");
            }
            
            if ($scoreNome > $melhorScore) {
                $melhorMatch = [
                    'termo' => $empresa->nome,
                    'tipo' => 'nome',
                    'score' => $scoreNome,
                    'posicao' => $posicao,
                    'contexto' => $contexto
                ];
                $melhorScore = $scoreNome;
                
                Log::debug("Match encontrado: {$empresa->nome} - Score: {$scoreNome}");
            }
        } else {
            Log::debug("Nome não encontrado no texto: {$empresa->nome}");
        }

        // 4. Variantes/termos personalizados (menor prioridade)
        if ($empresa->variantes_busca) {
            foreach ($empresa->variantes_busca as $variante) {
                if (strlen($variante) < 3) continue;
                
                $varianteNormalizada = $this->normalizarTexto($variante);
                if (strpos($textoNormalizado, $varianteNormalizada) !== false) {
                    $posicao = strpos($textoNormalizado, $varianteNormalizada);
                    $contexto = $this->extrairContexto($textoOriginal, $posicao, strlen($varianteNormalizada));
                    
                    $scoreVariante = $this->calcularScoreNome($varianteNormalizada, $textoNormalizado, $empresa) * 0.8; // Reduzir um pouco
                    if ($scoreVariante > $melhorScore) {
                        $melhorMatch = [
                            'termo' => $variante,
                            'tipo' => 'variante',
                            'score' => $scoreVariante,
                            'posicao' => $posicao,
                            'contexto' => $contexto
                        ];
                        $melhorScore = $scoreVariante;
                    }
                }
            }
        }

        return $melhorMatch;
    }

    /**
     * Calcular score para nomes (com base no tamanho e contexto)
     */
    protected function calcularScoreNome(string $nome, string $texto, Empresa $empresa): float
    {
        $score = 0.7; // Score base para nomes aumentado
        
        // Bônus significativo para nomes longos e específicos como MUNDIAL INDUSTRIA E COMERCIO DE PRODUTOS DE HIGIENE LTDA
        $tamanho = strlen($nome);
        if ($tamanho > 40) $score += 0.2; // Nomes muito específicos
        elseif ($tamanho > 20) $score += 0.15;
        elseif ($tamanho > 10) $score += 0.05;
        
        // Bônus por prioridade da empresa
        switch ($empresa->prioridade) {
            case 'alta': $score += 0.1; break;
            case 'media': $score += 0.05; break;
        }
        
        // Bônus para match exato completo (sempre aplicado se chegou aqui)
        $score += 0.1;
        
        // Penalizar apenas se o termo aparece MUITAS vezes (mais rigoroso)
        $frequencia = substr_count($texto, $nome);
        if ($frequencia > 10) $score -= 0.15;
        elseif ($frequencia > 5) $score -= 0.1;
        elseif ($frequencia > 3) $score -= 0.05;
        
        return min(1.0, max(0.0, $score));
    }

    protected function calcularScoreConfianca(array $termo, Empresa $empresa, string $textoCompleto): float
    {
        $score = 0.5; // Score base
        
        // Aumenta score se o termo for exato (não parcial)
        if ($this->isTermoExato($termo['termo'], $termo['contexto'])) {
            $score += 0.2;
        }
        
        // Aumenta score se encontrar CNPJ próximo
        if ($empresa->cnpj && $this->encontrarCnpjProximo($empresa->cnpj, $termo['contexto'])) {
            $score += 0.25;
        }
        
        // Aumenta score se encontrar inscrição estadual próxima
        if ($empresa->inscricao_estadual && $this->encontrarInscricaoProxima($empresa->inscricao_estadual, $termo['contexto'])) {
            $score += 0.15;
        }
        
        // Bônus adicional se o termo é exatamente o nome da empresa
        if (mb_strtolower(trim($termo['termo'])) === mb_strtolower(trim($empresa->nome))) {
            $score += 0.15;
        }
        
        // Aumenta score baseado na prioridade da empresa
        switch ($empresa->prioridade) {
            case 'alta':
                $score += 0.1;
                break;
            case 'media':
                $score += 0.05;
                break;
        }
        
        // Diminui score se o termo for muito comum no texto
        $frequencia = substr_count(strtolower($textoCompleto), strtolower($termo['termo']));
        if ($frequencia > 10) {
            $score -= 0.1;
        }
        
        return min(1.0, max(0.0, $score));
    }

    protected function isTermoExato(string $termo, string $contexto): bool
    {
        // Verifica se o termo está cercado por delimitadores (espaços, pontuação)
        $pattern = '/\b' . preg_quote($termo, '/') . '\b/i';
        return preg_match($pattern, $contexto) > 0;
    }

    protected function encontrarCnpjProximo(string $cnpj, string $contexto): bool
    {
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);
        
        // Buscar CNPJ completo no contexto
        if (strpos($contexto, $cnpjLimpo) !== false) {
            return true;
        }
        
        // Buscar CNPJ sem zeros à esquerda
        $cnpjSemZeros = ltrim($cnpjLimpo, '0');
        if (strlen($cnpjSemZeros) >= 10 && strpos($contexto, $cnpjSemZeros) !== false) {
            return true;
        }
        
        // Buscar CNPJ formatado (xx.xxx.xxx/xxxx-xx)
        $cnpjFormatado = substr($cnpjLimpo, 0, 2) . '.' . substr($cnpjLimpo, 2, 3) . '.' . 
                        substr($cnpjLimpo, 5, 3) . '/' . substr($cnpjLimpo, 8, 4) . '-' . 
                        substr($cnpjLimpo, 12, 2);
        if (strpos($contexto, $cnpjFormatado) !== false) {
            return true;
        }
        
        return false;
    }

    protected function encontrarInscricaoProxima(string $inscricao, string $contexto): bool
    {
        $inscricaoLimpa = preg_replace('/[^0-9]/', '', $inscricao);
        $inscricaoLimpaContexto = preg_replace('/[^0-9]/', '', $contexto);
        
        return strpos($inscricaoLimpaContexto, $inscricaoLimpa) !== false;
    }

    protected function determinarTipoMatch(string $termo, Empresa $empresa): string
    {
        // Verificar se é CNPJ
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $empresa->cnpj ?? '');
        $termoLimpo = preg_replace('/[^0-9]/', '', $termo);
        if ($cnpjLimpo && $termoLimpo === $cnpjLimpo) {
            return 'cnpj';
        }

        // Verificar se é nome da empresa
        if (stripos($empresa->nome, $termo) !== false || stripos($termo, $empresa->nome) !== false) {
            return 'nome';
        }

        // Por padrão, considerar termo personalizado
        return 'termo_personalizado';
    }
    
    /**
     * Contar número de páginas do PDF
     */
    protected function contarPaginasPdf($pdf): int
    {
        try {
            // Método 1: Tentar usar getPages() se disponível
            if (method_exists($pdf, 'getPages')) {
                $pages = $pdf->getPages();
                return count($pages);
            }
            
            // Método 2: Procurar pela estrutura do PDF diretamente
            $pdfDetails = $pdf->getDetails();
            if (isset($pdfDetails['Pages'])) {
                return (int) $pdfDetails['Pages'];
            }
            
            // Método 3: Analisar o conteúdo bruto do PDF
            $catalog = $pdf->getCatalog();
            if ($catalog && method_exists($catalog, 'getPages')) {
                $pageTree = $catalog->getPages();
                if ($pageTree && method_exists($pageTree, 'getKids')) {
                    $kids = $pageTree->getKids();
                    return count($kids);
                }
            }
            
            Log::warning("Não foi possível determinar o número de páginas do PDF");
            return 1; // Assumir 1 página se não conseguir determinar
            
        } catch (\Exception $e) {
            Log::warning("Erro ao contar páginas do PDF: " . $e->getMessage());
            return 1; // Fallback para 1 página
        }
    }
}