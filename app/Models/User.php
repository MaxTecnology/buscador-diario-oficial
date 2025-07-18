<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telefone',
        'telefone_whatsapp',
        'aceita_whatsapp',
        'pode_fazer_login',
        'created_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pode_fazer_login' => 'boolean',
            'aceita_whatsapp' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'user_empresa_permissions')
            ->withPivot([
                'pode_visualizar', 
                'pode_receber_email', 
                'pode_receber_whatsapp',
                'notificacao_email',
                'notificacao_whatsapp',
                'nivel_prioridade',
                'notificacao_imediata',
                'resumo_diario',
                'horario_resumo'
            ])
            ->withTimestamps();
    }

    public function diarios(): HasMany
    {
        return $this->hasMany(Diario::class, 'usuario_upload_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'telefone', 'pode_fazer_login'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
