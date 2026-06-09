<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'bodega_id',
        'created_by',
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
        return in_array(strtolower(trim((string) optional($this->role)->nombre)), ['operador', 'encargado', 'almacenista'], true)
            || (int) $this->role_id === 2;
    }

    public function isSupervisor(): bool
    {
        return in_array(strtolower(trim((string) optional($this->role)->nombre)), ['supervisor', 'coordinador'], true)
            || (int) $this->role_id === 3;
    }
}
