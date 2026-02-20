<?php

namespace App\Console\Commands;

use App\Models\Diario;
use Illuminate\Console\Command;
use Smalot\PdfParser\Parser;

class AtualizarPaginasDiarios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'diarios:atualizar-paginas {--force : Atualizar mesmo diários que já têm páginas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atualiza o campo total_paginas dos diários processados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $parser = new Parser();
        
        $query = Diario::where('status', 'concluido');
        
        if (!$this->option('force')) {
            $query->whereNull('total_paginas');
        }
        
        $diarios = $query->get();
        
        if ($diarios->isEmpty()) {
            $this->info('Nenhum diário encontrado para atualizar.');
            return 0;
        }
        
        $this->info("Encontrados {$diarios->count()} diário(s) para atualizar.");
        
        $bar = $this->output->createProgressBar($diarios->count());
        $bar->start();
        
        $atualizados = 0;
        $erros = 0;
        
        foreach ($diarios as $diario) {
            try {
                $disk = \Illuminate\Support\Facades\Storage::disk(config('filesystems.diarios_disk', 'diarios'));
                $adapter = $disk->getAdapter();
                $tmpFile = null;
                if (method_exists($adapter, 'getPathPrefix')) {
                    $caminhoArquivo = $adapter->getPathPrefix() . $diario->caminho_arquivo;
                } else {
                    $tmpFile = tempnam(sys_get_temp_dir(), 'pdf_');
                    $stream = $disk->readStream($diario->caminho_arquivo);
                    if (!$stream) {
                        throw new \Exception("Não foi possível ler o PDF: {$diario->caminho_arquivo}");
                    }
                    $out = fopen($tmpFile, 'w+b');
                    stream_copy_to_stream($stream, $out);
                    fclose($stream);
                    fclose($out);
                    $caminhoArquivo = $tmpFile;
                }

                $pdf = $parser->parseFile($caminhoArquivo);
                $totalPaginas = $this->contarPaginasPdf($pdf);
                
                $diario->update(['total_paginas' => $totalPaginas]);
                
                $atualizados++;
                
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Erro ao processar {$diario->nome_arquivo}: " . $e->getMessage());
                $erros++;
            }
            
            if ($tmpFile && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("Concluído!");
        $this->info("Diários atualizados: {$atualizados}");
        if ($erros > 0) {
            $this->warn("Erros encontrados: {$erros}");
        }
        
        return 0;
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
            
            return 1; // Assumir 1 página se não conseguir determinar
            
        } catch (\Exception $e) {
            return 1; // Fallback para 1 página
        }
    }
}
