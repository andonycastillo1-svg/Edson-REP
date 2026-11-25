<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\Consolidado;
use App\Models\Inspeccion;
use App\Models\Region;
use App\Models\User;
use App\Sistema\Permisos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FlujoAcceso
{
    public function __construct(private Permisos $permisos, private BodegaAccessService $bodegas, private DelegacionesOperativas $delegaciones) {}

    public function puede(User $usuario, string $permiso): bool
    {
        if (($permiso === 'importar' && !$this->central($usuario))
            || (in_array($permiso, ['compras', 'plan_compras', 'exportar_compras', 'constancias', 'cerrar_compras', 'cortes',
                'consolidar', 'crear_consolidados', 'enviar_consolidados'], true) && !$this->centralRequerimientos($usuario))) {
            return false;
        }

        $consulta = match ($permiso) {
            'capturar', 'exportar_inspecciones', 'cortes' => 'inspecciones',
            'crear_consolidados', 'enviar_consolidados', 'reabrir', 'revisar', 'ajustar', 'agregar_colaboradores', 'aprobar', 'exportar_consolidados', 'cerrar_compras' => 'consolidados',
            'plan_compras', 'exportar_compras', 'constancias' => 'compras',
            default => null,
        };

        return $this->tiene($usuario, $permiso) && ($consulta === null || $this->tiene($usuario, $consulta));
    }

    private function tiene(User $usuario, string $permiso): bool
    {
        $clave = 'flujo.'.$permiso;

        return $this->permanente($usuario, $permiso) || $this->delegaciones->vigentes($usuario, $clave)->isNotEmpty();
    }

    private function permanente(User $usuario, string $permiso): bool
    {
        if (!$this->permisos->permite($usuario, 'flujo.'.$permiso)) {
            return false;
        }

        // El acceso preexistente del operador secundario sigue limitado a su bodega.
        return $usuario->rolOperativo() !== 2 || $this->centralRequerimientos($usuario) !== null
            || ($this->permisos->resultado($usuario, 'flujo.'.$permiso)['origen'] === 'Regla individual'
            && in_array($permiso, ['capturar', 'inspecciones', 'exportar_inspecciones', 'consolidados', 'revisar',
                'ajustar', 'agregar_colaboradores', 'aprobar', 'exportar_consolidados'], true));
    }

    public function autorizar(User $usuario, string $permiso): void
    {
        abort_unless($this->puede($usuario, $permiso), 403);
    }

    public function puedeCortes(User $usuario): bool
    {
        return $this->puede($usuario, 'cortes');
    }

    public function central(User $usuario): ?Bodega
    {
        $bodega = $this->bodegas->principal();

        return $bodega && $this->bodegas->canModifyStock($usuario, (int) $bodega->id) ? $bodega : null;
    }

    public function centralRequerimientos(User $usuario): ?Bodega
    {
        if ($bodega = $this->central($usuario)) {
            return $bodega;
        }
        $bodega = $this->bodegas->principal();

        return $bodega && DB::table('bodega_usuario_operativa')->where('user_id', $usuario->id)
            ->where('bodega_id', $bodega->id)->where('opera_requerimientos', true)->exists() ? $bodega : null;
    }

    public function inspecciones(User $usuario, string $permiso = 'inspecciones'): Builder
    {
        $this->autorizar($usuario, $permiso);
        $propio = $this->permanente($usuario, $permiso) && $this->permanente($usuario, 'inspecciones');
        if ($propio && $this->centralRequerimientos($usuario)) {
            return Inspeccion::query();
        }

        return Inspeccion::where(function (Builder $q) use ($usuario, $propio, $permiso) {
            $q->whereRaw('1 = 0');
            if ($propio) {
                $q->orWhere(fn (Builder $scope) => $this->limitarInspecciones($scope, $usuario, $this->regiones($usuario)));
            }
            foreach ($this->delegaciones->vigentes($usuario, 'flujo.'.$permiso) as $delegacion) {
                if (in_array('flujo.inspecciones', $delegacion->permisos, true) && $this->permanente($delegacion->titular, 'inspecciones')) {
                    $q->orWhere(fn (Builder $scope) => $this->limitarInspecciones($scope, $delegacion->titular, $this->delegaciones->regiones($delegacion), $usuario->id));
                }
            }
        });
    }

    private function limitarInspecciones(Builder $consulta, User $usuario, Builder|array $regiones, ?int $sustituto = null): Builder
    {
        return $consulta->whereIn('region_id', $regiones)
            ->when($usuario->rolOperativo() === 3, fn ($q) => $q->whereIn('supervisor_id', array_filter([$usuario->id, $sustituto])));
    }

    public function consolidados(User $usuario, string $permiso = 'consolidados'): Builder
    {
        $this->autorizar($usuario, $permiso);
        $propio = $this->permanente($usuario, $permiso) && $this->permanente($usuario, 'consolidados');
        if ($propio && $this->centralRequerimientos($usuario) && !in_array($permiso, ['revisar', 'ajustar', 'agregar_colaboradores', 'aprobar'], true)) {
            return Consolidado::query();
        }

        return Consolidado::where(function (Builder $q) use ($usuario, $permiso, $propio) {
            $q->whereRaw('1 = 0');
            if ($propio) {
                $q->orWhere(fn (Builder $scope) => $this->limitarConsolidados($scope, $usuario, $this->regiones($usuario)));
            }
            foreach ($this->delegaciones->vigentes($usuario, 'flujo.'.$permiso) as $delegacion) {
                if (in_array('flujo.consolidados', $delegacion->permisos, true) && $this->permanente($delegacion->titular, 'consolidados')) {
                    $q->orWhere(fn (Builder $scope) => $this->limitarConsolidados($scope, $delegacion->titular, $this->delegaciones->regiones($delegacion)));
                }
            }
        });
    }

    private function limitarConsolidados(Builder $consulta, User $usuario, Builder|array $regiones): Builder
    {
        return $consulta->where('coordinador_id', $usuario->id)->where('estado', '!=', 'borrador')
            ->whereDoesntHave('inspecciones', fn (Builder $i) => $i->whereNotIn('inspecciones.region_id', $regiones))
            ->whereDoesntHave('participantes', fn (Builder $p) => $p->whereNotIn('region_id', $regiones))
            ->where(fn (Builder $q) => $q->has('inspecciones')->orHas('participantes'));
    }

    public function regiones(User $usuario, ?string $permiso = null): Builder
    {
        $delegadas = $permiso === null ? [] : $this->delegaciones->vigentes($usuario, 'flujo.'.$permiso)
            ->filter(fn ($fila) => $permiso !== 'capturar' || in_array('flujo.inspecciones', $fila->permisos, true))
            ->filter(fn ($fila) => $permiso !== 'capturar' || $this->permanente($fila->titular, 'inspecciones'))
            ->flatMap(fn ($fila) => $this->delegaciones->regiones($fila))->unique()->all();

        return Region::query()->where('activa', true)->select('regiones.id')->where(function (Builder $q) use ($usuario, $permiso, $delegadas) {
            $q->whereIn('regiones.id', $delegadas);
            if ($permiso === null || ($this->permanente($usuario, $permiso) && $this->permanente($usuario, 'inspecciones'))) {
                $q->orWhereIn('regiones.id', $this->delegaciones->regionesTitular($usuario));
            }
        });
    }

    public function registrarCaptura(User $usuario, int $region, int $inspeccion): void
    {
        if (!$this->permanente($usuario, 'capturar') || !$this->permanente($usuario, 'inspecciones') || !$this->regiones($usuario)->whereKey($region)->exists()) {
            $delegacion = $this->delegaciones->vigentes($usuario, 'flujo.capturar', bloquear: true)->first(fn ($fila) =>
                in_array('flujo.inspecciones', $fila->permisos, true) && $this->permanente($fila->titular, 'inspecciones')
                && in_array($region, $this->delegaciones->regiones($fila), true));
            abort_unless($delegacion, 403);
            $this->delegaciones->registrar($delegacion, 'flujo.capturar', $inspeccion);
        }
    }

    public function regionesRevision(User $usuario, Consolidado $consolidado, string $permiso): array
    {
        if ((int) $consolidado->coordinador_id === (int) $usuario->id && $this->permanente($usuario, $permiso)) {
            return $this->delegaciones->regionesTitular($usuario);
        }

        $ids = $consolidado->inspecciones()->pluck('inspecciones.region_id')->merge($consolidado->participantes()->pluck('region_id'));
        $mutacion = request()->isMethod('POST');
        $delegacion = $this->delegaciones->vigentes($usuario, 'flujo.'.$permiso, bloquear: $mutacion)->first(fn ($fila) =>
            (int) $fila->titular_id === (int) $consolidado->coordinador_id && in_array('flujo.consolidados', $fila->permisos, true)
            && $ids->diff($this->delegaciones->regiones($fila))->isEmpty());
        abort_unless($delegacion, 403);
        if ($mutacion) {
            $this->delegaciones->registrar($delegacion, 'flujo.'.$permiso, $consolidado->id);
        }

        return $this->delegaciones->regiones($delegacion);
    }
}
