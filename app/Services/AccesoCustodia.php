<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\User;
use App\Sistema\Permisos;
use Illuminate\Support\Collection;

class AccesoCustodia
{
    public function __construct(private readonly BodegaAccessService $bodegas, private readonly Permisos $permisos) {}

    public function puedeVer(User $usuario, AsignacionInventario $asignacion): bool
    {
        return match ($usuario->rolOperativo()) {
            1 => true,
            2 => (int) $asignacion->user_id === (int) $usuario->id,
            3 => $usuario->almacenistasAsignados()->whereKey($asignacion->user_id)->exists(),
            default => false,
        };
    }

    public function puedeResolver(User $usuario, AsignacionInventario $asignacion): bool
    {
        return $this->permisos->permite($usuario, 'asignaciones.devolver')
            && $this->puedeVer($usuario, $asignacion)
            && $this->bodegas->canReceiveStock($usuario, (int) $asignacion->bodega_id);
    }

    public function puedeConfirmar(User $usuario, Collection $asignaciones): bool
    {
        return $asignaciones->isNotEmpty()
            && $this->permisos->permite($usuario, 'asignaciones.crear')
            && $this->permisos->permite($usuario, 'asignaciones.consultar')
            && $asignaciones->every(fn (AsignacionInventario $asignacion) => $this->puedeVer($usuario, $asignacion)
                && $this->bodegas->canModifyStock($usuario, (int) $asignacion->bodega_id));
    }
}
