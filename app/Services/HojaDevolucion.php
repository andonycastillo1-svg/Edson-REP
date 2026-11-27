<?php

namespace App\Services;

use App\Models\AsignacionInventarioArchivo;
use App\Models\AsignacionMovimiento;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class HojaDevolucion
{
    public static function movimientos(string $grupo): Collection
    {
        return AsignacionMovimiento::with('asignacion.colaborador.region', 'asignacion.producto', 'asignacion.bodega', 'user')
            ->where('grupo_devolucion', $grupo)->conHojaDevolucion()->orderBy('id')->get();
    }

    public static function pdf(Collection $movimientos, string $grupo): string
    {
        $primerMovimiento = $movimientos->first();
        $formato = app(FormatosDocumento::class)->emitir('devolucion', $grupo);
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->setPaper('letter');
        $pdf->loadHtml(view('admin.asignaciones.hoja_devolucion', compact('movimientos', 'grupo', 'primerMovimiento', 'formato'))->render());
        $pdf->render();

        return $pdf->output();
    }

    public static function guardar(string $grupo, string $ruta): void
    {
        $movimientos = self::movimientos($grupo);
        $pdf = self::pdf($movimientos, $grupo);
        if (!Storage::disk('documentos')->put($ruta, $pdf)) {
            throw new \RuntimeException('No se pudo guardar la hoja de devolución.');
        }

        AsignacionInventarioArchivo::create([
            'asignacion_inventario_id' => $movimientos->first()->asignacion_inventario_id,
            'grupo_devolucion' => $grupo,
            'tipo_documento' => AsignacionInventarioArchivo::DEVOLUCION_GENERADA,
            'ruta' => $ruta,
            'nombre_original' => 'devolucion-'.$grupo.'.pdf',
            'mime' => 'application/pdf',
            'tamano' => strlen($pdf),
            'subido_por_user_id' => $movimientos->first()->user_id,
        ]);
    }
}
