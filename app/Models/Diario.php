<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Diario extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'nome_arquivo',
        'estado',
        'data_diario',
        'hash_sha256',
        'caminho_arquivo',
        'tamanho_arquivo',
        'total_paginas',
        'texto_extraido',
        'texto_completo',
        'caminho_texto_completo',
        'status',
        'status_processamento',
        'erro_mensagem',
        'erro_processamento',
        'processado_em',
        'tentativas',
        'usuario_upload_id',
    ];

    protected function casts(): array
    {
        return [
            'data_diario' => 'date',
            'processado_em' => 'datetime',
            'tentativas' => 'integer',
            'total_paginas' => 'integer',
            'tamanho_arquivo' => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_upload_id');
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(Ocorrencia::class);
    }

    public function scopePendentes($query)
    {
        return $query->where('status', 'pendente');
    }

    public function scopeProcessando($query)
    {
        return $query->where('status', 'processando');
    }

    public function scopeConcluidos($query)
    {
        return $query->where('status', 'concluido');
    }

    public function scopeComErro($query)
    {
        return $query->where('status', 'erro');
    }

    public function scopePorEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    public function marcarComoProcessando(): void
    {
        $this->update([
            'status' => 'processando',
            'tentativas' => $this->tentativas + 1,
        ]);
    }

    public function marcarComoConcluido(): void
    {
        $this->update([
            'status' => 'concluido',
            'processado_em' => now(),
        ]);
    }

    public function marcarComoErro(string $mensagem): void
    {
        $this->update([
            'status' => 'erro',
            'erro_mensagem' => $mensagem,
        ]);
    }

    public function podeSerReprocessado(): bool
    {
        return $this->tentativas < 3;
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'nome_arquivo' => $this->nome_arquivo,
            'estado' => $this->estado,
            'data_diario' => $this->data_diario->format('Y-m-d'),
            'texto_extraido' => $this->texto_extraido,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome_arquivo', 'estado', 'data_diario', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($diario) {
            // Remover arquivo PDF
            $disk = \Illuminate\Support\Facades\Storage::disk(config('filesystems.diarios_disk', 'diarios'));
            if ($diario->caminho_arquivo && $disk->exists($diario->caminho_arquivo)) {
                $disk->delete($diario->caminho_arquivo);
            }
            
            // Remover arquivo de texto extraído
            if ($diario->caminho_texto_completo && $disk->exists($diario->caminho_texto_completo)) {
                $disk->delete($diario->caminho_texto_completo);
            }
        });
    }
}
