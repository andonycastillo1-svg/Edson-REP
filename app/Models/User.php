<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'bodega_id',
        'created_by',
        'coordinador_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'acceso_activo' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function rolOperativo(): int
    {
        $rol = $this->role;

        return $rol === null || $rol->activo === false ? 0 : $rol->ambito();
    }

    public function reglasSistema()
    {
        return $this->hasMany(\App\Sistema\ReglaUsuario::class);
    }

    public function scopeDeAmbito($query, int $ambito)
    {
        if (!app(\App\Sistema\Permisos::class)->instalado()) {
            return $query->where('users.role_id', $ambito);
        }

        return $query->where('users.acceso_activo', true)->whereHas('role', fn ($rol) => $rol
            ->where('activo', true)->where(function ($q) use ($ambito) {
                $q->where(fn ($extra) => $extra->whereNotIn('id', [1, 2, 3, 4])->where('ambito_operativo', $ambito));
                if (in_array($ambito, [1, 2, 3, 4], true)) {
                    $q->orWhere('id', $ambito);
                }
            }));
    }

    public function coordinador()
    {
        return $this->belongsTo(self::class, 'coordinador_id');
    }

    public function regionesCoordinadas()
    {
        return $this->hasMany(Region::class, 'coordinador_id');
    }

    public function regionesOperativas()
    {
        return $this->belongsToMany(Region::class, 'region_usuario_operativa');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supervisoresAsignados()
    {
        return $this->belongsToMany(
            User::class,
            'almacenista_supervisores',
            'almacenista_id',
            'supervisor_id'
        )->withTimestamps();
    }

    public function almacenistasAsignados()
    {
        return $this->belongsToMany(
            User::class,
            'almacenista_supervisores',
            'supervisor_id',
            'almacenista_id'
        )->withTimestamps();
    }

    /**
     * En el sistema el rol que aprueba/rechaza traslados es Operador/Encargado (id=2).
     * Se conserva el nombre del método por compatibilidad con controladores y vistas existentes.
     */
    public function isEncargado(): bool
    {
        return $this->rolOperativo() === 2;
    }

    public function isSupervisor(): bool
    {
        return $this->rolOperativo() === 3;
    }
}
