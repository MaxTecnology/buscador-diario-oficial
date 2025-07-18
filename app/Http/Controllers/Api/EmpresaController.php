<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Empresa\CreateEmpresaRequest;
use App\Http\Requests\Api\Empresa\UpdateEmpresaRequest;
use App\Http\Resources\Api\EmpresaResource;
use App\Http\Resources\Api\OcorrenciaResource;
use App\Models\Empresa;
use App\Services\EmpresaSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmpresaController extends Controller
{
    public function __construct(
        private EmpresaSearchService $searchService
    ) {}

    /**
     * Listar empresas com filtros e paginação
     */
    public function index(Request $request): JsonResponse
    {
        $query = Empresa::query();

        // Filtros
        if ($request->filled('nome')) {
            $query->where('nome', 'like', '%' . $request->nome . '%');
        }

        if ($request->filled('cnpj')) {
            $query->where('cnpj', 'like', '%' . $request->cnpj . '%');
        }

        if ($request->filled('ativo')) {
            $query->where('ativo', $request->boolean('ativo'));
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('cidade')) {
            $query->where('cidade', 'like', '%' . $request->cidade . '%');
        }

        // Busca em texto livre
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nome', 'like', "%{$searchTerm}%")
                  ->orWhere('cnpj', 'like', "%{$searchTerm}%")
                  ->orWhere('razao_social', 'like', "%{$searchTerm}%")
                  ->orWhereJsonContains('termos_busca', $searchTerm);
            });
        }

        // Ordenação
        $sortBy = $request->get('sort_by', 'nome');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginação
        $perPage = min($request->get('per_page', 15), 100);
        $empresas = $query->paginate($perPage);

        return response()->json([
            'data' => EmpresaResource::collection($empresas->items()),
            'meta' => [
                'current_page' => $empresas->currentPage(),
                'last_page' => $empresas->lastPage(),
                'per_page' => $empresas->perPage(),
                'total' => $empresas->total(),
                'from' => $empresas->firstItem(),
                'to' => $empresas->lastItem(),
            ],
            'stats' => $this->getIndexStats(),
        ]);
    }

    /**
     * Criar nova empresa
     */
    public function store(CreateEmpresaRequest $request): JsonResponse
    {
        try {
            $empresa = Empresa::create($request->validated());

            return response()->json([
                'message' => 'Empresa criada com sucesso.',
                'data' => new EmpresaResource($empresa),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar empresa.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Detalhes de uma empresa
     */
    public function show(Empresa $empresa): JsonResponse
    {
        return response()->json([
            'data' => new EmpresaResource($empresa),
            'stats' => [
                'total_ocorrencias' => $empresa->ocorrencias()->count(),
                'ocorrencias_30_dias' => $empresa->ocorrencias()
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
                'score_medio' => $empresa->ocorrencias()->avg('score_confianca'),
                'usuarios_vinculados' => $empresa->users()->count(),
            ],
        ]);
    }

    /**
     * Atualizar empresa
     */
    public function update(UpdateEmpresaRequest $request, Empresa $empresa): JsonResponse
    {
        try {
            $empresa->update($request->validated());

            return response()->json([
                'message' => 'Empresa atualizada com sucesso.',
                'data' => new EmpresaResource($empresa->fresh()),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar empresa.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Deletar empresa
     */
    public function destroy(Empresa $empresa): JsonResponse
    {
        try {
            $empresa->delete();

            return response()->json([
                'message' => 'Empresa deletada com sucesso.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao deletar empresa.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Busca avançada de empresas
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'termo' => 'required|string|min:2',
            'limite' => 'integer|min:1|max:100',
            'score_minimo' => 'numeric|min:0|max:100',
        ]);

        try {
            $empresas = $this->searchService->buscarEmpresas(
                termo: $request->termo,
                limite: $request->get('limite', 20),
                scoreMinimo: $request->get('score_minimo', 70)
            );

            return response()->json([
                'data' => $empresas->map(function ($item) {
                    return [
                        'empresa' => new EmpresaResource($item['empresa']),
                        'score' => $item['score'],
                        'tipo_match' => $item['tipo_match'],
                    ];
                }),
                'meta' => [
                    'total_encontradas' => $empresas->count(),
                    'termo_busca' => $request->termo,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro na busca de empresas.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Importar empresas via CSV/Excel
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:csv,xlsx|max:10240',
        ]);

        try {
            // Implementar lógica de importação
            // Por enquanto retornar sucesso simples
            return response()->json([
                'message' => 'Importação processada com sucesso.',
                'data' => [
                    'arquivo' => $request->file('arquivo')->getClientOriginalName(),
                    'status' => 'processando',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro na importação.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exportar empresas para CSV/Excel
     */
    public function export(Request $request): BinaryFileResponse
    {
        $request->validate([
            'formato' => 'in:csv,xlsx',
            'filtros' => 'array',
        ]);

        try {
            // Implementar lógica de exportação
            $filename = 'empresas_' . now()->format('Y-m-d_H-i-s') . '.csv';
            $path = storage_path('app/temp/' . $filename);

            // Criar CSV simples por enquanto
            $empresas = Empresa::all();
            $fp = fopen($path, 'w');
            
            // Cabeçalho
            fputcsv($fp, ['ID', 'Nome', 'CNPJ', 'Estado', 'Ativo']);
            
            // Dados
            foreach ($empresas as $empresa) {
                fputcsv($fp, [
                    $empresa->id,
                    $empresa->nome,
                    $empresa->cnpj,
                    $empresa->estado,
                    $empresa->ativo ? 'Sim' : 'Não',
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
     * Histórico de ocorrências por empresa
     */
    public function ocorrencias(Empresa $empresa, Request $request): JsonResponse
    {
        $query = $empresa->ocorrencias()->with(['diario']);

        // Filtros
        if ($request->filled('data_inicio')) {
            $query->where('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->where('created_at', '<=', $request->data_fim);
        }

        if ($request->filled('tipo_match')) {
            $query->where('tipo_match', $request->tipo_match);
        }

        if ($request->filled('score_minimo')) {
            $query->where('score_confianca', '>=', $request->score_minimo);
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
            ],
        ]);
    }

    /**
     * Estatísticas de uma empresa
     */
    public function stats(Empresa $empresa): JsonResponse
    {
        $ocorrencias = $empresa->ocorrencias();

        return response()->json([
            'empresa' => [
                'id' => $empresa->id,
                'nome' => $empresa->nome,
                'cnpj' => $empresa->cnpj,
                'ativo' => $empresa->ativo,
            ],
            'ocorrencias' => [
                'total' => $ocorrencias->count(),
                'ultimos_30_dias' => $ocorrencias->where('created_at', '>=', now()->subDays(30))->count(),
                'ultimos_7_dias' => $ocorrencias->where('created_at', '>=', now()->subDays(7))->count(),
                'score_medio' => $ocorrencias->avg('score_confianca'),
                'por_tipo' => $ocorrencias->selectRaw('tipo_match, COUNT(*) as total')
                    ->groupBy('tipo_match')
                    ->pluck('total', 'tipo_match'),
                'notificacoes' => [
                    'email_enviados' => $ocorrencias->where('notificado_email', true)->count(),
                    'whatsapp_enviados' => $ocorrencias->where('notificado_whatsapp', true)->count(),
                ],
            ],
        ]);
    }

    /**
     * Teste de busca em tempo real
     */
    public function testSearch(Request $request, Empresa $empresa): JsonResponse
    {
        $request->validate([
            'texto' => 'required|string|min:10',
        ]);

        try {
            $resultados = $this->searchService->buscarEmpresaNoTexto(
                texto: $request->texto,
                empresas: collect([$empresa])
            );

            $resultado = $resultados->first();

            return response()->json([
                'empresa' => new EmpresaResource($empresa),
                'resultado' => $resultado ? [
                    'encontrado' => true,
                    'score' => $resultado['score'],
                    'tipo_match' => $resultado['tipo_match'],
                    'contexto' => $resultado['contexto'],
                    'termo_encontrado' => $resultado['termo_encontrado'],
                ] : [
                    'encontrado' => false,
                ],
                'texto_teste' => substr($request->texto, 0, 200) . '...',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro no teste de busca.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function getIndexStats(): array
    {
        return [
            'total' => Empresa::count(),
            'ativas' => Empresa::where('ativo', true)->count(),
            'inativas' => Empresa::where('ativo', false)->count(),
            'por_categoria' => Empresa::selectRaw('categoria, COUNT(*) as total')
                ->groupBy('categoria')
                ->pluck('total', 'categoria')
                ->toArray(),
            'por_estado' => Empresa::selectRaw('estado, COUNT(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado')
                ->toArray(),
        ];
    }
}
