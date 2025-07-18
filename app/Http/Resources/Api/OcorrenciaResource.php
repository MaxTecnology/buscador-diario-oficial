<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OcorrenciaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_match' => $this->tipo_match,
            'score_confianca' => $this->score_confianca,
            'termo_encontrado' => $this->termo_encontrado,
            'contexto' => $this->contexto,
            'notificado_email' => $this->notificado_email,
            'notificado_whatsapp' => $this->notificado_whatsapp,
            'notificado_em' => $this->notificado_em?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Empresa relacionada
            'empresa' => $this->whenLoaded('empresa', function () {
                return [
                    'id' => $this->empresa->id,
                    'nome' => $this->empresa->nome,
                    'cnpj' => $this->empresa->cnpj,
                    'estado' => $this->empresa->estado,
                    'ativo' => $this->empresa->ativo,
                    'categoria' => $this->empresa->categoria,
                ];
            }),
            
            // Diário relacionado
            'diario' => $this->whenLoaded('diario', function () {
                return [
                    'id' => $this->diario->id,
                    'nome_arquivo' => $this->diario->nome_arquivo,
                    'estado' => $this->diario->estado,
                    'data_diario' => $this->diario->data_diario->toDateString(),
                    'status' => $this->diario->status,
                    'total_paginas' => $this->diario->total_paginas,
                    // Usuário que fez upload
                    'usuario' => $this->when($this->diario->relationLoaded('usuario'), function () {
                        return [
                            'id' => $this->diario->usuario->id,
                            'name' => $this->diario->usuario->name,
                        ];
                    }),
                ];
            }),
            
            // Metadados da ocorrência
            'meta' => $this->when($request->get('include_meta'), function () {
                return [
                    'tipo_match_descricao' => $this->getTipoMatchDescription(),
                    'score_nivel' => $this->getScoreLevel(),
                    'contexto_resumido' => $this->getResumedContext(),
                    'pode_reenviar_email' => !$this->notificado_email,
                    'pode_reenviar_whatsapp' => !$this->notificado_whatsapp,
                    'dias_desde_criacao' => $this->created_at->diffInDays(now()),
                ];
            }),
            
            // Análise do contexto (apenas se solicitado)
            'analise' => $this->when($request->get('include_analysis'), function () {
                return [
                    'palavras_antes' => $this->getWordsBefore(),
                    'palavras_depois' => $this->getWordsAfter(),
                    'posicao_no_texto' => $this->getPositionInText(),
                    'frases_relacionadas' => $this->getRelatedSentences(),
                ];
            }),
        ];
    }

    /**
     * Obter descrição do tipo de match
     */
    private function getTipoMatchDescription(): string
    {
        return match($this->tipo_match) {
            'cnpj' => 'CNPJ exato',
            'nome' => 'Nome da empresa',
            'termo' => 'Termo de busca',
            'razao_social' => 'Razão social',
            default => 'Tipo desconhecido'
        };
    }

    /**
     * Obter nível do score
     */
    private function getScoreLevel(): string
    {
        return match(true) {
            $this->score_confianca >= 90 => 'alto',
            $this->score_confianca >= 70 => 'medio',
            default => 'baixo'
        };
    }

    /**
     * Obter contexto resumido
     */
    private function getResumedContext(): string
    {
        if (!$this->contexto) return '';
        
        return strlen($this->contexto) > 200 
            ? substr($this->contexto, 0, 200) . '...'
            : $this->contexto;
    }

    /**
     * Obter palavras antes do termo encontrado
     */
    private function getWordsBefore(int $count = 5): array
    {
        if (!$this->contexto || !$this->termo_encontrado) {
            return [];
        }

        $position = stripos($this->contexto, $this->termo_encontrado);
        if ($position === false) return [];

        $beforeText = substr($this->contexto, 0, $position);
        $words = array_filter(explode(' ', $beforeText));
        
        return array_slice($words, -$count);
    }

    /**
     * Obter palavras depois do termo encontrado
     */
    private function getWordsAfter(int $count = 5): array
    {
        if (!$this->contexto || !$this->termo_encontrado) {
            return [];
        }

        $position = stripos($this->contexto, $this->termo_encontrado);
        if ($position === false) return [];

        $afterText = substr($this->contexto, $position + strlen($this->termo_encontrado));
        $words = array_filter(explode(' ', $afterText));
        
        return array_slice($words, 0, $count);
    }

    /**
     * Obter posição aproximada no texto
     */
    private function getPositionInText(): ?string
    {
        if (!$this->diario || !$this->diario->texto_extraido || !$this->contexto) {
            return null;
        }

        $totalLength = strlen($this->diario->texto_extraido);
        $contextPosition = strpos($this->diario->texto_extraido, substr($this->contexto, 0, 50));
        
        if ($contextPosition === false) return null;

        $percentage = round(($contextPosition / $totalLength) * 100);
        
        return match(true) {
            $percentage < 25 => 'início',
            $percentage < 75 => 'meio',
            default => 'fim'
        };
    }

    /**
     * Obter frases relacionadas
     */
    private function getRelatedSentences(): array
    {
        if (!$this->contexto) return [];

        // Dividir o contexto em frases
        $sentences = preg_split('/[.!?]+/', $this->contexto, -1, PREG_SPLIT_NO_EMPTY);
        
        return array_map('trim', array_slice($sentences, 0, 3));
    }
}
