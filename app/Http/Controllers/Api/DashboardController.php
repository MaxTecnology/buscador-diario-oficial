<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Diario;
use App\Models\Empresa;
use App\Models\Ocorrencia;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    /**
     * Métricas principais do dashboard
     */
    public function metrics(): JsonResponse
    {
        $metrics = Cache::remember('dashboard_metrics', 300, function () {
            return [
                'diarios' => [
                    'total' => Diario::count(),
                    'pendentes' => Diario::pendentes()->count(),
                    'processando' => Diario::processando()->count(),
                    'concluidos' => Diario::concluidos()->count(),
                    'com_erro' => Diario::comErro()->count(),
                    'hoje' => Diario::whereDate('created_at', today())->count(),
                ],
                'empresas' => [
                    'total' => Empresa::count(),
                    'ativas' => Empresa::where('ativo', true)->count(),
                    'inativas' => Empresa::where('ativo', false)->count(),
                ],
                'ocorrencias' => [
                    'total' => Ocorrencia::count(),
                    'hoje' => Ocorrencia::whereDate('created_at', today())->count(),
                    'esta_semana' => Ocorrencia::where('created_at', '>=', now()->startOfWeek())->count(),
                    'este_mes' => Ocorrencia::where('created_at', '>=', now()->startOfMonth())->count(),
                    'score_medio' => round(Ocorrencia::avg('score_confianca'), 2),
                ],
                'usuarios' => [
                    'total' => User::count(),
                    'ativos' => User::where('pode_fazer_login', true)->count(),
                    'conectados_hoje' => User::whereDate('last_login_at', today())->count(),
                ],
                'notificacoes' => [
                    'emails_enviados' => Ocorrencia::where('notificado_email', true)->count(),
                    'whatsapp_enviados' => Ocorrencia::where('notificado_whatsapp', true)->count(),
                    'pendentes_email' => Ocorrencia::where('notificado_email', false)->count(),
                    'pendentes_whatsapp' => Ocorrencia::where('notificado_whatsapp', false)->count(),
                ],
            ];
        });

        return response()->json($metrics);
    }

    /**
     * Estatísticas detalhadas
     */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('dashboard_stats', 600, function () {
            return [
                'processamento' => [
                    'taxa_sucesso' => $this->calculateSuccessRate(),
                    'tempo_medio_processamento' => $this->getAverageProcessingTime(),
                    'arquivos_por_dia' => $this->getFilesPerDay(),
                ],
                'ocorrencias_por_tipo' => Ocorrencia::selectRaw('tipo_match, COUNT(*) as total')
                    ->groupBy('tipo_match')
                    ->pluck('total', 'tipo_match')
                    ->toArray(),
                'ocorrencias_por_score' => [
                    'alto' => Ocorrencia::where('score_confianca', '>=', 90)->count(),
                    'medio' => Ocorrencia::whereBetween('score_confianca', [70, 89])->count(),
                    'baixo' => Ocorrencia::where('score_confianca', '<', 70)->count(),
                ],
                'empresas_mais_encontradas' => $this->getTopCompanies(),
                'estados_mais_ativos' => $this->getTopStates(),
                'timeline_ocorrencias' => $this->getOccurrenceTimeline(),
            ];
        });

        return response()->json($stats);
    }

    /**
     * Atividade recente do sistema
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 20), 100);
        
        $activities = Activity::with('causer')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Relatório de compliance
     */
    public function compliance(): JsonResponse
    {
        $compliance = [
            'auditoria' => [
                'total_logs' => Activity::count(),
                'logs_hoje' => Activity::whereDate('created_at', today())->count(),
                'usuarios_ativos' => Activity::whereDate('created_at', today())
                    ->distinct('causer_id')
                    ->count('causer_id'),
            ],
            'processamento' => [
                'arquivos_processados' => Diario::concluidos()->count(),
                'arquivos_com_erro' => Diario::comErro()->count(),
                'taxa_erro' => $this->calculateErrorRate(),
            ],
            'notificacoes' => [
                'taxa_entrega_email' => $this->getEmailDeliveryRate(),
                'taxa_entrega_whatsapp' => $this->getWhatsappDeliveryRate(),
            ],
            'integridade_dados' => [
                'diarios_sem_texto' => Diario::whereNull('texto_extraido')->count(),
                'empresas_sem_cnpj' => Empresa::whereNull('cnpj')->count(),
                'ocorrencias_sem_contexto' => Ocorrencia::whereNull('contexto')->count(),
            ],
        ];

        return response()->json($compliance);
    }

    /**
     * Relatório de atividades por usuário
     */
    public function atividades(Request $request): JsonResponse
    {
        $request->validate([
            'data_inicio' => 'date',
            'data_fim' => 'date|after_or_equal:data_inicio',
            'usuario_id' => 'exists:users,id',
        ]);

        $query = Activity::with('causer');

        if ($request->filled('data_inicio')) {
            $query->where('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->where('created_at', '<=', $request->data_fim);
        }

        if ($request->filled('usuario_id')) {
            $query->where('causer_id', $request->usuario_id);
        }

        $atividades = $query->selectRaw('causer_id, description, COUNT(*) as total')
            ->groupBy(['causer_id', 'description'])
            ->orderBy('total', 'desc')
            ->get();

        return response()->json([
            'data' => $atividades,
            'resumo' => [
                'total_atividades' => $atividades->sum('total'),
                'usuarios_unicos' => $atividades->pluck('causer_id')->unique()->count(),
                'tipos_atividade' => $atividades->pluck('description')->unique()->count(),
            ],
        ]);
    }

    /**
     * Relatório de performance
     */
    public function performance(): JsonResponse
    {
        $performance = [
            'processamento' => [
                'tempo_medio_total' => $this->getAverageProcessingTime(),
                'tempo_por_pagina' => $this->getProcessingTimePerPage(),
                'throughput_diario' => $this->getDailyThroughput(),
            ],
            'memoria' => [
                'uso_medio_memoria' => $this->getAverageMemoryUsage(),
                'picos_memoria' => $this->getMemoryPeaks(),
            ],
            'queue' => [
                'jobs_processados' => $this->getProcessedJobs(),
                'jobs_falharam' => $this->getFailedJobs(),
                'tempo_espera_medio' => $this->getAverageQueueTime(),
            ],
        ];

        return response()->json($performance);
    }

    /**
     * Resumo de empresas
     */
    public function empresasResumo(): JsonResponse
    {
        $resumo = [
            'distribuicao_por_estado' => Empresa::selectRaw('estado, COUNT(*) as total')
                ->groupBy('estado')
                ->orderBy('total', 'desc')
                ->pluck('total', 'estado')
                ->toArray(),
            'distribuicao_por_categoria' => Empresa::selectRaw('categoria, COUNT(*) as total')
                ->groupBy('categoria')
                ->orderBy('total', 'desc')
                ->pluck('total', 'categoria')
                ->toArray(),
            'mais_encontradas' => $this->getTopCompanies(20),
            'sem_ocorrencias' => Empresa::whereDoesntHave('ocorrencias')->count(),
            'com_mais_ocorrencias' => Empresa::withCount('ocorrencias')
                ->orderBy('ocorrencias_count', 'desc')
                ->limit(10)
                ->get(['id', 'nome', 'cnpj', 'ocorrencias_count']),
        ];

        return response()->json($resumo);
    }

    /**
     * Resumo de diários
     */
    public function diariosResumo(): JsonResponse
    {
        $resumo = [
            'por_estado' => Diario::selectRaw('estado, COUNT(*) as total')
                ->groupBy('estado')
                ->orderBy('total', 'desc')
                ->pluck('total', 'estado')
                ->toArray(),
            'por_status' => Diario::selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray(),
            'tamanho_medio' => round(Diario::avg('tamanho_arquivo') / 1024 / 1024, 2), // MB
            'paginas_media' => round(Diario::avg('total_paginas'), 1),
            'maiores_arquivos' => Diario::orderBy('tamanho_arquivo', 'desc')
                ->limit(10)
                ->get(['id', 'nome_arquivo', 'estado', 'tamanho_arquivo', 'total_paginas']),
            'processamento_por_dia' => Diario::selectRaw('DATE(created_at) as data, COUNT(*) as total')
                ->groupBy('data')
                ->orderBy('data', 'desc')
                ->limit(30)
                ->pluck('total', 'data')
                ->toArray(),
        ];

        return response()->json($resumo);
    }

    /**
     * Configurações do sistema
     */
    public function configs(): JsonResponse
    {
        $configs = SystemConfig::all()->keyBy('chave');

        return response()->json($configs);
    }

    /**
     * Atualizar múltiplas configurações
     */
    public function updateConfigs(Request $request): JsonResponse
    {
        $request->validate([
            'configs' => 'required|array',
            'configs.*.chave' => 'required|string',
            'configs.*.valor' => 'required',
        ]);

        foreach ($request->configs as $configData) {
            SystemConfig::updateOrCreate(
                ['chave' => $configData['chave']],
                ['valor' => $configData['valor']]
            );
        }

        // Limpar cache das configurações
        Cache::forget('system_configs');

        return response()->json([
            'message' => 'Configurações atualizadas com sucesso.',
        ]);
    }

    /**
     * Obter configuração específica
     */
    public function getConfig(string $chave): JsonResponse
    {
        $config = SystemConfig::where('chave', $chave)->first();

        if (!$config) {
            return response()->json([
                'message' => 'Configuração não encontrada.',
            ], 404);
        }

        return response()->json($config);
    }

    /**
     * Definir configuração específica
     */
    public function setConfig(Request $request, string $chave): JsonResponse
    {
        $request->validate([
            'valor' => 'required',
        ]);

        $config = SystemConfig::updateOrCreate(
            ['chave' => $chave],
            ['valor' => $request->valor]
        );

        // Limpar cache das configurações
        Cache::forget('system_configs');

        return response()->json([
            'message' => 'Configuração atualizada com sucesso.',
            'data' => $config,
        ]);
    }

    /**
     * Health check do sistema
     */
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = collect($health)->except(['status', 'timestamp'])
            ->every(fn($check) => $check['status'] === 'ok');

        $health['status'] = $allHealthy ? 'ok' : 'warning';

        return response()->json($health);
    }

    /**
     * Status das filas
     */
    public function queues(): JsonResponse
    {
        // Implementação simples - pode ser expandida com Laravel Horizon
        return response()->json([
            'queues' => [
                'pdf-processing' => [
                    'pending' => 0, // Implementar com Redis
                    'processing' => 0,
                    'failed' => 0,
                ],
                'notifications' => [
                    'pending' => 0,
                    'processing' => 0,
                    'failed' => 0,
                ],
            ],
        ]);
    }

    /**
     * Logs do sistema
     */
    public function logs(Request $request): JsonResponse
    {
        $request->validate([
            'nivel' => 'in:emergency,alert,critical,error,warning,notice,info,debug',
            'limite' => 'integer|min:1|max:1000',
        ]);

        // Implementação simples - seria melhor usar um package específico
        return response()->json([
            'message' => 'Funcionalidade de logs em desenvolvimento.',
            'logs' => [],
        ]);
    }

    // Métodos privados auxiliares

    private function calculateSuccessRate(): float
    {
        $total = Diario::count();
        $sucessos = Diario::concluidos()->count();
        
        return $total > 0 ? round(($sucessos / $total) * 100, 2) : 0;
    }

    private function calculateErrorRate(): float
    {
        $total = Diario::count();
        $erros = Diario::comErro()->count();
        
        return $total > 0 ? round(($erros / $total) * 100, 2) : 0;
    }

    private function getAverageProcessingTime(): float
    {
        return round(Diario::concluidos()
            ->whereNotNull('processado_em')
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, created_at, processado_em)')), 2);
    }

    private function getProcessingTimePerPage(): float
    {
        $avgTime = $this->getAverageProcessingTime();
        $avgPages = Diario::concluidos()->avg('total_paginas');
        
        return $avgPages > 0 ? round($avgTime / $avgPages, 2) : 0;
    }

    private function getDailyThroughput(): array
    {
        return Diario::selectRaw('DATE(created_at) as data, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('data')
            ->orderBy('data')
            ->pluck('total', 'data')
            ->toArray();
    }

    private function getTopCompanies(int $limit = 10): array
    {
        return Empresa::withCount('ocorrencias')
            ->orderBy('ocorrencias_count', 'desc')
            ->limit($limit)
            ->get(['id', 'nome', 'cnpj', 'ocorrencias_count'])
            ->toArray();
    }

    private function getTopStates(): array
    {
        return Diario::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->orderBy('total', 'desc')
            ->pluck('total', 'estado')
            ->toArray();
    }

    private function getOccurrenceTimeline(): array
    {
        return Ocorrencia::selectRaw('DATE(created_at) as data, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('data')
            ->orderBy('data')
            ->pluck('total', 'data')
            ->toArray();
    }

    private function getFilesPerDay(): array
    {
        return Diario::selectRaw('DATE(created_at) as data, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('data')
            ->orderBy('data')
            ->pluck('total', 'data')
            ->toArray();
    }

    private function getEmailDeliveryRate(): float
    {
        $total = Ocorrencia::count();
        $enviados = Ocorrencia::where('notificado_email', true)->count();
        
        return $total > 0 ? round(($enviados / $total) * 100, 2) : 0;
    }

    private function getWhatsappDeliveryRate(): float
    {
        $total = Ocorrencia::count();
        $enviados = Ocorrencia::where('notificado_whatsapp', true)->count();
        
        return $total > 0 ? round(($enviados / $total) * 100, 2) : 0;
    }

    private function getAverageMemoryUsage(): int
    {
        // Implementação placeholder - seria integrado com monitoramento
        return 128; // MB
    }

    private function getMemoryPeaks(): array
    {
        // Implementação placeholder
        return [];
    }

    private function getProcessedJobs(): int
    {
        // Implementação placeholder - seria integrado com Laravel Horizon
        return 0;
    }

    private function getFailedJobs(): int
    {
        // Implementação placeholder
        return 0;
    }

    private function getAverageQueueTime(): float
    {
        // Implementação placeholder
        return 0.0;
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $result = Cache::get('health_check');
            return ['status' => $result === 'ok' ? 'ok' : 'error', 'message' => 'Cache working'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = storage_path('app/health_check.txt');
            file_put_contents($path, 'ok');
            $result = file_get_contents($path);
            unlink($path);
            return ['status' => 'ok', 'message' => 'Storage working'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        // Implementação placeholder - seria integrado com Laravel Horizon
        return ['status' => 'ok', 'message' => 'Queue status unknown'];
    }
}
