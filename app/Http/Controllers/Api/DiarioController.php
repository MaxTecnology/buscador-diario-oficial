<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Diario\UploadDiarioRequest;
use App\Http\Resources\Api\DiarioResource;
use App\Http\Resources\Api\OcorrenciaResource;
use App\Jobs\ProcessarDiarioJob;
use App\Models\Diario;
use App\Models\SystemConfig;
use App\Services\DiarioProcessingService;
use App\Services\PdfProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DiarioController extends Controller
{
    public function __construct(
        private PdfProcessingService $pdfService,
        private DiarioProcessingService $processingService
    ) {}

    /**
     * Listar diários com filtros e paginação
     */
    public function index(Request $request): JsonResponse
    {
        $query = Diario::with(['usuario', 'ocorrencias']);

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('data_inicio')) {
            $query->where('data_diario', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->where('data_diario', '<=', $request->data_fim);
        }

        if ($request->filled('usuario_id')) {
            $query->where('usuario_upload_id', $request->usuario_id);
        }

        // Ordenação
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginação
        $perPage = min($request->get('per_page', 15), 100);
        $diarios = $query->paginate($perPage);

        return response()->json([
            'data' => DiarioResource::collection($diarios->items()),
            'meta' => [
                'current_page' => $diarios->currentPage(),
                'last_page' => $diarios->lastPage(),
                'per_page' => $diarios->perPage(),
                'total' => $diarios->total(),
                'from' => $diarios->firstItem(),
                'to' => $diarios->lastItem(),
            ],
            'stats' => $this->getIndexStats(),
        ]);
    }

    /**
     * Upload de novo diário
     */
    public function upload(UploadDiarioRequest $request): JsonResponse
    {
        try {
            $diario = $this->pdfService->processUploadedFile(
                $request->file('arquivo'),
                $request->estado,
                $request->data_diario,
                $request->user()->id
            );

            // Disparar job de processamento assíncrono
            ProcessarDiarioJob::dispatch($diario)->onQueue('pdf-processing');

            return response()->json([
                'message' => 'Diário enviado com sucesso. Processamento iniciado.',
                'data' => new DiarioResource($diario),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro no upload do diário.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Detalhes de um diário
     */
    public function show(Diario $diario): JsonResponse
    {
        $diario->load(['usuario', 'ocorrencias.empresa']);

        return response()->json([
            'data' => new DiarioResource($diario),
            'stats' => [
                'total_ocorrencias' => $diario->ocorrencias->count(),
                'empresas_encontradas' => $diario->ocorrencias->pluck('empresa_id')->unique()->count(),
                'score_medio' => $diario->ocorrencias->avg('score_confianca'),
            ],
        ]);
    }

    /**
     * Download do arquivo PDF
     */
    public function download(Diario $diario): BinaryFileResponse
    {
        $disk = Storage::disk(config('filesystems.diarios_disk', 'diarios'));
        if (!$disk->exists($diario->caminho_arquivo)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return $disk->download($diario->caminho_arquivo, $diario->nome_arquivo, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Reprocessar diário
     */
    public function reprocess(Diario $diario): JsonResponse
    {
        try {
            if (!$diario->podeSerReprocessado()) {
                return response()->json([
                    'message' => 'Diário excedeu o limite de tentativas de processamento.',
                ], 422);
            }

            // Disparar job de reprocessamento
            ProcessarDiarioJob::dispatch($diario)->onQueue('pdf-processing');

            return response()->json([
                'message' => 'Reprocessamento iniciado.',
                'data' => new DiarioResource($diario->fresh()),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao iniciar reprocessamento.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Deletar diário
     */
    public function destroy(Diario $diario): JsonResponse
    {
        try {
            $disk = Storage::disk(config('filesystems.diarios_disk', 'diarios'));
            // Deletar arquivo físico
            if ($disk->exists($diario->caminho_arquivo)) {
                $disk->delete($diario->caminho_arquivo);
            }

            // Deletar registro (cascata deleta ocorrências)
            $diario->delete();

            return response()->json([
                'message' => 'Diário deletado com sucesso.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao deletar diário.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Listar diários pendentes
     */
    public function pendentes(Request $request): JsonResponse
    {
        $query = Diario::pendentes()->with(['usuario']);
        $perPage = min($request->get('per_page', 15), 100);
        $diarios = $query->paginate($perPage);

        return response()->json([
            'data' => DiarioResource::collection($diarios->items()),
            'meta' => [
                'total' => $diarios->total(),
                'current_page' => $diarios->currentPage(),
                'last_page' => $diarios->lastPage(),
            ],
        ]);
    }

    /**
     * Listar diários processando
     */
    public function processando(Request $request): JsonResponse
    {
        $query = Diario::processando()->with(['usuario']);
        $perPage = min($request->get('per_page', 15), 100);
        $diarios = $query->paginate($perPage);

        return response()->json([
            'data' => DiarioResource::collection($diarios->items()),
            'meta' => [
                'total' => $diarios->total(),
                'current_page' => $diarios->currentPage(),
                'last_page' => $diarios->lastPage(),
            ],
        ]);
    }

    /**
     * Listar diários concluídos
     */
    public function concluidos(Request $request): JsonResponse
    {
        $query = Diario::concluidos()->with(['usuario']);
        $perPage = min($request->get('per_page', 15), 100);
        $diarios = $query->paginate($perPage);

        return response()->json([
            'data' => DiarioResource::collection($diarios->items()),
            'meta' => [
                'total' => $diarios->total(),
                'current_page' => $diarios->currentPage(),
                'last_page' => $diarios->lastPage(),
            ],
        ]);
    }

    /**
     * Listar diários com erro
     */
    public function comErro(Request $request): JsonResponse
    {
        $query = Diario::comErro()->with(['usuario']);
        $perPage = min($request->get('per_page', 15), 100);
        $diarios = $query->paginate($perPage);

        return response()->json([
            'data' => DiarioResource::collection($diarios->items()),
            'meta' => [
                'total' => $diarios->total(),
                'current_page' => $diarios->currentPage(),
                'last_page' => $diarios->lastPage(),
            ],
        ]);
    }

    /**
     * Ocorrências de um diário
     */
    public function ocorrencias(Diario $diario, Request $request): JsonResponse
    {
        $query = $diario->ocorrencias()->with(['empresa']);

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->empresa_id);
        }

        if ($request->filled('tipo_match')) {
            $query->where('tipo_match', $request->tipo_match);
        }

        if ($request->filled('score_minimo')) {
            $query->where('score_confianca', '>=', $request->score_minimo);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $ocorrencias = $query->paginate($perPage);

        return response()->json([
            'data' => OcorrenciaResource::collection($ocorrencias->items()),
            'meta' => [
                'total' => $ocorrencias->total(),
                'current_page' => $ocorrencias->currentPage(),
                'last_page' => $ocorrencias->lastPage(),
            ],
        ]);
    }

    /**
     * Estatísticas de um diário
     */
    public function stats(Diario $diario): JsonResponse
    {
        return response()->json([
            'diario' => [
                'id' => $diario->id,
                'nome_arquivo' => $diario->nome_arquivo,
                'status' => $diario->status,
                'total_paginas' => $diario->total_paginas,
                'tamanho_arquivo' => $diario->tamanho_arquivo,
            ],
            'ocorrencias' => [
                'total' => $diario->ocorrencias->count(),
                'por_tipo' => $diario->ocorrencias->groupBy('tipo_match')->map->count(),
                'empresas_encontradas' => $diario->ocorrencias->pluck('empresa_id')->unique()->count(),
                'score_medio' => $diario->ocorrencias->avg('score_confianca'),
                'notificacoes_enviadas' => [
                    'email' => $diario->ocorrencias->where('notificado_email', true)->count(),
                    'whatsapp' => $diario->ocorrencias->where('notificado_whatsapp', true)->count(),
                ],
            ],
        ]);
    }

    /**
     * Progresso do processamento
     */
    public function progress(Diario $diario): JsonResponse
    {
        return response()->json([
            'status' => $diario->status,
            'tentativas' => $diario->tentativas,
            'max_tentativas' => SystemConfig::get('processing.max_retries', 3),
            'processado_em' => $diario->processado_em?->toISOString(),
            'erro_mensagem' => $diario->erro_mensagem,
            'pode_reprocessar' => $diario->podeSerReprocessado(),
        ]);
    }

    private function getIndexStats(): array
    {
        return [
            'total' => Diario::count(),
            'pendentes' => Diario::pendentes()->count(),
            'processando' => Diario::processando()->count(),
            'concluidos' => Diario::concluidos()->count(),
            'com_erro' => Diario::comErro()->count(),
        ];
    }
}
