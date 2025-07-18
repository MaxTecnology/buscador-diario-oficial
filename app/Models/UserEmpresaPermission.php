<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UserEmpresaPermission extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'empresa_id',
        'pode_visualizar',
        'pode_receber_email',
        'pode_receber_whatsapp',
    ];

    protected function casts(): array
    {
        return [
            'pode_visualizar' => 'boolean',
            'pode_receber_email' => 'boolean',
            'pode_receber_whatsapp' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeComPermissaoVisualizacao($query)
    {
        return $query->where('pode_visualizar', true);
    }

    public function scopeComPermissaoEmail($query)
    {
        return $query->where('pode_receber_email', true);
    }

    public function scopeComPermissaoWhatsapp($query)
    {
        return $query->where('pode_receber_whatsapp', true);
    }

    public function scopePorUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePorEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function podeReceberNotificacoes(): bool
    {
        return $this->pode_receber_email || $this->pode_receber_whatsapp;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'empresa_id', 'pode_visualizar', 'pode_receber_email', 'pode_receber_whatsapp'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
