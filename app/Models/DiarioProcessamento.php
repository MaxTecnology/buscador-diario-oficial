<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiarioProcessamento extends Model
{
    use HasFactory;

    protected $fillable = [
        'diario_id',
        'iniciado_por_user_id',
        'tipo',
        'modo',
        'status',
        'motivo',
        'notificar',
        'limpar_ocorrencias_anteriores',
        'iniciado_em',
        'finalizado_em',
        'erro_mensagem',
        'total_ocorrencias',
        'novas_ocorrencias',
        'ocorrencias_desativadas',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'notificar' => 'boolean',
            'limpar_ocorrencias_anteriores' => 'boolean',
            'iniciado_em' => 'datetime',
            'finalizado_em' => 'datetime',
            'total_ocorrencias' => 'integer',
            'novas_ocorrencias' => 'integer',
            'ocorrencias_desativadas' => 'integer',
            'meta' => 'array',
        ];
    }

    public function diario(): BelongsTo
    {
        return $this->belongsTo(Diario::class);
    }

    public function iniciadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por_user_id');
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(Ocorrencia::class, 'diario_processamento_id');
    }
}
