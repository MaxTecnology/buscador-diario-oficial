<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpresaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cnpj' => $this->cnpj,
            'razao_social' => $this->razao_social,
            'categoria' => $this->categoria,
            'estado' => $this->estado,
            'cidade' => $this->cidade,
            'email_notificacao' => $this->email_notificacao,
            'telefone_notificacao' => $this->telefone_notificacao,
            'webhook_url' => $this->webhook_url,
            'ativo' => $this->ativo,
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Campos JSON
            'termos_busca' => $this->termos_busca ?? [],
            'termos_busca_gerados' => $this->termos_busca_gerados ?? [],
            
            // Estatísticas básicas
            'stats' => $this->when($request->get('include_stats'), function () {
                return [
                    'total_ocorrencias' => $this->ocorrencias()->count(),
                    'ocorrencias_30_dias' => $this->ocorrencias()
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count(),
                    'ocorrencias_7_dias' => $this->ocorrencias()
                        ->where('created_at', '>=', now()->subDays(7))
                        ->count(),
                    'score_medio' => round($this->ocorrencias()->avg('score_confianca'), 2),
                    'usuarios_vinculados' => $this->users()->count(),
                    'ultima_ocorrencia' => $this->ocorrencias()->latest()->first()?->created_at?->toISOString(),
                ];
            }),
            
            // Relacionamentos carregados
            'ocorrencias' => $this->whenLoaded('ocorrencias', function () {
                return OcorrenciaResource::collection($this->ocorrencias);
            }),
            
            'users' => $this->whenLoaded('users', function () {
                return $this->users->map(function ($user) {
                    $permission = $user->pivot;
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'permissions' => [
                            'pode_ver_ocorrencias' => $permission->pode_ver_ocorrencias,
                            'pode_receber_notificacoes' => $permission->pode_receber_notificacoes,
                        ],
                    ];
                });
            }),
            
            // Informações adicionais para relatórios
            'meta' => $this->when($request->get('include_meta'), function () {
                return [
                    'cnpj_formatado' => $this->cnpj ? $this->formatCnpj($this->cnpj) : null,
                    'total_termos_busca' => count($this->termos_busca ?? []) + count($this->termos_busca_gerados ?? []),
                    'pode_receber_notificacoes' => !empty($this->email_notificacao) || !empty($this->telefone_notificacao),
                    'webhook_ativo' => !empty($this->webhook_url),
                ];
            }),
        ];
    }

    /**
     * Formatar CNPJ para exibição
     */
    private function formatCnpj(?string $cnpj): ?string
    {
        if (!$cnpj || strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }
}
