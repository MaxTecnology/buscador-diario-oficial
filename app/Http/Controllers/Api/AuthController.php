<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login e geração de tokens
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        // Verificar se usuário pode fazer login
        if (!$user->pode_fazer_login) {
            return response()->json([
                'message' => 'Usuário não autorizado a fazer login.',
            ], 403);
        }

        // Revogar tokens existentes se solicitado
        if ($request->revoke_existing_tokens) {
            $user->tokens()->delete();
        }

        $token = $user->createToken('api-token', ['*'], now()->addHours(24));

        activity()
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip(), 'user_agent' => $request->userAgent()])
            ->log('Login realizado via API');

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
        ]);
    }

    /**
     * Logout e revogação de token
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revogar token atual
        $request->user()->currentAccessToken()->delete();

        activity()
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('Logout realizado via API');

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    /**
     * Revogar todos os tokens
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revogar todos os tokens
        $user->tokens()->delete();

        activity()
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('Logout de todos os dispositivos via API');

        return response()->json([
            'message' => 'Logout realizado em todos os dispositivos.',
        ]);
    }

    /**
     * Refresh token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();

        // Criar novo token
        $newToken = $user->createToken('api-token', ['*'], now()->addHours(24));

        // Revogar token atual
        $currentToken->delete();

        activity()
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('Token renovado via API');

        return response()->json([
            'token' => $newToken->plainTextToken,
            'expires_at' => $newToken->accessToken->expires_at,
        ]);
    }

    /**
     * Dados do usuário autenticado
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'empresas']);

        return response()->json([
            'user' => new UserResource($user),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * Verificar validade do token
     */
    public function verify(Request $request): JsonResponse
    {
        return response()->json([
            'valid' => true,
            'user' => new UserResource($request->user()),
            'expires_at' => $request->user()->currentAccessToken()->expires_at,
        ]);
    }
}
