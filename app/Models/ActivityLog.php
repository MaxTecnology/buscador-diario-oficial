<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'ip_address',
        'user_agent',
        'action',
        'entity_type',
        'entity_id',
        'entity_name',
        'description',
        'icon',
        'color',
        'old_values',
        'new_values',
        'context',
        'source',
        'session_id',
        'occurred_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    // Modelo é imutável - não permite updates ou deletes
    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new \Exception('Activity logs são imutáveis e não podem ser alterados');
        }
        return parent::save($options);
    }

    public function delete()
    {
        throw new \Exception('Activity logs são imutáveis e não podem ser removidos');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes para facilitar consultas
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByEntity($query, $entityType, $entityId = null)
    {
        $query = $query->where('entity_type', $entityType);
        
        if ($entityId) {
            $query->where('entity_id', $entityId);
        }
        
        return $query;
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('occurred_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('occurred_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('occurred_at', now()->month)
                    ->whereYear('occurred_at', now()->year);
    }

    // Método para criar log de atividade
    public static function logActivity(array $data): self
    {
        // Dados automáticos do request atual
        $defaultData = [
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name ?? 'Sistema',
            'user_email' => auth()->user()?->email ?? 'sistema@diario.com',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
            'source' => app()->runningInConsole() ? 'cli' : 'web',
            'session_id' => session()->getId(),
            'occurred_at' => now(),
        ];

        $mergedData = array_merge($defaultData, $data);

        return static::create($mergedData);
    }

    // Métodos de conveniência para ações comuns
    public static function logDiarioCreated(Diario $diario): self
    {
        return self::logActivity([
            'action' => 'created',
            'entity_type' => 'Diario',
            'entity_id' => $diario->id,
            'entity_name' => $diario->nome_arquivo,
            'description' => "Diário '{$diario->nome_arquivo}' foi enviado para processamento",
            'icon' => 'heroicon-o-document-plus',
            'color' => 'green',
            'new_values' => [
                'nome_arquivo' => $diario->nome_arquivo,
                'estado' => $diario->estado,
                'tamanho_mb' => round(($diario->tamanho_arquivo ?? 0) / 1024 / 1024, 2),
            ],
            'context' => [
                'tamanho_arquivo' => $diario->tamanho_arquivo,
                'data_diario' => $diario->data_diario?->format('d/m/Y'),
            ]
        ]);
    }

    public static function logDiarioDeleted(Diario $diario): self
    {
        return self::logActivity([
            'action' => 'deleted',
            'entity_type' => 'Diario',
            'entity_id' => $diario->id,
            'entity_name' => $diario->nome_arquivo,
            'description' => "Diário '{$diario->nome_arquivo}' foi removido do sistema",
            'icon' => 'heroicon-o-trash',
            'color' => 'red',
            'old_values' => [
                'nome_arquivo' => $diario->nome_arquivo,
                'estado' => $diario->estado,
                'status' => $diario->status,
                'total_ocorrencias' => $diario->ocorrencias->count(),
            ],
            'context' => [
                'motivo' => 'Exclusão manual pelo usuário',
                'ocorrencias_perdidas' => $diario->ocorrencias->count(),
            ]
        ]);
    }

    public static function logDiarioProcessed(Diario $diario, int $ocorrenciasEncontradas): self
    {
        return self::logActivity([
            'action' => 'processed',
            'entity_type' => 'Diario',
            'entity_id' => $diario->id,
            'entity_name' => $diario->nome_arquivo,
            'description' => "Diário '{$diario->nome_arquivo}' processado: {$ocorrenciasEncontradas} ocorrência(s) encontrada(s)",
            'icon' => 'heroicon-o-cog-6-tooth',
            'color' => $ocorrenciasEncontradas > 0 ? 'blue' : 'gray',
            'new_values' => [
                'status' => $diario->status,
                'total_paginas' => $diario->total_paginas,
                'ocorrencias_encontradas' => $ocorrenciasEncontradas,
            ],
            'context' => [
                'tempo_processamento' => $diario->processado_em?->diffInSeconds($diario->created_at) ?? 0,
                'tentativas' => $diario->tentativas,
            ]
        ]);
    }

    public static function logEmpresaCreated(Empresa $empresa): self
    {
        return self::logActivity([
            'action' => 'created',
            'entity_type' => 'Empresa',
            'entity_id' => $empresa->id,
            'entity_name' => $empresa->nome,
            'description' => "Empresa '{$empresa->nome}' foi cadastrada no sistema",
            'icon' => 'heroicon-o-building-office',
            'color' => 'green',
            'new_values' => [
                'nome' => $empresa->nome,
                'cnpj' => $empresa->cnpj,
                'inscricao_estadual' => $empresa->inscricao_estadual,
            ]
        ]);
    }

    public static function logLogin(User $user): self
    {
        return self::logActivity([
            'action' => 'login',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'entity_name' => $user->name,
            'description' => "Usuário '{$user->name}' fez login no sistema",
            'icon' => 'heroicon-o-arrow-right-on-rectangle',
            'color' => 'blue',
            'context' => [
                'ultimo_login' => $user->last_login_at?->format('d/m/Y H:i:s'),
            ]
        ]);
    }

    public static function logLogout(User $user): self
    {
        return self::logActivity([
            'action' => 'logout',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'entity_name' => $user->name,
            'description' => "Usuário '{$user->name}' fez logout do sistema",
            'icon' => 'heroicon-o-arrow-left-on-rectangle',
            'color' => 'gray',
        ]);
    }
}