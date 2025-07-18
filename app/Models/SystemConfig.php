<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SystemConfig extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'chave',
        'valor',
        'tipo',
        'descricao',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($config) {
            Cache::forget("system_config_{$config->chave}");
        });

        static::deleted(function ($config) {
            Cache::forget("system_config_{$config->chave}");
        });
    }

    public static function get(string $chave, $default = null)
    {
        return Cache::remember("system_config_{$chave}", 3600, function () use ($chave, $default) {
            $config = static::where('chave', $chave)->first();
            
            if (!$config) {
                return $default;
            }

            return match ($config->tipo) {
                'boolean' => filter_var($config->valor, FILTER_VALIDATE_BOOLEAN),
                'number' => is_numeric($config->valor) ? (float) $config->valor : $default,
                'json' => json_decode($config->valor, true) ?: $default,
                default => $config->valor,
            };
        });
    }

    public static function getValue(string $chave, $default = null)
    {
        return self::get($chave, $default);
    }

    public static function setValue(string $chave, $valor, ?string $descricao = null): void
    {
        $config = static::where('chave', $chave)->first();
        
        if (!$config) {
            // Tentar detectar o tipo automaticamente
            $tipo = match (true) {
                is_bool($valor) => 'boolean',
                is_numeric($valor) => 'number',
                is_array($valor) => 'json',
                default => 'string'
            };
            
            static::create([
                'chave' => $chave,
                'valor' => match ($tipo) {
                    'boolean' => $valor ? '1' : '0',
                    'json' => json_encode($valor),
                    default => (string) $valor,
                },
                'tipo' => $tipo,
                'descricao' => $descricao ?? "Configuração {$chave}",
            ]);
            return;
        }

        $valorFormatado = match ($config->tipo) {
            'boolean' => $valor ? '1' : '0',
            'json' => json_encode($valor),
            default => (string) $valor,
        };

        $config->update([
            'valor' => $valorFormatado,
            'descricao' => $descricao ?? $config->descricao,
        ]);
    }

    public static function set(string $chave, $valor, string $tipo = 'string', ?string $descricao = null): void
    {
        $valorFormatado = match ($tipo) {
            'boolean' => $valor ? '1' : '0',
            'json' => json_encode($valor),
            default => (string) $valor,
        };

        static::updateOrCreate(
            ['chave' => $chave],
            [
                'valor' => $valorFormatado,
                'tipo' => $tipo,
                'descricao' => $descricao,
            ]
        );
    }

    public function getValorFormatadoAttribute()
    {
        return match ($this->tipo) {
            'boolean' => filter_var($this->valor, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($this->valor) ? (float) $this->valor : null,
            'json' => json_decode($this->valor, true),
            default => $this->valor,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['chave', 'valor', 'tipo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
