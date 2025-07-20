<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
// use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Empresa extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'nome',
        'cnpj',
        'inscricao_estadual',
        'termos_personalizados',
        'variantes_busca',
        'prioridade',
        'score_minimo',
        'ativo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'termos_personalizados' => 'array',
            'variantes_busca' => 'array',
            'score_minimo' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($empresa) {
            $empresa->generateVariantesBusca();
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_empresa_permissions')
            ->withPivot([
                'pode_visualizar',
                'notificacao_email',
                'notificacao_whatsapp'
            ])
            ->withTimestamps();
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(Ocorrencia::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generateVariantesBusca(): void
    {
        $variantes = [];
        
        if ($this->nome) {
            $variantes[] = $this->nome;
            $variantes[] = strtoupper($this->nome);
            $variantes[] = $this->removeAcentos($this->nome);
            $variantes[] = strtoupper($this->removeAcentos($this->nome));
            
            $palavras = explode(' ', $this->nome);
            if (count($palavras) > 1) {
                $abreviacoes = array_map(fn($palavra) => substr($palavra, 0, 1), $palavras);
                $variantes[] = implode('', $abreviacoes);
            }
        }

        if ($this->cnpj) {
            $variantes[] = $this->cnpj;
            $variantes[] = preg_replace('/[^0-9]/', '', $this->cnpj);
        }

        if ($this->termos_personalizados && is_array($this->termos_personalizados)) {
            foreach ($this->termos_personalizados as $termo) {
                $variantes[] = $termo;
                $variantes[] = strtoupper($termo);
                $variantes[] = $this->removeAcentos($termo);
            }
        }

        $this->variantes_busca = array_unique(array_filter($variantes));
    }

    private function removeAcentos(string $string): string
    {
        return strtr($string, [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ç' => 'C', 'ç' => 'c',
        ]);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cnpj' => $this->cnpj,
            'variantes_busca' => $this->variantes_busca,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'cnpj', 'prioridade', 'score_minimo', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
