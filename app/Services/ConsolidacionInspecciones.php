<?php

namespace App\Services;

use App\Models\Consolidado;
use App\Models\CorteRequerimiento;
use App\Models\Inspeccion;
use App\Models\User;
use App\Sistema\Auditoria;
use Illuminate\Database\Eloquent\Collection as Registros;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsolidacionInspecciones
{
    public function __construct(private DetalleConsolidado $detalle, private Auditoria $auditoria) {}

    public function bloquearDisponibles(array $ids): Registros
    {
        $inspecciones = Inspeccion::whereKey($ids)->orderBy('id')->lockForUpdate()->get();
        $ocupadas = DB::table('consolidado_inspeccion')->whereIn('inspeccion_id', $ids)
            ->orderBy('inspeccion_id')->lockForUpdate()->pluck('inspeccion_id');

        if ($inspecciones->count() !== count($ids) || $ocupadas->isNotEmpty()) {
            throw ValidationException::withMessages(['inspecciones' => 'Alguna inspección ya pertenece a otro consolidado o no existe. Revisa la selección.']);
        }

        return $inspecciones;
    }

    public function generar(User $operador, User $coordinador, Registros $inspecciones, string $nombre, string $desde, string $hasta, ?CorteRequerimiento $corte = null): Consolidado
    {
        $consolidado = new Consolidado([
            'nombre' => $nombre, 'desde' => $desde, 'hasta' => $hasta, 'estado' => 'enviado',
            'coordinador_id' => $coordinador->id, 'operador_id' => $operador->id,
        ]);
        if ($corte !== null) {
            $consolidado->corte()->associate($corte);
        }
        $consolidado->save();
        $consolidado->inspecciones()->attach($inspecciones->modelKeys());
        $this->detalle->inicializar($consolidado);
        $consolidado->save();
        $origen = $corte ? ['corte_id' => $corte->id] : [];
        $this->evento($consolidado, $operador, 'crear', null, ['inspecciones' => $inspecciones->modelKeys()] + $origen);
        $this->evento($consolidado, $operador, 'enviar', null, ['coordinador' => $coordinador->id] + $origen);

        return $consolidado;
    }

    public function evento(Consolidado $consolidado, User $usuario, string $accion, ?string $nota, array $cambios): void
    {
        $evento = $consolidado->eventos()->create(['user_id' => $usuario->id, 'accion' => $accion, 'nota' => $nota, 'cambios' => $cambios]);
        $this->auditoria->registrar('flujo', $accion.'_consolidado', $consolidado->id, null, ['evento' => $evento->id] + $cambios);
        if (in_array($accion, ['enviar', 'reasignar', 'aprobar', 'devolver', 'cerrar'], true)) {
            DB::afterCommit(fn () => app(NotificacionService::class)->safeAction(
                fn ($servicio) => $servicio->notificarConsolidado($evento)
            ));
        }
    }
}
