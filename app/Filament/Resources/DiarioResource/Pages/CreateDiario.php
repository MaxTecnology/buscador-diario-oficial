<?php

namespace App\Filament\Resources\DiarioResource\Pages;

use App\Filament\Resources\DiarioResource;
use App\Services\PdfProcessorService;
use App\Models\ActivityLog;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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

        $disk = Storage::disk(config('filesystems.diarios_disk', 'diarios'));

        if (!empty($data['arquivo']) && is_string($data['arquivo'])) {
            $path = $data['arquivo'];
            $data['caminho_arquivo'] = $path;

            // Tamanho
            $data['tamanho_arquivo'] = $disk->size($path) ?: 0;

            // Hash via stream (funciona para discos remotos)
            $stream = $disk->readStream($path);
            if ($stream) {
                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $stream);
                $hashSha256 = hash_final($ctx);
                fclose($stream);
                $data['hash_sha256'] = $hashSha256;

                // Verificar duplicidade
                $diarioExistente = \App\Models\Diario::where('hash_sha256', $hashSha256)->first();
                if ($diarioExistente) {
                    $disk->delete($path);
                    throw new \Exception("Este arquivo PDF já foi enviado anteriormente em {$diarioExistente->created_at->format('d/m/Y H:i')}. Diário existente: {$diarioExistente->nome_arquivo}");
                }
            }

            // Nome de arquivo
            if (empty($data['nome_arquivo'])) {
                $data['nome_arquivo'] = $data['nome_arquivo_original'] ?? basename($path);
            }
            if (!str_ends_with(strtolower($data['nome_arquivo']), '.pdf')) {
                $data['nome_arquivo'] .= '.pdf';
            }
        } else {
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

        // Não processar automaticamente; deixar como pendente até operador acionar
        $diario->update([
            'status' => 'pendente',
            'status_processamento' => 'pendente',
            'erro_mensagem' => null,
            'erro_processamento' => null,
        ]);

        Notification::make()
            ->title('Diário criado')
            ->body('Arquivo enviado. Clique em "Processar" na lista de diários quando quiser iniciar a detecção.')
            ->info()
            ->send();
    }
}
