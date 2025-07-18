<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiarioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome_arquivo' => $this->nome_arquivo,
            'caminho_arquivo' => $this->caminho_arquivo,
            'estado' => $this->estado,
            'data_diario' => $this->data_diario->toDateString(),
            'status' => $this->status,
            'tamanho_arquivo' => $this->tamanho_arquivo,
            'total_paginas' => $this->total_paginas,
            'tentativas' => $this->tentativas,
            'erro_mensagem' => $this->erro_mensagem,
            'observacoes' => $this->observacoes,
            'hash_arquivo' => $this->hash_arquivo,
            'processado_em' => $this->processado_em?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Informações do usuário que fez upload
            'usuario' => $this->whenLoaded('usuario', function () {
                return [
                    'id' => $this->usuario->id,
                    'name' => $this->usuario->name,
                    'email' => $this->usuario->email,
                ];
            }),
            
            // Estatísticas de processamento
            'stats' => $this->when($request->get('include_stats'), function () {
                return [
                    'total_ocorrencias' => $this->ocorrencias()->count(),
                    'empresas_encontradas' => $this->ocorrencias()->distinct('empresa_id')->count('empresa_id'),
                    'score_medio' => round($this->ocorrencias()->avg('score_confianca'), 2),
                    'tempo_processamento' => $this->processado_em && $this->created_at 
                        ? $this->created_at->diffInSeconds($this->processado_em) 
                        : null,
                    'notificacoes_enviadas' => [
                        'email' => $this->ocorrencias()->where('notificado_email', true)->count(),
                        'whatsapp' => $this->ocorrencias()->where('notificado_whatsapp', true)->count(),
                    ],
                ];
            }),
            
            // Ocorrências encontradas
            'ocorrencias' => $this->whenLoaded('ocorrencias', function () {
                return OcorrenciaResource::collection($this->ocorrencias);
            }),
            
            // Metadados do arquivo
            'meta' => $this->when($request->get('include_meta'), function () {
                return [
                    'tamanho_formatado' => $this->formatFileSize($this->tamanho_arquivo),
                    'pode_ser_reprocessado' => $this->podeSerReprocessado(),
                    'tem_texto_extraido' => !empty($this->texto_extraido),
                    'status_descricao' => $this->getStatusDescription(),
                    'progresso_percent' => $this->getProgressPercent(),
                ];
            }),
            
            // Texto extraído (apenas se solicitado - pode ser muito grande)
            'texto_extraido' => $this->when(
                $request->get('include_text') && !empty($this->texto_extraido),
                function () {
                    return $this->texto_extraido;
                }
            ),
        ];
    }

    /**
     * Formatar tamanho do arquivo
     */
    private function formatFileSize(?int $bytes): ?string
    {
        if (!$bytes) return null;

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Obter descrição do status
     */
    private function getStatusDescription(): string
    {
        return match($this->status) {
            'pendente' => 'Aguardando processamento',
            'processando' => 'Processando arquivo',
            'concluido' => 'Processamento concluído',
            'erro' => 'Erro no processamento',
            default => 'Status desconhecido'
        };
    }

    /**
     * Calcular porcentagem de progresso
     */
    private function getProgressPercent(): int
    {
        return match($this->status) {
            'pendente' => 0,
            'processando' => 50,
            'concluido' => 100,
            'erro' => 0,
            default => 0
        };
    }
}
