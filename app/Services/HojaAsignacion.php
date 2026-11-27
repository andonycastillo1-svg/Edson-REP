<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\User;

class HojaAsignacion
{
    public function __construct(private AccesoInventarioOperativo $acceso) {}

    public function vista(User $usuario, string $grupo, bool $portatil = false): \Illuminate\Contracts\View\View
    {
        $asignaciones = AsignacionInventario::with(['producto', 'bodega', 'colaborador.region', 'entrega', 'user.bodega', 'movimientoInicial', 'movimientos', 'incidencias'])
            ->where('grupo_asignacion', $grupo)
            ->get();

        abort_if($asignaciones->isEmpty(), 404, 'No se encontraron asignaciones para este PDF.');
        $alcance = $this->acceso->autorizarCustodias($usuario, 'asignaciones.exportar', $asignaciones);
        $this->acceso->registrar($alcance, 'asignaciones.exportar', $grupo);
        $asignaciones->each(function (AsignacionInventario $asignacion) {
            $asignacion->cantidad = $asignacion->movimientoInicial?->cantidad
                ?? ($asignacion->cantidad_asignada + $asignacion->movimientos->whereIn('tipo', ['Devolucion', 'Reemplazo'])->where('estado_devolucion', '!==', 'sin_retorno')->sum('cantidad')
                    + $asignacion->incidencias->where('resultado_stock', 'sin_retorno')->sum('cantidad'));
        });

        $colaborador = $asignaciones->first()->colaborador;

        if (!$colaborador) {
            abort(404, 'No se encontró el colaborador de esta asignación.');
        }

        $total = $asignaciones->sum(function ($a) {
            return ($a->costo_unitario ?? 0) * $a->cantidad;
        });

        $creador = $asignaciones->first()->user;
        $asignadorNombre = $creador?->name ?? 'No identificado';

        $bodegaAsignador = $creador?->bodega?->nombre
            ?? optional($asignaciones->first()?->bodega)->nombre
            ?? 'No definida';

        $formato = app(FormatosDocumento::class)->emitir('asignacion', $grupo);

        return view('admin.asignaciones.pdf', compact(
            'formato',
            'colaborador',
            'asignaciones',
            'total',
            'asignadorNombre',
            'bodegaAsignador',
            'grupo', 'portatil'
        ));
    }
}
