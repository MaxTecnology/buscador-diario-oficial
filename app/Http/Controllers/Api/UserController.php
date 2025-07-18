<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\User\CreateUserRequest;
use App\Http\Requests\Api\User\UpdateUserRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\Empresa;
use App\Models\User;
use App\Models\UserEmpresaPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Listar usuários com filtros e paginação
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'empresas']);

        // Filtros
        if ($request->filled('nome')) {
            $query->where('name', 'like', '%' . $request->nome . '%');
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->filled('ativo')) {
            $query->where('pode_fazer_login', $request->boolean('ativo'));
        }

        // Ordenação
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginação
        $perPage = min($request->get('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'stats' => $this->getIndexStats(),
        ]);
    }

    /**
     * Criar novo usuário
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'telefone' => $request->telefone,
                'pode_fazer_login' => $request->boolean('pode_fazer_login', true),
                'created_by' => $request->user()->id,
            ]);

            // Atribuir role
            if ($request->filled('role')) {
                $user->assignRole($request->role);
            }

            // Vincular empresas se fornecidas
            if ($request->filled('empresas')) {
                foreach ($request->empresas as $empresaData) {
                    UserEmpresaPermission::create([
                        'user_id' => $user->id,
                        'empresa_id' => $empresaData['empresa_id'],
                        'pode_ver_ocorrencias' => $empresaData['pode_ver_ocorrencias'] ?? true,
                        'pode_receber_notificacoes' => $empresaData['pode_receber_notificacoes'] ?? true,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Usuário criado com sucesso.',
                'data' => new UserResource($user->fresh(['roles', 'empresas'])),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar usuário.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Detalhes de um usuário
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'empresas', 'createdBy']);

        return response()->json([
            'data' => new UserResource($user),
            'stats' => [
                'diarios_enviados' => $user->diariosEnviados()->count(),
                'empresas_vinculadas' => $user->empresas()->count(),
                'ultimo_login' => $user->last_login_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Atualizar usuário
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $data = $request->validated();

            // Hash da senha se fornecida
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            // Atualizar role se fornecida
            if ($request->filled('role')) {
                $user->syncRoles([$request->role]);
            }

            return response()->json([
                'message' => 'Usuário atualizado com sucesso.',
                'data' => new UserResource($user->fresh(['roles', 'empresas'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar usuário.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Deletar usuário
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            // Verificar se não é o último admin
            if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
                return response()->json([
                    'message' => 'Não é possível deletar o último administrador.',
                ], 422);
            }

            $user->delete();

            return response()->json([
                'message' => 'Usuário deletado com sucesso.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao deletar usuário.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Vincular empresa ao usuário
     */
    public function attachEmpresa(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'pode_ver_ocorrencias' => 'boolean',
            'pode_receber_notificacoes' => 'boolean',
        ]);

        try {
            $empresa = Empresa::findOrFail($request->empresa_id);

            // Verificar se já não está vinculado
            if ($user->empresas()->where('empresa_id', $empresa->id)->exists()) {
                return response()->json([
                    'message' => 'Usuário já está vinculado a esta empresa.',
                ], 422);
            }

            UserEmpresaPermission::create([
                'user_id' => $user->id,
                'empresa_id' => $empresa->id,
                'pode_ver_ocorrencias' => $request->boolean('pode_ver_ocorrencias', true),
                'pode_receber_notificacoes' => $request->boolean('pode_receber_notificacoes', true),
            ]);

            return response()->json([
                'message' => 'Empresa vinculada ao usuário com sucesso.',
                'data' => new UserResource($user->fresh(['roles', 'empresas'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao vincular empresa.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Desvincular empresa do usuário
     */
    public function detachEmpresa(User $user, Empresa $empresa): JsonResponse
    {
        try {
            $permission = UserEmpresaPermission::where('user_id', $user->id)
                ->where('empresa_id', $empresa->id)
                ->first();

            if (!$permission) {
                return response()->json([
                    'message' => 'Usuário não está vinculado a esta empresa.',
                ], 404);
            }

            $permission->delete();

            return response()->json([
                'message' => 'Empresa desvinculada do usuário com sucesso.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao desvincular empresa.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Atualizar permissões da empresa para o usuário
     */
    public function updateEmpresaPermissions(Request $request, User $user, Empresa $empresa): JsonResponse
    {
        $request->validate([
            'pode_ver_ocorrencias' => 'required|boolean',
            'pode_receber_notificacoes' => 'required|boolean',
        ]);

        try {
            $permission = UserEmpresaPermission::where('user_id', $user->id)
                ->where('empresa_id', $empresa->id)
                ->first();

            if (!$permission) {
                return response()->json([
                    'message' => 'Usuário não está vinculado a esta empresa.',
                ], 404);
            }

            $permission->update([
                'pode_ver_ocorrencias' => $request->boolean('pode_ver_ocorrencias'),
                'pode_receber_notificacoes' => $request->boolean('pode_receber_notificacoes'),
            ]);

            return response()->json([
                'message' => 'Permissões atualizadas com sucesso.',
                'data' => [
                    'user' => new UserResource($user->fresh(['roles', 'empresas'])),
                    'permissions' => $permission->fresh(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar permissões.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function getIndexStats(): array
    {
        return [
            'total' => User::count(),
            'ativos' => User::where('pode_fazer_login', true)->count(),
            'inativos' => User::where('pode_fazer_login', false)->count(),
            'por_role' => Role::withCount('users')->get()->pluck('users_count', 'name')->toArray(),
        ];
    }
}
