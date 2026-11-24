<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\Consolidado;
use App\Models\ConsolidadoNecesidad;
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

    public function vincular(User $usuario, int $id, string $colaborador, Collection $items): Collection
    {
        $consolidado = $this->verificar($usuario, $id, bloquear: true);
        $persona = $consolidado->participantes()->where('colaborador_codigo', $colaborador)->first();
        $necesidades = $persona ? $persona->necesidades()->whereIn('producto_codigo', $items->pluck('producto_codigo'))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('producto_codigo') : collect();
        // Cada fila vinculada representa una unidad entregada, aunque después se devuelva.
        $entregadas = AsignacionInventario::whereIn('requerimiento_id', $necesidades->pluck('id'))
            ->orderBy('id')->lockForUpdate()->pluck('requerimiento_id')->countBy();

        foreach ($items->countBy('producto_codigo') as $codigo => $cantidad) {
            /** @var ConsolidadoNecesidad|null $necesidad */
            $necesidad = $necesidades->get($codigo);
            if (!$necesidad || $entregadas->get($necesidad->id, 0) + $cantidad > $necesidad->cantidad) {
                throw ValidationException::withMessages(['consolidado_id' => "La entrega de {$codigo} a {$colaborador} supera las unidades aprobadas pendientes en el consolidado."]);
            }
        }

        return $necesidades->pluck('id', 'producto_codigo');
    }
}
