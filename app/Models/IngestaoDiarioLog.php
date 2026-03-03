<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestaoDiarioLog extends Model
{
    protected $table = 'ingestao_diario_logs';

    protected $fillable = [
        'idempotency_key',
        'source',
        'external_id',
        'status',
        'diario_id',
        'estado',
        'data_diario',
        'nome_arquivo',
        'bucket',
        'object_key',
        'sha256',
        'size_bytes',
        'signature_valid',
        'http_status',
        'mensagem',
        'request_ip',
        'metadata',
        'request_payload',
        'response_payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'data_diario' => 'date',
            'processed_at' => 'datetime',
            'signature_valid' => 'boolean',
            'size_bytes' => 'integer',
            'http_status' => 'integer',
        ];
    }

    public function diario(): BelongsTo
    {
        return $this->belongsTo(Diario::class);
    }
}

