<?php

namespace App\Exports;

use App\Models\AlertaReemplazo;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class DetalleIncidenciasExport extends FormattedExcelExport implements WithStrictNullComparison
{
    private const DIA = 86400;
    private const MES = 30 * self::DIA;

    private ?array $detalle = null;

    public function __construct(private Collection $alertas, string $generatedBy, array $filters)
    {
        parent::__construct('Detalle de incidencias', $generatedBy, $filters);
    }

    protected function headings(): array
    {
        return [
            'Incidencia', 'Código colaborador', 'Nombre completo', 'Puesto', 'Región', 'Estado laboral',
            'Código producto', 'Producto', 'Categoría', 'Cantidad afectada', 'Bodega', 'Condición del artículo anterior',
            'Entrega anterior', 'Recepción o inicio de uso', 'Vida útil original (meses)', 'Vida consumida (días)',
            'Vida restante (días)', 'Vencimiento estimado', 'Costo unitario histórico', 'Costo histórico total',
            'Fecha del reemplazo', 'Motivo del reemplazo', 'Registró el reemplazo', 'Nueva asignación',
            'Vida útil pendiente (%)', 'Referencia económica para revisión de RR. HH.', 'Estado del cálculo',
            'Datos faltantes o criterio', 'Estado de incidencia', 'Fecha de registro', 'Actuaciones y notas',
            'Responsable de seguimiento', 'Fecha de cierre', 'Fecha del hecho', 'Condición registrada', 'Observaciones',
        ];
    }

    protected function rows(): array
    {
        return $this->detalle ??= $this->alertas->map(fn (AlertaReemplazo $alerta) => $this->fila($alerta))->all();
    }

    private function fila(AlertaReemplazo $alerta): array
    {
        $persona = $alerta->colaborador;
        $producto = $alerta->producto;
        $anterior = $alerta->asignacionAnterior ?? $alerta->incidencia?->asignacion;
        $nueva = $alerta->asignacionNueva;
        $incidencia = $alerta->incidencia;
        // La referencia guardada se calculó al reemplazar. La vida al registrar una condición puede corresponder a otra fecha.
        $segundos = $nueva ? $alerta->vida_restante_segundos : ($incidencia?->vida_restante_segundos ?? $alerta->vida_restante_segundos);
        $meses = $alerta->vida_util_meses ?: $anterior?->vida_util_original_meses;
        $proporcion = $segundos !== null && $meses > 0 ? min(1, $segundos / ($meses * self::MES)) : null;
        $costo = $anterior?->costo_unitario;
        $cantidad = $alerta->cantidad;
        $referencia = $alerta->referencia_proporcional;
        $faltantes = array_filter([
            !$anterior ? 'Asignación anterior' : null,
            $costo === null ? 'Costo unitario histórico' : null,
            !$meses ? 'Vida útil original' : null,
            $segundos === null ? 'Vida restante acreditada al ocurrir' : null,
        ]);
        $politica = $alerta->politica_motivo;
        [$estado, $criterio] = match (true) {
            $referencia !== null => ['Calculada', 'Importe por unidad guardado al registrar el reemplazo'],
            $faltantes !== [] => ['No calculable', implode(' · ', $faltantes)],
            $politica === 'revision' || $politica === 'sin_propuesta' => ['Sin propuesta', 'El motivo no prevé una referencia proporcional'],
            default => ['No calculable', 'No existe una referencia acreditada para este registro'],
        };
        $actuacion = $alerta->actuaciones->last();
        $cierre = $alerta->actuaciones->last(fn ($fila) => $fila->accion === 'finalizar');

        return [
            $alerta->id, $alerta->colaborador_codigo, $persona?->nombre, $persona?->puesto, $persona?->region?->nombre,
            $persona?->estado, $alerta->producto_codigo, $producto?->nombre, $producto?->categoria, $cantidad,
            $alerta->bodega_nombre, $incidencia?->motivo_etiqueta,
            $this->fecha($anterior?->entrega?->salida_en ?? $alerta->fecha_asignacion_anterior ?? $anterior?->fecha),
            $this->fecha($anterior?->entrega?->recibida_en),
            $meses ?: null, $proporcion !== null ? round(max(0, $meses * self::MES - $segundos) / self::DIA, 2) : null,
            $segundos !== null ? round($segundos / self::DIA, 2) : null, $this->fecha($anterior?->fecha_vencimiento),
            $costo !== null ? (float) $costo : null, $costo !== null && $cantidad !== null ? round((float) $costo * $cantidad, 2) : null,
            $this->fecha($nueva?->entrega?->salida_en ?? $nueva?->fecha), $nueva?->motivo_registrado ?? $alerta->motivo_etiqueta,
            $nueva?->user?->name ?? $alerta->registrador?->name, $nueva?->id,
            $proporcion, $referencia !== null ? (float) $referencia : null, $estado, $criterio,
            $alerta->estado_etiqueta, $this->fecha($alerta->created_at),
            $alerta->actuaciones->map(fn ($fila) => '#'.$fila->id.' · '.$fila->created_at?->format('d/m/Y').' · '.($fila->usuario?->name ?? 'Usuario histórico').' · '.$fila->nota)->implode("\n"),
            $actuacion?->usuario?->name, $this->fecha($cierre?->created_at),
            $this->fecha($alerta->fecha_incidente), $incidencia?->resultado_stock, $alerta->detalle,
        ];
    }

    private function fecha(?DateTimeInterface $fecha): ?float
    {
        return $fecha ? Date::dateTimeToExcel($fecha) : null;
    }

    protected function numberFormats(): array
    {
        $fecha = 'dd/mm/yyyy hh:mm';
        $moneda = '"Q" #,##0.00';
        return [
            'M' => $fecha, 'N' => $fecha, 'R' => 'dd/mm/yyyy', 'S' => $moneda, 'T' => $moneda,
            'U' => $fecha, 'Y' => '0.00%', 'Z' => $moneda, 'AD' => $fecha, 'AG' => $fecha, 'AH' => $fecha,
        ];
    }
}
