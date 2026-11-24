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
    public function __construct(private FlujoAcceso $acceso) {}

    public function disponibles(User $usuario): Builder
    {
        return Consolidado::query()->where('detalle_individual', true)->whereIn('estado', ['aprobado', 'cerrado'])
            ->when(!$this->acceso->puede($usuario, 'compras'), fn ($q) => $q->whereRaw('1 = 0'));
    }

    public function verificar(User $usuario, int $id, bool $bloquear = false): Consolidado
    {
        $this->acceso->autorizar($usuario, 'compras');
        $consulta = $this->disponibles($usuario)->whereKey($id);

        return ($bloquear ? $consulta->lockForUpdate() : $consulta)->first()
            ?? throw ValidationException::withMessages(['consolidado_id' => 'Selecciona un consolidado aprobado con necesidades verificadas por colaborador.']);
    }

    public function pendientes(Consolidado $consolidado, ?string $colaborador = null): Collection
    {
        return ConsolidadoNecesidad::query()->with('participante.colaborador', 'producto')->withCount('asignaciones')
            ->whereHas('participante', fn ($q) => $q->where('consolidado_id', $consolidado->id)
                ->when($colaborador !== null, fn ($q) => $q->where('colaborador_codigo', $colaborador))
                ->whereHas('colaborador', fn ($q) => $q->where('estado', 'Activo')))
            ->where('cantidad', '>', 0)->orderBy('consolidado_colaborador_id')->orderBy('producto_codigo')->get()
            ->filter(fn ($fila) => $fila->cantidad > $fila->asignaciones_count);
    }

    public function cantidadesDisponibles(array $bodegas, array $codigos): Collection
    {
        return Inventario::query()->whereIn('bodega_id', $bodegas)->whereIn('producto_codigo', $codigos)
            ->whereIn('stock_tipo', ['nuevo', 'usado'])->select('bodega_id', 'producto_codigo')->selectRaw('SUM(cantidad) AS total')
            ->groupBy('bodega_id', 'producto_codigo')->get()->groupBy('producto_codigo');
    }

    public function vincular(User $usuario, int $id, string $colaborador, Collection $items, bool $bloquear = true): Collection
    {
        $consolidado = $this->verificar($usuario, $id, $bloquear);
        $persona = $consolidado->participantes()->where('colaborador_codigo', $colaborador)->first();
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
