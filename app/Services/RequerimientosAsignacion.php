<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\Consolidado;
use App\Models\ConsolidadoNecesidad;
use App\Models\Inventario;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RequerimientosAsignacion
{
    public function __construct(private AccesoInventarioOperativo $acceso, private BodegaAccessService $bodegas) {}

    private function regiones(User $usuario, string $permiso): ?array
    {
        $central = $this->bodegas->principal()?->id;
        if ($central === null) {
            return [];
        }
        $alcances = collect([$permiso, 'asignaciones.vincular'])->map(function ($clave) use ($usuario, $central) {
            $vigentes = $this->acceso->alcances($usuario, $clave)->filter(fn ($ambito) => $ambito->contiene([(int) $central]));

            return $vigentes->contains(fn ($ambito) => $ambito->regiones === null)
                ? null : $vigentes->flatMap(fn ($ambito) => $ambito->regiones)->unique()->all();
        });
        [$operacion, $vinculo] = $alcances->all();

        return $operacion === null || $vinculo === null ? $operacion ?? $vinculo : array_intersect($operacion, $vinculo);
    }

    public function disponibles(User $usuario, string $permiso = 'asignaciones.crear'): Builder
    {
        $regiones = $this->regiones($usuario, $permiso);

        return Consolidado::query()->where('detalle_individual', true)->whereIn('estado', ['aprobado', 'cerrado'])
            ->when($regiones !== null, fn ($q) => $q->whereHas('participantes', fn ($q) =>
                $q->whereIn('region_id', $regiones)->whereHas('colaborador', fn ($q) => $q->whereIn('region_id', $regiones))));
    }

    public function conPendientes(User $usuario, string $permiso = 'asignaciones.crear'): Builder
    {
        $regiones = $this->regiones($usuario, $permiso);

        return $this->disponibles($usuario, $permiso)
            ->whereHas('participantes', fn ($p) => $p
                ->when($regiones !== null, fn ($q) => $q->whereIn('region_id', $regiones))
                ->whereHas('colaborador', fn ($q) => $q->where('estado', 'Activo')
                    ->when($regiones !== null, fn ($q) => $q->whereIn('region_id', $regiones)))
                ->whereHas('necesidades', fn ($q) => $q->where('cantidad', '>', 0)
                    ->whereRaw('consolidado_necesidades.cantidad > (select count(*) from asignaciones_inventarios where requerimiento_id = consolidado_necesidades.id)')));
    }

    public function verificar(User $usuario, int $id, bool $bloquear = false, string $permiso = 'asignaciones.crear'): Consolidado
    {
        abort_if($this->regiones($usuario, $permiso) === [], 403);
        $consulta = $this->disponibles($usuario, $permiso)->whereKey($id);

        return ($bloquear ? $consulta->lockForUpdate() : $consulta)->first()
            ?? throw ValidationException::withMessages(['consolidado_id' => 'Selecciona un consolidado aprobado con necesidades verificadas por colaborador.']);
    }

    public function pendientes(Consolidado $consolidado, ?string $colaborador = null, ?User $usuario = null, string $permiso = 'asignaciones.crear'): Collection
    {
        $regiones = $usuario ? $this->regiones($usuario, $permiso) : null;

        return ConsolidadoNecesidad::query()->with('participante.colaborador', 'producto')->withCount('asignaciones')
            ->whereHas('participante', fn ($q) => $q->where('consolidado_id', $consolidado->id)
                ->when($colaborador !== null, fn ($q) => $q->where('colaborador_codigo', $colaborador))
                ->when($regiones !== null, fn ($q) => $q->whereIn('region_id', $regiones))
                ->whereHas('colaborador', fn ($q) => $q->where('estado', 'Activo')
                    ->when($regiones !== null, fn ($q) => $q->whereIn('region_id', $regiones))))
            ->where('cantidad', '>', 0)->orderBy('consolidado_colaborador_id')->orderBy('producto_codigo')->get()
            ->filter(fn ($fila) => $fila->cantidad > $fila->asignaciones_count);
    }

    public function cantidadesDisponibles(array $bodegas, array $codigos): Collection
    {
        return Inventario::query()->whereIn('bodega_id', $bodegas)->whereIn('producto_codigo', $codigos)
            ->whereIn('stock_tipo', ['nuevo', 'usado'])->select('bodega_id', 'producto_codigo')->selectRaw('SUM(cantidad) AS total')
            ->groupBy('bodega_id', 'producto_codigo')->get()->groupBy('producto_codigo');
    }

    public function vincular(User $usuario, int $id, string $colaborador, Collection $items, bool $bloquear = true, string $permiso = 'asignaciones.crear'): Collection
    {
        $consolidado = $this->verificar($usuario, $id, $bloquear, $permiso);
        $persona = $consolidado->participantes()->where('colaborador_codigo', $colaborador)->first();
        if ($persona) {
            $ids = $items->pluck('bodega_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();
            $regiones = [$persona->region_id, $persona->colaborador?->region_id];
            $this->acceso->autorizar($usuario, $permiso, $ids, $regiones, bloquear: $bloquear);
            $alcance = $this->acceso->autorizar($usuario, 'asignaciones.vincular', [(int) $this->bodegas->principal()?->id], $regiones, bloquear: $bloquear);
            if ($bloquear) {
                $this->acceso->registrar($alcance, 'asignaciones.vincular', $consolidado->id);
            }
        }
        $necesidades = $persona ? $persona->necesidades()->whereIn('producto_codigo', $items->pluck('producto_codigo'))
            ->orderBy('id')->when($bloquear, fn ($q) => $q->lockForUpdate())->get()->keyBy('producto_codigo') : collect();
        // Cada fila vinculada representa una unidad entregada, aunque después se devuelva.
        $entregadas = AsignacionInventario::whereIn('requerimiento_id', $necesidades->pluck('id'))
            ->orderBy('id')->when($bloquear, fn ($q) => $q->lockForUpdate())->pluck('requerimiento_id')->countBy();

        foreach ($items->groupBy('producto_codigo') as $codigo => $lineas) {
            $cantidad = $lineas->sum(fn ($fila) => (int) ($fila['cantidad_asignada'] ?? $fila['cantidad'] ?? 1));
            /** @var ConsolidadoNecesidad|null $necesidad */
            $necesidad = $necesidades->get($codigo);
            if (!$necesidad || $entregadas->get($necesidad->id, 0) + $cantidad > $necesidad->cantidad) {
                throw ValidationException::withMessages(['consolidado_id' => "La entrega de {$codigo} a {$colaborador} supera las unidades aprobadas pendientes en el consolidado."]);
            }
        }

        return $necesidades->pluck('id', 'producto_codigo');
    }
}
