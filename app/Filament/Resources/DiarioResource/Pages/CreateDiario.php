<?php

namespace App\Filament\Resources\DiarioResource\Pages;

use App\Filament\Resources\DiarioResource;
use App\Models\Diario;
use App\Models\ActivityLog;
use Filament\Support\Exceptions\Halt;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
                $diarioExistente = Diario::where('hash_sha256', $hashSha256)->first();
                if ($diarioExistente) {
                    $disk->delete($path);

                    $mensagem = sprintf(
                        'Este PDF já foi enviado em %s. Diário existente: %s (status: %s). Procure na lista e use "Processar" para reprocessar, se necessário.',
                        $diarioExistente->created_at->format('d/m/Y H:i'),
                        $diarioExistente->nome_arquivo,
                        $diarioExistente->status
                    );

                    $this->addError('data.arquivo', $mensagem);

                    Notification::make()
                        ->title('PDF duplicado')
                        ->body($mensagem)
                        ->warning()
                        ->persistent()
                        ->send();

                    throw new Halt();
                }
            }

            // Nome de arquivo
            if (empty($data['nome_arquivo'])) {
                $data['nome_arquivo'] = $data['nome_arquivo_original'] ?? basename($path);
            }
            if (!str_ends_with(strtolower($data['nome_arquivo']), '.pdf')) {
                $data['nome_arquivo'] .= '.pdf';
            }

            // Organizar caminho do arquivo no bucket por estado e data do diário.
            $estado = strtoupper((string) ($data['estado'] ?? 'NA'));
            $dataDiario = !empty($data['data_diario'])
                ? \Illuminate\Support\Carbon::parse($data['data_diario'])
                : now();

            $nomeBase = pathinfo($data['nome_arquivo'], PATHINFO_FILENAME);
            $nomeSeguro = Str::slug($nomeBase);
            if ($nomeSeguro === '') {
                $nomeSeguro = 'diario-oficial';
            }

            $hashCurto = substr((string) ($data['hash_sha256'] ?? Str::uuid()), 0, 8);
            $nomeFinal = sprintf(
                '%s_%s_%s_%s.pdf',
                $estado,
                $dataDiario->format('Ymd'),
                $nomeSeguro,
                $hashCurto
            );

            $caminhoFinal = sprintf(
                'diarios/%s/%s/%s',
                $estado,
                $dataDiario->format('Y/m/d'),
                $nomeFinal
            );

            if ($path !== $caminhoFinal) {
                if ($disk->exists($caminhoFinal)) {
                    $caminhoFinal = sprintf(
                        'diarios/%s/%s/%s_%s.pdf',
                        $estado,
                        $dataDiario->format('Y/m/d'),
                        $estado . '_' . $dataDiario->format('Ymd') . '_' . $nomeSeguro,
                        Str::uuid()
                    );
                }

                $movido = $disk->move($path, $caminhoFinal);

                if (!$movido) {
                    throw new \RuntimeException('Não foi possível organizar o arquivo no storage.');
                }

                $data['caminho_arquivo'] = $caminhoFinal;
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
