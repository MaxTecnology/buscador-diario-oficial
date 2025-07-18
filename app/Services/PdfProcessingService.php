<?php

namespace App\Services;

use App\Models\Diario;
use App\Models\SystemConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class PdfProcessingService
{
    public function processUploadedFile(UploadedFile $file, string $estado, string $datadiario, int $userId): Diario
    {
        // Validações básicas
        $this->validateFile($file);

        // Gerar hash SHA-256 para integridade
        $hash = hash_file('sha256', $file->getRealPath());

        // Verificar se já existe PDF com mesmo hash
        if (Diario::where('hash_sha256', $hash)->exists()) {
            throw new \Exception('Este arquivo já foi processado anteriormente.');
        }

        // Salvar arquivo no storage
        $filename = $this->generateFileName($file, $estado, $datadiario);
        $caminho = $file->storeAs('diarios/' . $estado, $filename, 'local');

        // Criar registro do diário
        $diario = Diario::create([
            'nome_arquivo' => $file->getClientOriginalName(),
            'estado' => $estado,
            'data_diario' => $datadiario,
            'hash_sha256' => $hash,
            'caminho_arquivo' => $caminho,
            'tamanho_arquivo' => $file->getSize(),
            'status' => 'pendente',
            'tentativas' => 0,
            'usuario_upload_id' => $userId,
        ]);

        Log::info('PDF uploaded', [
            'diario_id' => $diario->id,
            'arquivo' => $filename,
            'estado' => $estado,
            'tamanho' => $file->getSize(),
            'hash' => $hash,
            'usuario_id' => $userId,
        ]);

        return $diario;
    }

    public function extractTextFromPdf(Diario $diario): bool
    {
        try {
            $diario->marcarComoProcessando();

            $caminhoCompleto = Storage::path($diario->caminho_arquivo);
            
            if (!file_exists($caminhoCompleto)) {
                throw new \Exception("Arquivo PDF não encontrado: {$caminhoCompleto}");
            }

            // Usar Spatie PDF-to-Text para extração
            $textoCompleto = Pdf::getText($caminhoCompleto);

            if (empty(trim($textoCompleto))) {
                throw new \Exception('Não foi possível extrair texto do PDF. Arquivo pode estar corrompido ou ser uma imagem.');
            }

            // Normalizar e limpar o texto
            $textoLimpo = $this->normalizeText($textoCompleto);

            // Contar páginas aproximadamente (baseado em quebras)
            $totalPaginas = $this->estimatePageCount($textoCompleto);

            // Atualizar diário com texto extraído
            $diario->update([
                'texto_extraido' => $textoLimpo,
                'total_paginas' => $totalPaginas,
            ]);

            $diario->marcarComoConcluido();

            Log::info('PDF texto extraído com sucesso', [
                'diario_id' => $diario->id,
                'total_paginas' => $totalPaginas,
                'caracteres_extraidos' => strlen($textoLimpo),
                'tentativa' => $diario->tentativas,
            ]);

            return true;

        } catch (\Exception $e) {
            $diario->marcarComoErro($e->getMessage());

            Log::error('Erro na extração de texto do PDF', [
                'diario_id' => $diario->id,
                'erro' => $e->getMessage(),
                'tentativa' => $diario->tentativas,
            ]);

            return false;
        }
    }

    public function reprocessPdf(Diario $diario): bool
    {
        if (!$diario->podeSerReprocessado()) {
            throw new \Exception('PDF excedeu o limite de tentativas de processamento.');
        }

        // Resetar status para reprocessamento
        $diario->update([
            'status' => 'pendente',
            'erro_mensagem' => null,
            'texto_extraido' => null,
            'total_paginas' => null,
            'processado_em' => null,
        ]);

        return $this->extractTextFromPdf($diario);
    }

    private function validateFile(UploadedFile $file): void
    {
        $maxSize = SystemConfig::get('upload.max_file_size', 10485760); // 10MB padrão
        $allowedTypes = SystemConfig::get('upload.allowed_types', ['pdf']);

        if ($file->getSize() > $maxSize) {
            throw new \Exception("Arquivo muito grande. Tamanho máximo permitido: " . $this->formatBytes($maxSize));
        }

        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedTypes)) {
            throw new \Exception("Tipo de arquivo não permitido. Apenas: " . implode(', ', $allowedTypes));
        }

        if ($file->getMimeType() !== 'application/pdf') {
            throw new \Exception("Arquivo deve ser um PDF válido.");
        }
    }

    private function generateFileName(UploadedFile $file, string $estado, string $datadiario): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        
        // Limpar nome do arquivo
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        
        return "{$estado}_{$datadiario}_{$timestamp}_{$cleanName}.{$extension}";
    }

    private function normalizeText(string $text): string
    {
        // Normalizar quebras de linha
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Remover espaços em excesso mas manter estrutura
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Remover linhas vazias em excesso
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
        
        // Trim geral
        return trim($text);
    }

    private function estimatePageCount(string $text): int
    {
        // Tentar contar páginas baseado em padrões comuns
        $pageBreaks = substr_count($text, "\f"); // Form feed
        
        if ($pageBreaks > 0) {
            return $pageBreaks + 1;
        }

        // Estimar baseado no tamanho do texto (aproximadamente 3000 chars por página)
        $estimatedPages = ceil(strlen($text) / 3000);
        
        return max(1, $estimatedPages);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    public function getProcessingStats(): array
    {
        return [
            'total_diarios' => Diario::count(),
            'pendentes' => Diario::pendentes()->count(),
            'processando' => Diario::processando()->count(),
            'concluidos' => Diario::concluidos()->count(),
            'com_erro' => Diario::comErro()->count(),
            'taxa_sucesso' => $this->calculateSuccessRate(),
        ];
    }

    private function calculateSuccessRate(): float
    {
        $total = Diario::count();
        if ($total === 0) return 0;
        
        $sucessos = Diario::concluidos()->count();
        return round(($sucessos / $total) * 100, 2);
    }
}