<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ingestao\StoreDiarioIngestaoRequest;
use App\Jobs\ProcessarPdfJob;
use App\Models\ActivityLog;
use App\Models\Diario;
use App\Models\IngestaoDiarioLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IngestaoDiarioController extends Controller
{
    public function __invoke(StoreDiarioIngestaoRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key'));

        if ($idempotencyKey === '') {
            return response()->json([
                'message' => 'Header X-Idempotency-Key é obrigatório.',
            ], 422);
        }

        $logExistente = IngestaoDiarioLog::query()
            ->where('idempotency_key', $idempotencyKey)
            ->latest('id')
            ->first();

        if ($logExistente) {
            return $this->respostaIdempotente($logExistente);
        }

        [$assinaturaValida, $mensagemAssinatura] = $this->validarAssinatura($request);

        $logIngestao = $this->iniciarLog(
            payload: $payload,
            request: $request,
            idempotencyKey: $idempotencyKey,
            assinaturaValida: $assinaturaValida
        );

        if (! $assinaturaValida) {
            $resposta = [
                'message' => $mensagemAssinatura ?? 'Assinatura inválida.',
            ];

            $this->finalizarLog(
                $logIngestao,
                status: 'rejeitado',
                httpStatus: 401,
                mensagem: $resposta['message'],
                responsePayload: $resposta
            );

            return response()->json($resposta, 401);
        }

        try {
            $objectKey = ltrim((string) $payload['object_key'], '/');
            $disk = Storage::disk(config('filesystems.diarios_disk', 'diarios'));

            if (! $disk->exists($objectKey)) {
                $resposta = [
                    'message' => 'Arquivo informado não existe no storage.',
                    'object_key' => $objectKey,
                ];

                $this->finalizarLog(
                    $logIngestao,
                    status: 'rejeitado',
                    httpStatus: 422,
                    mensagem: $resposta['message'],
                    responsePayload: $resposta
                );

                return response()->json($resposta, 422);
            }

            $sha256 = strtolower((string) $payload['sha256']);

            if ((bool) config('ingest.verify_object_hash', false)) {
                $hashCalculado = $this->calcularHashArquivo($disk, $objectKey);

                if ($hashCalculado !== $sha256) {
                    $resposta = [
                        'message' => 'Hash do arquivo diverge do hash informado.',
                        'sha256_informado' => $sha256,
                        'sha256_calculado' => $hashCalculado,
                    ];

                    $this->finalizarLog(
                        $logIngestao,
                        status: 'rejeitado',
                        httpStatus: 422,
                        mensagem: $resposta['message'],
                        responsePayload: $resposta
                    );

                    return response()->json($resposta, 422);
                }
            }

            $logExternalId = IngestaoDiarioLog::query()
                ->where('source', $payload['source'])
                ->where('external_id', $payload['external_id'])
                ->whereIn('status', ['enfileirado', 'duplicado'])
                ->latest('id')
                ->first();

            if ($logExternalId && $logExternalId->diario_id) {
                $resposta = [
                    'message' => 'Ingestão já processada anteriormente para source + external_id.',
                    'duplicate' => true,
                    'diario_id' => $logExternalId->diario_id,
                    'ingestao_log_id' => $logExternalId->id,
                ];

                $this->finalizarLog(
                    $logIngestao,
                    status: 'duplicado',
                    httpStatus: 200,
                    mensagem: $resposta['message'],
                    diarioId: $logExternalId->diario_id,
                    responsePayload: $resposta
                );

                return response()->json($resposta, 200);
            }

            $diarioExistente = Diario::query()
                ->where('hash_sha256', $sha256)
                ->first();

            if ($diarioExistente) {
                $resposta = [
                    'message' => 'PDF já cadastrado anteriormente.',
                    'duplicate' => true,
                    'diario_id' => $diarioExistente->id,
                    'nome_arquivo' => $diarioExistente->nome_arquivo,
                    'status' => $diarioExistente->status,
                ];

                $this->finalizarLog(
                    $logIngestao,
                    status: 'duplicado',
                    httpStatus: 200,
                    mensagem: $resposta['message'],
                    diarioId: $diarioExistente->id,
                    responsePayload: $resposta
                );

                return response()->json($resposta, 200);
            }

            $usuarioSistema = $this->resolverUsuarioSistema();

            if (! $usuarioSistema) {
                throw new \RuntimeException('Nenhum usuário disponível para registrar upload de ingestão.');
            }

            $nomeArquivo = $this->normalizarNomeArquivo((string) $payload['nome_arquivo']);

            $diario = Diario::query()->create([
                'nome_arquivo' => $nomeArquivo,
                'estado' => strtoupper((string) $payload['estado']),
                'data_diario' => $payload['data_diario'],
                'hash_sha256' => $sha256,
                'caminho_arquivo' => $objectKey,
                'tamanho_arquivo' => (int) $payload['size_bytes'],
                'status' => 'processando',
                'status_processamento' => 'processando',
                'erro_mensagem' => null,
                'erro_processamento' => null,
                'tentativas' => 0,
                'usuario_upload_id' => $usuarioSistema->id,
            ]);

            $job = ProcessarPdfJob::dispatch($diario, [
                'tipo' => 'inicial',
                'modo' => 'completo',
                'motivo' => 'Ingestão automática via webhook (n8n)',
                'notificar' => (bool) config('ingest.notify_on_enqueue', true),
                'limpar_ocorrencias_anteriores' => true,
                'iniciado_por_user_id' => $usuarioSistema->id,
            ]);

            $queue = trim((string) config('ingest.queue', ''));

            if ($queue !== '') {
                $job->onQueue($queue);
            }

            $resposta = [
                'message' => 'Diário recebido e enfileirado para processamento.',
                'accepted' => true,
                'diario_id' => $diario->id,
                'status' => $diario->status,
                'object_key' => $objectKey,
            ];

            $this->finalizarLog(
                $logIngestao,
                status: 'enfileirado',
                httpStatus: 202,
                mensagem: $resposta['message'],
                diarioId: $diario->id,
                responsePayload: $resposta
            );

            ActivityLog::logActivity([
                'action' => 'ingestao_enfileirada',
                'entity_type' => 'Diario',
                'entity_id' => $diario->id,
                'entity_name' => $diario->nome_arquivo,
                'description' => "Ingestão automática recebida de {$payload['source']} para o diário '{$diario->nome_arquivo}'.",
                'icon' => 'heroicon-o-arrow-down-tray',
                'color' => 'info',
                'context' => [
                    'idempotency_key' => $idempotencyKey,
                    'external_id' => $payload['external_id'],
                    'source' => $payload['source'],
                    'object_key' => $objectKey,
                ],
            ]);

            return response()->json($resposta, 202);
        } catch (Throwable $e) {
            Log::error('Falha na ingestão de diário via webhook.', [
                'source' => $payload['source'] ?? null,
                'external_id' => $payload['external_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'erro' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $resposta = [
                'message' => 'Falha interna ao processar ingestão.',
                'error' => $e->getMessage(),
            ];

            $this->finalizarLog(
                $logIngestao,
                status: 'erro',
                httpStatus: 500,
                mensagem: $e->getMessage(),
                responsePayload: $resposta
            );

            return response()->json($resposta, 500);
        }
    }

    private function validarAssinatura(Request $request): array
    {
        $requireSignature = (bool) config('ingest.require_signature', true);
        $secret = (string) config('ingest.webhook_secret', '');

        if (! $requireSignature) {
            return [true, null];
        }

        if ($secret === '') {
            return [false, 'Segredo de ingestão não configurado (INGEST_WEBHOOK_SECRET).'];
        }

        $timestamp = trim((string) $request->header('X-Timestamp'));
        $signatureHeader = trim((string) $request->header('X-Signature'));

        if ($timestamp === '' || $signatureHeader === '') {
            return [false, 'Headers X-Timestamp e X-Signature são obrigatórios.'];
        }

        if (! ctype_digit($timestamp)) {
            return [false, 'Header X-Timestamp inválido.'];
        }

        $tolerance = max(30, (int) config('ingest.signature_tolerance_seconds', 300));
        $delta = abs(now()->timestamp - (int) $timestamp);

        if ($delta > $tolerance) {
            return [false, 'Timestamp fora da janela permitida para assinatura.'];
        }

        $signature = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return [false, 'Assinatura HMAC inválida.'];
        }

        return [true, null];
    }

    private function iniciarLog(
        array $payload,
        Request $request,
        string $idempotencyKey,
        bool $assinaturaValida
    ): IngestaoDiarioLog {
        return IngestaoDiarioLog::query()->create([
            'idempotency_key' => $idempotencyKey,
            'source' => (string) ($payload['source'] ?? 'desconhecido'),
            'external_id' => (string) ($payload['external_id'] ?? ''),
            'status' => 'recebido',
            'estado' => $payload['estado'] ?? null,
            'data_diario' => $payload['data_diario'] ?? null,
            'nome_arquivo' => $payload['nome_arquivo'] ?? null,
            'bucket' => $payload['bucket'] ?? null,
            'object_key' => $payload['object_key'] ?? null,
            'sha256' => $payload['sha256'] ?? null,
            'size_bytes' => isset($payload['size_bytes']) ? (int) $payload['size_bytes'] : null,
            'signature_valid' => $assinaturaValida,
            'request_ip' => $request->ip(),
            'metadata' => $payload['metadata'] ?? null,
            'request_payload' => $payload,
            'processed_at' => now(),
        ]);
    }

    private function finalizarLog(
        IngestaoDiarioLog $log,
        string $status,
        int $httpStatus,
        ?string $mensagem = null,
        ?int $diarioId = null,
        array $responsePayload = [],
    ): void {
        $log->update([
            'status' => $status,
            'http_status' => $httpStatus,
            'mensagem' => $mensagem,
            'diario_id' => $diarioId,
            'response_payload' => $responsePayload,
            'processed_at' => now(),
        ]);

        $contexto = [
            'ingestao_log_id' => $log->id,
            'idempotency_key' => $log->idempotency_key,
            'source' => $log->source,
            'external_id' => $log->external_id,
            'status' => $status,
            'http_status' => $httpStatus,
            'diario_id' => $diarioId,
            'request_ip' => $log->request_ip,
        ];

        if (in_array($status, ['erro', 'rejeitado'], true)) {
            Log::channel('audit')->warning('[INGESTAO] Finalizada com problema', $contexto);
            return;
        }

        Log::channel('audit')->info('[INGESTAO] Finalizada com sucesso técnico', $contexto);
    }

    private function respostaIdempotente(IngestaoDiarioLog $logExistente): JsonResponse
    {
        $response = [
            'message' => 'Requisição já processada anteriormente.',
            'duplicate_request' => true,
            'status' => $logExistente->status,
            'diario_id' => $logExistente->diario_id,
            'ingestao_log_id' => $logExistente->id,
        ];

        $statusCode = in_array($logExistente->status, ['enfileirado', 'duplicado'], true)
            ? 200
            : ($logExistente->http_status ?: 200);

        return response()->json($response, $statusCode);
    }

    private function normalizarNomeArquivo(string $nomeArquivo): string
    {
        $nome = trim($nomeArquivo);

        if ($nome === '') {
            $nome = 'diario_oficial.pdf';
        }

        if (! str_ends_with(strtolower($nome), '.pdf')) {
            $nome .= '.pdf';
        }

        return $nome;
    }

    private function resolverUsuarioSistema(): ?User
    {
        $userId = config('ingest.system_user_id');

        if ($userId) {
            $usuario = User::query()->find((int) $userId);

            if ($usuario) {
                return $usuario;
            }
        }

        $email = trim((string) config('ingest.system_user_email', ''));

        if ($email !== '') {
            $usuario = User::query()->where('email', $email)->first();

            if ($usuario) {
                return $usuario;
            }
        }

        $admin = User::query()
            ->where('pode_fazer_login', true)
            ->role('admin')
            ->first();

        if ($admin) {
            return $admin;
        }

        return User::query()->where('pode_fazer_login', true)->first()
            ?? User::query()->first();
    }

    private function calcularHashArquivo($disk, string $objectKey): string
    {
        $stream = $disk->readStream($objectKey);

        if (! is_resource($stream)) {
            throw new \RuntimeException('Não foi possível abrir o arquivo para validar hash.');
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}
