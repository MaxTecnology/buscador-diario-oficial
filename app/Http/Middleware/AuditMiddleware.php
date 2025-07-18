<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Interceptar dados sensíveis antes do log
        $sanitizedData = $this->sanitizeData($request->all());

        $response = $next($request);

        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);

        // Log apenas para rotas importantes
        if ($this->shouldAudit($request)) {
            Log::channel('audit')->info('Request auditada', [
                'user_id' => auth()->id(),
                'user_email' => auth()->user()?->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route' => $request->route()?->getName(),
                'parameters' => $sanitizedData,
                'response_status' => $response->getStatusCode(),
                'response_time_ms' => $responseTime,
                'timestamp' => now()->toISOString(),
            ]);
        }

        return $response;
    }

    private function shouldAudit(Request $request): bool
    {
        $auditRoutes = [
            'api/users',
            'api/empresas', 
            'api/diarios',
            'api/ocorrencias',
            'api/configs',
            'admin/',
        ];

        $excludeRoutes = [
            'api/dashboard/metrics',
            'api/me',
            'livewire/',
            '_debugbar/',
        ];

        $path = $request->path();

        // Excluir rotas específicas
        foreach ($excludeRoutes as $excludeRoute) {
            if (str_contains($path, $excludeRoute)) {
                return false;
            }
        }

        // Incluir rotas importantes
        foreach ($auditRoutes as $auditRoute) {
            if (str_contains($path, $auditRoute)) {
                return true;
            }
        }

        // Auditar métodos de modificação
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    private function sanitizeData(array $data): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key', 'secret'];
        
        return collect($data)->map(function ($value, $key) use ($sensitiveFields) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                return '[FILTERED]';
            }
            
            if (is_array($value)) {
                return $this->sanitizeData($value);
            }
            
            return $value;
        })->toArray();
    }
}
