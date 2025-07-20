<?php

namespace App\Filament\Resources\DiarioResource\Pages;

use App\Filament\Resources\DiarioResource;
use App\Services\PdfProcessorService;
use App\Models\ActivityLog;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreateDiario extends CreateRecord
{
    protected static string $resource = DiarioResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Definir usuário que fez o upload
        $data['usuario_upload_id'] = Auth::id();

        // Definir status inicial
        $data['status'] = 'pendente';
        $data['tentativas'] = 0;

        // O arquivo é processado automaticamente pelo Filament
        // Apenas precisamos gerar o hash e definir outros campos
        if (!empty($data['arquivo']) && is_string($data['arquivo'])) {
            // O Filament já processou o arquivo e retornou o caminho
            $data['caminho_arquivo'] = $data['arquivo'];
            
            // Tentar extrair informações do arquivo
            $fullPath = storage_path('app/public/' . $data['arquivo']);
            if (file_exists($fullPath)) {
                $hashSha256 = hash_file('sha256', $fullPath);
                
                // Verificar se já existe um diário com o mesmo hash
                $diarioExistente = \App\Models\Diario::where('hash_sha256', $hashSha256)->first();
                if ($diarioExistente) {
                    // Deletar o arquivo enviado para evitar duplicação
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($data['arquivo']);
                    
                    throw new \Exception("Este arquivo PDF já foi enviado anteriormente em {$diarioExistente->created_at->format('d/m/Y H:i')}. Diário existente: {$diarioExistente->nome_arquivo}");
                }
                
                $data['hash_sha256'] = $hashSha256;
                $data['tamanho_arquivo'] = filesize($fullPath);
                
                // Se nome_arquivo não foi fornecido, usar o nome original do arquivo
                if (empty($data['nome_arquivo'])) {
                    $data['nome_arquivo'] = $data['nome_arquivo_original'] ?? basename($data['arquivo']);
                }
                
                // Garantir que o nome do arquivo tenha a extensão .pdf
                if (!str_ends_with(strtolower($data['nome_arquivo']), '.pdf')) {
                    $data['nome_arquivo'] .= '.pdf';
                }
            }
        } else {
            // Fallback se não há arquivo
            $data['hash_sha256'] = hash('sha256', ($data['nome_arquivo'] ?? '') . time());
            $data['caminho_arquivo'] = null;
            $data['tamanho_arquivo'] = null;
        }

        // Remover campos temporários
        unset($data['arquivo']);
        unset($data['nome_arquivo_original']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $diario = $this->record;
        
        // Registrar log de atividade
        ActivityLog::logDiarioCreated($diario);
        
        if ($diario->caminho_arquivo) {
            // Verificar se deve processar de forma síncrona ou assíncrona
            $processamentoAssincrono = \App\Models\ConfiguracaoSistema::get('processamento_assincrono', false);
            
            if ($processamentoAssincrono) {
                // Processar de forma assíncrona usando job
                \App\Jobs\ProcessarPdfJob::dispatch($diario);
                
                Notification::make()
                    ->title('PDF Enviado para Processamento!')
                    ->body('O arquivo foi enviado para a fila de processamento. Você será notificado quando concluído.')
                    ->info()
                    ->send();
            } else {
                // Processar de forma síncrona (original)
                try {
                    $processorService = new PdfProcessorService();
                    $resultado = $processorService->processarPdf($diario);
                    
                    if ($resultado['sucesso']) {
                        Notification::make()
                            ->title('PDF Processado com Sucesso!')
                            ->body("Texto extraído: {$resultado['texto_extraido']} caracteres. Ocorrências encontradas: {$resultado['ocorrencias_encontradas']}")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Erro no Processamento')
                            ->body("Falha ao processar PDF: {$resultado['erro']}")
                            ->danger()
                            ->send();
                    }
                } catch (\Exception $e) {
                    // Se falhar, tentar de forma assíncrona como fallback
                    \App\Jobs\ProcessarPdfJob::dispatch($diario);
                    
                    Notification::make()
                        ->title('Processamento Demorou Muito')
                        ->body('O arquivo foi enviado para processamento em segundo plano devido ao seu tamanho.')
                        ->warning()
                        ->send();
                }
            }
        }
    }
}
