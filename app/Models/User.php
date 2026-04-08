<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Role;
use App\Models\Bodega;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'bodega_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    /**
     * En tu sistema el rol que "decide" (aprueba/rechaza) es Operador (id=2).
     * Dejamos el nombre del método igual para no tocar controllers/vistas ya hechas.
     */
    public function isEncargado(): bool
    {
        return strtolower(trim((string) optional($this->role)->nombre)) === 'operador'
            || (int) $this->role_id === 2;
    }
}