<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class AlertasRrhhExport extends FormattedExcelExport
{
    public function __construct(
        protected Collection $alertas,
        string $generatedBy,
        array $filters = [],
    ) {
        parent::__construct('Incidencias para revisión de RR. HH.', $generatedBy, $filters);
    }

    protected function headings(): array
    {
        return [
            'Motivo', 'Código colaborador', 'Colaborador', 'Código producto', 'Producto / refacción',
            'Cantidad', 'Bodega', 'Vehículo VIN', 'Fecha de asignación', 'Fecha del incidente',
            'Vida al ocurrir', 'Estado', 'Registrado por', 'Observaciones',
        ];
    }

    protected function rows(): array
    {
        return $this->alertas->map(fn ($alerta) => [
            $alerta->motivo_etiqueta, $alerta->colaborador_codigo, $alerta->colaborador?->nombre,
            $alerta->producto_codigo, $alerta->producto?->nombre, $alerta->cantidad,
            $alerta->bodega_nombre, $alerta->vehiculo_vin,
            $alerta->fecha_asignacion_anterior ? Date::dateTimeToExcel($alerta->fecha_asignacion_anterior) : null,
            $alerta->fecha_incidente ? Date::dateTimeToExcel($alerta->fecha_incidente) : null,
            $alerta->vida_etiqueta, $alerta->estado_etiqueta, $alerta->registrador?->name, $alerta->detalle,
        ])->all();
    }

    protected function totals(): array
    {
        return [['TOTAL DE INCIDENCIAS', $this->alertas->count()]];
    }

    protected function numberFormats(): array
    {
        return ['I' => 'dd/mm/yyyy', 'J' => 'dd/mm/yyyy hh:mm'];
    }
}
