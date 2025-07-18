<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'pode_fazer_login' => $this->pode_fazer_login,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Roles e permissões
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'primary_role' => $this->whenLoaded('roles', function () {
                return $this->roles->first()?->name;
            }),
            
            // Empresas vinculadas
            'empresas' => $this->whenLoaded('empresas', function () {
                return $this->empresas->map(function ($empresa) {
                    $permission = $empresa->pivot;
                    return [
                        'id' => $empresa->id,
                        'nome' => $empresa->nome,
                        'cnpj' => $empresa->cnpj,
                        'estado' => $empresa->estado,
                        'ativo' => $empresa->ativo,
                        'permissions' => [
                            'pode_ver_ocorrencias' => $permission->pode_ver_ocorrencias,
                            'pode_receber_notificacoes' => $permission->pode_receber_notificacoes,
                        ],
                    ];
                });
            }),
            
            // Usuário que criou
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),
            
            // Estatísticas básicas (apenas quando solicitado)
            'stats' => $this->when($request->get('include_stats'), function () {
                return [
                    'diarios_enviados' => $this->diariosEnviados()->count(),
                    'empresas_vinculadas' => $this->empresas()->count(),
                    'ultimo_acesso' => $this->last_login_at?->toISOString(),
                ];
            }),
        ];
    }
}
