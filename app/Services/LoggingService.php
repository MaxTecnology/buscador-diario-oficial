<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LoggingService
{
    const NIVEL_DEBUG = 'debug';
    const NIVEL_INFO = 'info';
    const NIVEL_WARNING = 'warning';
    const NIVEL_ERROR = 'error';
    const NIVEL_CRITICAL = 'critical';

    const CONTEXTO_PROCESSAMENTO = 'processamento';
    const CONTEXTO_NOTIFICACAO = 'notificacao';
    const CONTEXTO_USUARIO = 'usuario';
    const CONTEXTO_SISTEMA = 'sistema';
    const CONTEXTO_SEGURANCA = 'seguranca';
    const CONTEXTO_PERFORMANCE = 'performance';

    /**
     * Log detalhado de processamento de PDF
     */
    public function logProcessamentoPdf(string $nivel, string $mensagem, array $contexto = []): void
    {
        $contextoCompleto = array_merge([
            'contexto' => self::CONTEXTO_PROCESSAMENTO,
            'usuario_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
            'ip' => request()->ip(),
            'session_id' => session()->getId(),
        ], $contexto);

        Log::channel('daily')->log($nivel, "[PDF] {$mensagem}", $contextoCompleto);
        
        // Log críticos também vão para auditoria
        if (in_array($nivel, [self::NIVEL_ERROR, self::NIVEL_CRITICAL])) {
            Log::channel('audit')->log($nivel, "[PDF] {$mensagem}", $contextoCompleto);
        }
    }

    /**
     * Log detalhado de notificações
     */
    public function logNotificacao(string $nivel, string $mensagem, array $contexto = []): void
    {
        $contextoCompleto = array_merge([
            'contexto' => self::CONTEXTO_NOTIFICACAO,
            'usuario_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ], $contexto);

        Log::channel('daily')->log($nivel, "[NOTIF] {$mensagem}", $contextoCompleto);
        
        // Salvar estatísticas de notificações no cache
        $this->updateNotificationStats($nivel, $contexto);
    }

    /**
     * Log de ações de usuário
     */
    public function logAcaoUsuario(string $acao, array $contexto = []): void
    {
        $contextoCompleto = array_merge([
            'contexto' => self::CONTEXTO_USUARIO,
            'usuario_id' => auth()->id(),
            'usuario_email' => auth()->user()?->email,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ], $contexto);

        Log::channel('audit')->info("[USER] {$acao}", $contextoCompleto);
    }

    /**
     * Log de performance
     */
    public function logPerformance(string $operacao, float $tempoMs, array $contexto = []): void
    {
        $nivel = $this->determinarNivelPerformance($tempoMs);
        
        $contextoCompleto = array_merge([
            'contexto' => self::CONTEXTO_PERFORMANCE,
            'operacao' => $operacao,
            'tempo_ms' => $tempoMs,
            'tempo_segundos' => round($tempoMs / 1000, 2),
            'usuario_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ], $contexto);

        Log::channel('daily')->log($nivel, "[PERF] {$operacao} executada em {$tempoMs}ms", $contextoCompleto);
        
        // Salvar métricas no cache
        $this->updatePerformanceStats($operacao, $tempoMs);
    }

    /**
     * Log de segurança
     */
    public function logSeguranca(string $evento, string $nivel = self::NIVEL_WARNING, array $contexto = []): void
    {
        $contextoCompleto = array_merge([
            'contexto' => self::CONTEXTO_SEGURANCA,
            'usuario_id' => auth()->id(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ], $contexto);

        Log::channel('audit')->log($nivel, "[SEC] {$evento}", $contextoCompleto);
    }

    /**
     * Log de sistema
     */
    public function logSistema(string $nivel, string $mensagem, array $contexto = []): void
    {
        $contextoCompleto = array_merge([
            'contexto' => self::CONTEXTO_SISTEMA,
            'timestamp' => now()->toISOString(),
        ], $contexto);

        Log::channel('daily')->log($nivel, "[SYS] {$mensagem}", $contextoCompleto);
        
        if (in_array($nivel, [self::NIVEL_ERROR, self::NIVEL_CRITICAL])) {
            Log::channel('audit')->log($nivel, "[SYS] {$mensagem}", $contextoCompleto);
        }
    }

    /**
     * Obter estatísticas de logs
     */
    public function getEstatisticasLogs(): array
    {
        return [
            'notificacoes' => Cache::get('stats_notificacoes', []),
            'performance' => Cache::get('stats_performance', []),
            'erros_hoje' => $this->contarErrosHoje(),
            'usuarios_ativos' => $this->contarUsuariosAtivos(),
        ];
    }

    /**
     * Limpar logs antigos
     */
    public function limparLogsAntigos(int $diasManter = 30): int
    {
        $caminhoLogs = storage_path('logs');
        $arquivosRemovidos = 0;
        $dataLimite = now()->subDays($diasManter);

        $arquivos = glob($caminhoLogs . '/laravel-*.log');
        
        foreach ($arquivos as $arquivo) {
            $dataArquivo = \Carbon\Carbon::createFromTimestamp(filemtime($arquivo));
            
            if ($dataArquivo->lt($dataLimite)) {
                if (unlink($arquivo)) {
                    $arquivosRemovidos++;
                }
            }
        }

        $this->logSistema(self::NIVEL_INFO, "Limpeza de logs executada", [
            'arquivos_removidos' => $arquivosRemovidos,
            'dias_mantidos' => $diasManter
        ]);

        return $arquivosRemovidos;
    }

    /**
     * Determinar nível de performance baseado no tempo
     */
    private function determinarNivelPerformance(float $tempoMs): string
    {
        if ($tempoMs > 10000) return self::NIVEL_CRITICAL; // > 10s
        if ($tempoMs > 5000) return self::NIVEL_ERROR;     // > 5s
        if ($tempoMs > 2000) return self::NIVEL_WARNING;   // > 2s
        if ($tempoMs > 1000) return self::NIVEL_INFO;      // > 1s
        return self::NIVEL_DEBUG;                          // <= 1s
    }

    /**
     * Atualizar estatísticas de notificações
     */
    private function updateNotificationStats(string $nivel, array $contexto): void
    {
        $key = 'stats_notificacoes';
        $stats = Cache::get($key, []);
        
        $hoje = now()->format('Y-m-d');
        $tipo = $contexto['tipo'] ?? 'indefinido';
        
        if (!isset($stats[$hoje])) {
            $stats[$hoje] = [];
        }
        
        if (!isset($stats[$hoje][$tipo])) {
            $stats[$hoje][$tipo] = [
                'total' => 0,
                'sucesso' => 0,
                'erro' => 0,
            ];
        }
        
        $stats[$hoje][$tipo]['total']++;
        
        if ($nivel === self::NIVEL_ERROR) {
            $stats[$hoje][$tipo]['erro']++;
        } else {
            $stats[$hoje][$tipo]['sucesso']++;
        }
        
        // Manter apenas os últimos 30 dias
        $stats = array_slice($stats, -30, 30, true);
        
        Cache::put($key, $stats, now()->addDays(31));
    }

    /**
     * Atualizar estatísticas de performance
     */
    private function updatePerformanceStats(string $operacao, float $tempoMs): void
    {
        $key = 'stats_performance';
        $stats = Cache::get($key, []);
        
        $hoje = now()->format('Y-m-d');
        
        if (!isset($stats[$hoje])) {
            $stats[$hoje] = [];
        }
        
        if (!isset($stats[$hoje][$operacao])) {
            $stats[$hoje][$operacao] = [
                'execucoes' => 0,
                'tempo_total' => 0,
                'tempo_max' => 0,
                'tempo_min' => PHP_FLOAT_MAX,
            ];
        }
        
        $stats[$hoje][$operacao]['execucoes']++;
        $stats[$hoje][$operacao]['tempo_total'] += $tempoMs;
        $stats[$hoje][$operacao]['tempo_max'] = max($stats[$hoje][$operacao]['tempo_max'], $tempoMs);
        $stats[$hoje][$operacao]['tempo_min'] = min($stats[$hoje][$operacao]['tempo_min'], $tempoMs);
        
        // Manter apenas os últimos 30 dias
        $stats = array_slice($stats, -30, 30, true);
        
        Cache::put($key, $stats, now()->addDays(31));
    }

    /**
     * Contar erros de hoje
     */
    private function contarErrosHoje(): int
    {
        $arquivoLog = storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log');
        
        if (!file_exists($arquivoLog)) {
            return 0;
        }
        
        $conteudo = file_get_contents($arquivoLog);
        return substr_count($conteudo, '.ERROR:') + substr_count($conteudo, '.CRITICAL:');
    }

    /**
     * Contar usuários ativos nas últimas 24h
     */
    private function contarUsuariosAtivos(): int
    {
        $arquivoAudit = storage_path('logs/audit-' . now()->format('Y-m-d') . '.log');
        
        if (!file_exists($arquivoAudit)) {
            return 0;
        }
        
        $conteudo = file_get_contents($arquivoAudit);
        $usuarios = [];
        
        // Extrair user_ids únicos do log
        preg_match_all('/"user_id":(\d+)/', $conteudo, $matches);
        
        if (!empty($matches[1])) {
            $usuarios = array_unique($matches[1]);
        }
        
        return count($usuarios);
    }
}