<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = auth()->user();

        // Verificar se o usuário pode fazer login
        if (!$user->pode_fazer_login) {
            return response()->json(['message' => 'Usuário não autorizado a fazer login'], 403);
        }

        // Verificar se tem alguma das roles necessárias
        if (!empty($roles) && !$user->hasAnyRole($roles)) {
            return response()->json(['message' => 'Acesso negado. Permissão insuficiente.'], 403);
        }

        return $next($request);
    }
}
