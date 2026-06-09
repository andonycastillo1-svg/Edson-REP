<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AlertasRrhhExport extends FormattedExcelExport
{
    public function __construct(
        protected Collection $alertas,
        string $generatedBy,
        array $filters = [],
    ) {
        parent::__construct('Informe de alertas y descuentos RRHH', $generatedBy, $filters);
    }

    protected function headings(): array
    {
        return [
            'Tipo de alerta',
            'Código colaborador',
            'Colaborador',
            'Código producto',
            'Producto / refacción',
            'Vehículo VIN',
            'Fecha asignación',
            'Fecha daño / reemplazo',
            'Vida útil (meses)',
            'Vida restante (meses)',
            'Aplica descuento',
            'Monto descuento',
            'Estado',
            'Generado por',
            'Observación',
        ];
    }

    protected function rows(): array
    {
        return $this->alertas->map(fn ($alerta) => [
            'Reemplazo anticipado / daño',
            $alerta->colaborador_codigo,
            $alerta->colaborador_nombre ?? 'Sin nombre',
            $alerta->producto_codigo,
            $alerta->producto_descripcion ?: ($alerta->producto_nombre ?: $alerta->producto_codigo),
            $alerta->vehiculo_vin,
            $alerta->fecha_asignacion_anterior ? Date::dateTimeToExcel($alerta->fecha_asignacion_anterior) : null,
            $alerta->fecha_dano_reemplazo ? Date::dateTimeToExcel($alerta->fecha_dano_reemplazo) : null,
            (int) $alerta->vida_util_meses,
            (int) $alerta->meses_restantes_reales,
            $alerta->descuento_aplicable ? 'Sí' : 'No',
            (float) $alerta->descuento_calculado,
            $alerta->estado_etiqueta,
            $alerta->registrado_por_nombre ?? 'No registrado',
            $alerta->detalle,
        ])->all();
    }

    protected function totals(): array
    {
        return [[
            'TOTAL DE ALERTAS', $this->alertas->count(), '', '', '', '', '', '', '', '',
            'TOTAL DESCUENTOS', (float) $this->alertas->sum('descuento_calculado'), '', '', '',
        ]];
    }

    protected function numberFormats(): array
    {
        return [
            'G' => NumberFormat::FORMAT_DATE_DDMMYYYY.' hh:mm',
            'H' => NumberFormat::FORMAT_DATE_DDMMYYYY.' hh:mm',
            'I' => '#,##0',
            'J' => '#,##0',
            'L' => '#,##0.00',
        ];
    }
}
