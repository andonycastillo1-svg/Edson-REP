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

    public function responsablesRegionales(AsignacionInventario $asignacion, string $permiso = 'asignaciones.consultar'): Collection
    {
        $region = $asignacion->colaborador?->region_id;
        if ($region === null || (int) $asignacion->bodega_id !== (int) app(BodegaAccessService::class)->principal()?->id) {
            return collect();
        }

        return User::with('role', 'reglasSistema')->where('acceso_activo', true)
            ->where(fn ($q) => $q->whereHas('regionesOperativas', fn ($r) => $r->whereKey($region))
                ->orWhereHas('regionesCoordinadas', fn ($r) => $r->whereKey($region))
                ->orWhereIn('id', \Illuminate\Support\Facades\DB::table('region_coordinador_operativo')
                    ->where('region_id', $region)->select('user_id')))
            ->get()->filter(fn (User $usuario) => app(\App\Sistema\Permisos::class)->permite($usuario, 'asignaciones.documentacion_regional')
                && $this->puedeVer($usuario, $asignacion, $permiso));
    }

    public static function rutaEntrega(User $usuario): string
    {
        $prefijo = $usuario->rolOperativo() === 3 ? 'supervisor' : AccesoInventarioOperativo::prefijo($usuario);

        return $prefijo.'.asignaciones.entrega';
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
