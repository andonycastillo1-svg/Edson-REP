<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\Consolidado;
use App\Models\Inspeccion;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Sistema\Permisos;
use Illuminate\Database\Eloquent\Builder;

class FlujoAcceso
{
    public function __construct(private Permisos $permisos, private BodegaAccessService $bodegas) {}

    public function puede(User $usuario, string $permiso): bool
    {
        if (!$this->permisos->permite($usuario, 'flujo.'.$permiso)) {
            return false;
        }

        $consulta = match ($permiso) {
            'capturar' => 'inspecciones',
            'consolidar', 'reabrir', 'revisar' => 'consolidados',
            default => null,
        };

        return ($consulta === null || $this->permisos->permite($usuario, 'flujo.'.$consulta))
            && ($usuario->rolOperativo() !== 2 || $this->central($usuario) !== null);
    }

    public function autorizar(User $usuario, string $permiso): void
    {
        abort_unless($this->puede($usuario, $permiso), 403);
    }

    public function puedeCortes(User $usuario): bool
    {
        return $this->puede($usuario, 'inspecciones') && $this->puede($usuario, 'consolidar');
    }

    public function central(User $usuario): ?Bodega
    {
        $bodega = $this->bodegas->principal();

        return $bodega && $this->bodegas->canModifyStock($usuario, (int) $bodega->id) ? $bodega : null;
    }

    public function inspecciones(User $usuario): Builder
    {
        $this->autorizar($usuario, 'inspecciones');

        return Inspeccion::query()
            ->when($usuario->rolOperativo() === 3, fn (Builder $q) => $q->where('supervisor_id', $usuario->id)
                ->whereIn('region_id', $this->regiones($usuario)))
            ->when($usuario->rolOperativo() === 5, fn (Builder $q) => $q->whereIn('region_id', $this->regiones($usuario)));
    }

    public function consolidados(User $usuario): Builder
    {
        $this->autorizar($usuario, 'consolidados');

        return Consolidado::query()->when($usuario->rolOperativo() === 5, fn (Builder $q) => $q
            ->where('coordinador_id', $usuario->id)->where('estado', '!=', 'borrador')
            ->whereDoesntHave('inspecciones', fn (Builder $i) => $i->whereNotIn('inspecciones.region_id', $this->regiones($usuario)))
            ->whereDoesntHave('participantes', fn (Builder $p) => $p->whereNotIn('region_id', $this->regiones($usuario)))
            ->where(fn (Builder $q) => $q->has('inspecciones')->orHas('participantes')));
    }

    public function regiones(User $usuario): Builder
    {
        return Region::query()->where('activa', true)->select('regiones.id')
            ->when($usuario->rolOperativo() === 5,
                fn ($q) => $q->where('coordinador_id', $usuario->id),
                fn ($q) => $q->whereIn('regiones.id', DB::table('region_usuario_operativa')->where('user_id', $usuario->id)->select('region_id')));
    }
}
