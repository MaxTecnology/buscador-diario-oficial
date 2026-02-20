<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Ocorrencia extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'diario_id',
        'empresa_id',
        'cnpj',
        'tipo_match',
        'termo_encontrado',
        'contexto_completo',
        'posicao_inicio',
        'posicao_fim',
        'score_confianca',
        'confiabilidade',
        'status_revisao',
        'pagina',
        'notificado_email',
        'notificado_whatsapp',
        'notificado_em',
    ];

    protected function casts(): array
    {
        return [
            'posicao_inicio' => 'integer',
            'posicao_fim' => 'integer',
            'score_confianca' => 'decimal:2',
            'confiabilidade' => 'string',
            'status_revisao' => 'string',
            'pagina' => 'integer',
            'notificado_email' => 'boolean',
            'notificado_whatsapp' => 'boolean',
            'notificado_em' => 'datetime',
        ];
    }

    public function diario(): BelongsTo
    {
        return $this->belongsTo(Diario::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopePorTipoMatch($query, string $tipo)
    {
        return $query->where('tipo_match', $tipo);
    }

    public function scopeComScoreMinimo($query, float $score)
    {
        return $query->where('score_confianca', '>=', $score);
    }

    public function scopeNaoNotificadas($query)
    {
        return $query->where('notificado_email', false)
                    ->where('notificado_whatsapp', false);
    }

    public function scopePorEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePorDiario($query, int $diarioId)
    {
        return $query->where('diario_id', $diarioId);
    }

    public function scopeSuspeitos($query)
    {
        return $query->where('confiabilidade', 'suspeito');
    }

    public function scopeAltaConfianca($query)
    {
        return $query->where('confiabilidade', 'alta');
    }

    public function scopePendentes($query)
    {
        return $query->where('status_revisao', 'pendente');
    }

    public function marcarComoNotificadoPorEmail(): void
    {
        $this->update([
            'notificado_email' => true,
            'notificado_em' => now(),
        ]);
    }

    public function marcarComoNotificadoPorWhatsapp(): void
    {
        $this->update([
            'notificado_whatsapp' => true,
            'notificado_em' => now(),
        ]);
    }

    public function jaNotificado(): bool
    {
        return $this->notificado_email || $this->notificado_whatsapp;
    }

    public function getConfiancaDescricaoAttribute(): string
    {
        if ($this->score_confianca >= 0.95) {
            return 'Muito Alta';
        } elseif ($this->score_confianca >= 0.85) {
            return 'Alta';
        } elseif ($this->score_confianca >= 0.70) {
            return 'Média';
        } else {
            return 'Baixa';
        }
    }

    // Accessor para compatibilidade com relatórios
    public function getScoreAttribute(): float
    {
        return $this->score_confianca;
    }

    // Accessor para compatibilidade com relatórios
    public function getTextoMatchAttribute(): string
    {
        return $this->termo_encontrado;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['diario_id', 'empresa_id', 'cnpj', 'tipo_match', 'score_confianca', 'notificado_email', 'notificado_whatsapp'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
