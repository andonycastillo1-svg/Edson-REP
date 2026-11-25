<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\User;
use Illuminate\Support\Collection;

class AccesoCustodia
{
    public function __construct(private readonly AccesoInventarioOperativo $inventario) {}

    public function puedeVer(User $usuario, AsignacionInventario $asignacion, string $permiso = 'asignaciones.consultar'): bool
    {
        return $this->inventario->buscar($usuario, $permiso, [$asignacion->bodega_id],
            [$asignacion->colaborador?->region_id], [$asignacion->user_id]) !== null;
    }

    public function puedeResolver(User $usuario, AsignacionInventario $asignacion): bool
    {
        return $this->puedeVer($usuario, $asignacion, 'asignaciones.devolver');
    }

    public function puedeConfirmar(User $usuario, Collection $asignaciones): bool
    {
        return $asignaciones->isNotEmpty() && $this->inventario->buscar($usuario, 'asignaciones.confirmar_recepcion',
            $asignaciones->pluck('bodega_id')->unique()->all(), $asignaciones->map(fn ($fila) => $fila->colaborador?->region_id)->unique()->all(),
            $asignaciones->pluck('user_id')->unique()->all()) !== null;
    }
}
