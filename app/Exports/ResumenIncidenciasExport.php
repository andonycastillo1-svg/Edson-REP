<?php

namespace App\Exports;

class ResumenIncidenciasExport extends FormattedExcelExport
{
    public function __construct(private array $estados, private int $total, string $generatedBy, array $filters)
    {
        parent::__construct('Resumen', $generatedBy, $filters);
    }

    protected function headings(): array
    {
        return ['Estado del seguimiento', 'Incidencias'];
    }

    protected function rows(): array
    {
        return $this->estados;
    }

    protected function totals(): array
    {
        return [
            ['Total de incidencias', $this->total],
            ['Los importes del detalle son referencias para revisión de RR. HH.'],
        ];
    }
}
