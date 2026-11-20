<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\User;

class HojaAsignacion
{
    public function __construct(private AccesoCustodia $acceso) {}

    public function vista(User $usuario, string $grupo, bool $portatil = false): \Illuminate\Contracts\View\View
    {
        $asignaciones = AsignacionInventario::with(['producto', 'bodega', 'colaborador', 'user.bodega', 'movimientoInicial', 'movimientos', 'incidencias'])
            ->where('grupo_asignacion', $grupo)
            ->get();

        abort_if($asignaciones->isEmpty(), 404, 'No se encontraron asignaciones para este PDF.');
        $asignaciones->each(function (AsignacionInventario $asignacion) use ($usuario) {
            abort_unless($this->acceso->puedeVer($usuario, $asignacion), 403);
            $asignacion->cantidad = $asignacion->movimientoInicial?->cantidad
                ?? ($asignacion->cantidad_asignada + $asignacion->movimientos->whereIn('tipo', ['Devolucion', 'Reemplazo'])->sum('cantidad')
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

        return view('admin.asignaciones.pdf', compact(
            'colaborador',
            'asignaciones',
            'total',
            'asignadorNombre',
            'bodegaAsignador',
            'grupo', 'portatil'
        ));
    }
}
