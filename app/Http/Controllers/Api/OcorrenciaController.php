<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OcorrenciaResource;
use App\Jobs\EnviarNotificacaoEmailJob;
use App\Jobs\EnviarNotificacaoWhatsappJob;
use App\Models\Ocorrencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OcorrenciaController extends Controller
{
    /**
     * Listar ocorrências com filtros e paginação
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ocorrencia::with(['empresa', 'diario', 'diario.usuario']);

        // Filtros
        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->empresa_id);
        }

        if ($request->filled('diario_id')) {
            $query->where('diario_id', $request->diario_id);
        }

        if ($request->filled('tipo_match')) {
            $query->where('tipo_match', $request->tipo_match);
        }

        if ($request->filled('score_minimo')) {
            $query->where('score_confianca', '>=', $request->score_minimo);
        }

        if ($request->filled('data_inicio')) {
            $query->where('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->where('created_at', '<=', $request->data_fim);
        }

        if ($request->filled('notificado_email')) {
            $query->where('notificado_email', $request->boolean('notificado_email'));
        }

        if ($request->filled('notificado_whatsapp')) {
            $query->where('notificado_whatsapp', $request->boolean('notificado_whatsapp'));
        }

        if ($request->filled('estado')) {
            $query->whereHas('diario', function ($q) use ($request) {
                $q->where('estado', $request->estado);
            });
        }

        // Busca em texto livre
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('contexto', 'like', "%{$searchTerm}%")
                  ->orWhere('termo_encontrado', 'like', "%{$searchTerm}%")
                  ->orWhereHas('empresa', function ($eq) use ($searchTerm) {
                      $eq->where('nome', 'like', "%{$searchTerm}%")
                         ->orWhere('cnpj', 'like', "%{$searchTerm}%");
                  });
            });
        }

        // Ordenação
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginação
        $perPage = min($request->get('per_page', 15), 100);
        $ocorrencias = $query->paginate($perPage);

        return response()->json([
            'data' => OcorrenciaResource::collection($ocorrencias->items()),
            'meta' => [
                'current_page' => $ocorrencias->currentPage(),
                'last_page' => $ocorrencias->lastPage(),
                'per_page' => $ocorrencias->perPage(),
                'total' => $ocorrencias->total(),
                'from' => $ocorrencias->firstItem(),
                'to' => $ocorrencias->lastItem(),
            ],
            'stats' => $this->getIndexStats(),
        ]);
    }

    /**
     * Busca avançada de ocorrências
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'termo' => 'required|string|min:2',
            'empresa_ids' => 'array',
            'empresa_ids.*' => 'exists:empresas,id',
            'score_minimo' => 'numeric|min:0|max:100',
            'data_inicio' => 'date',
            'data_fim' => 'date|after_or_equal:data_inicio',
            'tipos_match' => 'array',
            'tipos_match.*' => 'in:cnpj,nome,termo',
        ]);

        $query = Ocorrencia::with(['empresa', 'diario'])
            ->where('contexto', 'like', '%' . $request->termo . '%');

        if ($request->filled('empresa_ids')) {
            $query->whereIn('empresa_id', $request->empresa_ids);
        }

        if ($request->filled('score_minimo')) {
            $query->where('score_confianca', '>=', $request->score_minimo);
        }

        if ($request->filled('data_inicio')) {
            $query->where('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->where('created_at', '<=', $request->data_fim);
        }

        if ($request->filled('tipos_match')) {
            $query->whereIn('tipo_match', $request->tipos_match);
        }

        $perPage = min($request->get('per_page', 20), 100);
        $ocorrencias = $query->orderBy('score_confianca', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => OcorrenciaResource::collection($ocorrencias->items()),
            'meta' => [
                'current_page' => $ocorrencias->currentPage(),
                'last_page' => $ocorrencias->lastPage(),
                'per_page' => $ocorrencias->perPage(),
                'total' => $ocorrencias->total(),
                'termo_busca' => $request->termo,
            ],
        ]);
    }

    /**
     * Detalhes de uma ocorrência
     */
    public function show(Ocorrencia $ocorrencia): JsonResponse
    {
        $ocorrencia->load(['empresa', 'diario', 'diario.usuario']);

        return response()->json([
            'data' => new OcorrenciaResource($ocorrencia),
        ]);
    }

    /**
     * Exportar ocorrências para CSV/Excel
     */
    public function export(Request $request): BinaryFileResponse
    {
        $request->validate([
            'formato' => 'in:csv,xlsx',
            'filtros' => 'array',
        ]);

        try {
            $query = Ocorrencia::with(['empresa', 'diario']);

            // Aplicar filtros da busca se fornecidos
            if ($request->filled('filtros')) {
                $filtros = $request->filtros;

                if (isset($filtros['empresa_id'])) {
                    $query->where('empresa_id', $filtros['empresa_id']);
                }

                if (isset($filtros['data_inicio'])) {
                    $query->where('created_at', '>=', $filtros['data_inicio']);
                }

                if (isset($filtros['data_fim'])) {
                    $query->where('created_at', '<=', $filtros['data_fim']);
                }

                if (isset($filtros['score_minimo'])) {
                    $query->where('score_confianca', '>=', $filtros['score_minimo']);
                }
            }

            $ocorrencias = $query->orderBy('created_at', 'desc')->get();

            $filename = 'ocorrencias_' . now()->format('Y-m-d_H-i-s') . '.csv';
            $path = storage_path('app/temp/' . $filename);

            // Garantir que o diretório existe
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $fp = fopen($path, 'w');

            // Cabeçalho
            fputcsv($fp, [
                'ID',
                'Empresa',
                'CNPJ',
                'Diário',
                'Estado',
                'Data Diário',
                'Tipo Match',
                'Score',
                'Termo Encontrado',
                'Contexto',
                'Email Enviado',
                'WhatsApp Enviado',
                'Data Ocorrência',
            ]);

            // Dados
            foreach ($ocorrencias as $ocorrencia) {
                fputcsv($fp, [
                    $ocorrencia->id,
                    $ocorrencia->empresa->nome,
                    $ocorrencia->empresa->cnpj,
                    $ocorrencia->diario->nome_arquivo,
                    $ocorrencia->diario->estado,
                    $ocorrencia->diario->data_diario->format('Y-m-d'),
                    $ocorrencia->tipo_match,
                    $ocorrencia->score_confianca,
                    $ocorrencia->termo_encontrado,
                    mb_substr($ocorrencia->contexto, 0, 200),
                    $ocorrencia->notificado_email ? 'Sim' : 'Não',
                    $ocorrencia->notificado_whatsapp ? 'Sim' : 'Não',
                    $ocorrencia->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($fp);

            return response()->download($path, $filename, [
                'Content-Type' => 'text/csv',
            ])->deleteFileAfterSend();

        } catch (\Exception $e) {
            abort(500, 'Erro na exportação: ' . $e->getMessage());
        }
    }

    /**
     * Estatísticas gerais de ocorrências
     */
    public function stats(Request $request): JsonResponse
    {
        $query = Ocorrencia::query();

        // Filtros opcionais para as estatísticas
        if ($request->filled('data_inicio')) {
            $query->where('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->where('created_at', '<=', $request->data_fim);
        }

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->empresa_id);
        }

        $stats = [
            'total' => $query->count(),
            'por_tipo' => $query->selectRaw('tipo_match, COUNT(*) as total')
                ->groupBy('tipo_match')
                ->pluck('total', 'tipo_match')
                ->toArray(),
            'score_medio' => round($query->avg('score_confianca'), 2),
            'score_distribuicao' => [
                'alto' => $query->where('score_confianca', '>=', 90)->count(),
                'medio' => $query->whereBetween('score_confianca', [70, 89])->count(),
                'baixo' => $query->where('score_confianca', '<', 70)->count(),
            ],
            'notificacoes' => [
                'email_enviados' => $query->where('notificado_email', true)->count(),
                'whatsapp_enviados' => $query->where('notificado_whatsapp', true)->count(),
                'pendentes_email' => $query->where('notificado_email', false)->count(),
                'pendentes_whatsapp' => $query->where('notificado_whatsapp', false)->count(),
            ],
            'por_empresa' => $query->with('empresa')
                ->selectRaw('empresa_id, COUNT(*) as total')
                ->groupBy('empresa_id')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'empresa' => $item->empresa->nome,
                        'cnpj' => $item->empresa->cnpj,
                        'total' => $item->total,
                    ];
                }),
            'timeline' => $query->selectRaw('DATE(created_at) as data, COUNT(*) as total')
                ->groupBy('data')
                ->orderBy('data', 'desc')
                ->limit(30)
                ->pluck('total', 'data')
                ->toArray(),
        ];

        return response()->json($stats);
    }

    /**
     * Reenviar notificação por email
     */
    public function resendEmail(Ocorrencia $ocorrencia): JsonResponse
    {
        try {
            if ($ocorrencia->notificado_email) {
                return response()->json([
                    'message' => 'Email já foi enviado para esta ocorrência.',
                ], 422);
            }

            EnviarNotificacaoEmailJob::dispatch($ocorrencia)
                ->onQueue('notifications');

            return response()->json([
                'message' => 'Reenvio de email programado com sucesso.',
                'data' => new OcorrenciaResource($ocorrencia),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao programar reenvio de email.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reenviar notificação via WhatsApp
     */
    public function resendWhatsapp(Ocorrencia $ocorrencia): JsonResponse
    {
        try {
            if ($ocorrencia->notificado_whatsapp) {
                return response()->json([
                    'message' => 'WhatsApp já foi enviado para esta ocorrência.',
                ], 422);
            }

            EnviarNotificacaoWhatsappJob::dispatch($ocorrencia)
                ->onQueue('notifications');

            return response()->json([
                'message' => 'Reenvio de WhatsApp programado com sucesso.',
                'data' => new OcorrenciaResource($ocorrencia),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao programar reenvio de WhatsApp.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function getIndexStats(): array
    {
        return [
            'total' => Ocorrencia::count(),
            'hoje' => Ocorrencia::whereDate('created_at', today())->count(),
            'esta_semana' => Ocorrencia::where('created_at', '>=', now()->startOfWeek())->count(),
            'este_mes' => Ocorrencia::where('created_at', '>=', now()->startOfMonth())->count(),
            'score_medio' => round(Ocorrencia::avg('score_confianca'), 2),
            'notificacoes_pendentes' => [
                'email' => Ocorrencia::where('notificado_email', false)->count(),
                'whatsapp' => Ocorrencia::where('notificado_whatsapp', false)->count(),
            ],
        ];
    }
}
