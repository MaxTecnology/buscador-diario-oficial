<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'ocorrencia_id',
        'user_id',
        'empresa_id',
        'diario_id',
        'type',
        'status',
        'recipient',
        'recipient_name',
        'subject',
        'message',
        'headers',
        'external_id',
        'error_message',
        'attempts',
        'triggered_by',
        'triggered_by_user_id',
        'ip_address',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // Modelo é imutável - não permite updates ou deletes
    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new \Exception('Notification logs são imutáveis e não podem ser alterados');
        }
        return parent::save($options);
    }

    public function delete()
    {
        throw new \Exception('Notification logs são imutáveis e não podem ser removidos');
    }

    // Relacionamentos
    public function ocorrencia(): BelongsTo
    {
        return $this->belongsTo(Ocorrencia::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function diario(): BelongsTo
    {
        return $this->belongsTo(Diario::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByRecipient($query, $recipient)
    {
        return $query->where('recipient', $recipient);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    // Métodos de conveniência para logging
    public static function logEmailSent(array $data): self
    {
        $defaultData = [
            'type' => 'email',
            'status' => 'sent',
            'sent_at' => now(),
            'triggered_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ];

        return static::create(array_merge($defaultData, $data));
    }

    public static function logEmailFailed(array $data): self
    {
        $defaultData = [
            'type' => 'email',
            'status' => 'failed',
            'failed_at' => now(),
            'triggered_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ];

        return static::create(array_merge($defaultData, $data));
    }

    public static function logWhatsAppSent(array $data): self
    {
        $defaultData = [
            'type' => 'whatsapp',
            'status' => 'sent',
            'sent_at' => now(),
            'triggered_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ];

        return static::create(array_merge($defaultData, $data));
    }

    public static function logWhatsAppFailed(array $data): self
    {
        $defaultData = [
            'type' => 'whatsapp',
            'status' => 'failed',
            'failed_at' => now(),
            'triggered_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ];

        return static::create(array_merge($defaultData, $data));
    }

    // Métodos para facilitar logging a partir de ocorrência
    public static function logFromOcorrencia(Ocorrencia $ocorrencia, string $type, string $status, array $additionalData = []): self
    {
        $baseData = [
            'ocorrencia_id' => $ocorrencia->id,
            'empresa_id' => $ocorrencia->empresa_id,
            'diario_id' => $ocorrencia->diario_id,
            'type' => $type,
            'status' => $status,
        ];

        // Determinar destinatário baseado no tipo
        if ($type === 'email') {
            $recipient = $ocorrencia->empresa->email ?? '';
            $recipientName = $ocorrencia->empresa->nome;
        } else { // whatsapp
            $recipient = $ocorrencia->empresa->telefone ?? '';
            $recipientName = $ocorrencia->empresa->nome;
        }

        $baseData['recipient'] = $recipient;
        $baseData['recipient_name'] = $recipientName;

        // Adicionar timestamps baseado no status
        if ($status === 'sent') {
            $baseData['sent_at'] = now();
        } elseif ($status === 'failed') {
            $baseData['failed_at'] = now();
        }

        $baseData['triggered_by_user_id'] = auth()->id();
        $baseData['ip_address'] = request()->ip();

        return static::create(array_merge($baseData, $additionalData));
    }

    // Método para obter estatísticas de notificações
    public static function getStats(string $period = 'today'): array
    {
        $query = static::query();

        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->thisWeek();
                break;
            case 'month':
                $query->thisMonth();
                break;
        }

        $stats = $query->selectRaw('
            type,
            status,
            COUNT(*) as total,
            COUNT(DISTINCT recipient) as unique_recipients
        ')
        ->groupBy(['type', 'status'])
        ->get();

        $result = [
            'email' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'unique_recipients' => 0],
            'whatsapp' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'unique_recipients' => 0],
            'totals' => ['sent' => 0, 'failed' => 0, 'total' => 0]
        ];

        foreach ($stats as $stat) {
            $result[$stat->type][$stat->status] = $stat->total;
            $result[$stat->type]['total'] += $stat->total;
            $result[$stat->type]['unique_recipients'] += $stat->unique_recipients;
            $result['totals'][$stat->status] += $stat->total;
            $result['totals']['total'] += $stat->total;
        }

        return $result;
    }
}