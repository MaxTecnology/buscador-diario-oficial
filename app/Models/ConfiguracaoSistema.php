<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ConfiguracaoSistema extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'configuracoes_sistema';
    
    protected $fillable = [
        'chave',
        'valor',
        'tipo',
        'descricao',
        'categoria',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'valor' => 'string',
        ];
    }

    public static function get(string $chave, $padrao = null)
    {
        $config = self::where('chave', $chave)->where('ativo', true)->first();
        
        if (!$config) {
            return $padrao;
        }

        return match($config->tipo) {
            'boolean' => filter_var($config->valor, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $config->valor,
            'float' => (float) $config->valor,
            'json' => json_decode($config->valor, true),
            default => $config->valor,
        };
    }

    public static function set(string $chave, $valor, string $tipo = 'string', string $descricao = ''): void
    {
        $valorString = match($tipo) {
            'boolean' => $valor ? '1' : '0',
            'json' => json_encode($valor),
            default => (string) $valor,
        };

        self::updateOrCreate(
            ['chave' => $chave],
            [
                'valor' => $valorString,
                'tipo' => $tipo,
                'descricao' => $descricao,
                'ativo' => true,
            ]
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['chave', 'valor', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}