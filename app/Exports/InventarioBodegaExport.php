<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InventarioBodegaExport extends FormattedExcelExport
{
    public function __construct(
        protected Collection $inventarios,
        protected string $bodega,
        string $generatedBy,
        array $filters = [],
    ) {
        parent::__construct('Inventario de bodega: '.$bodega, $generatedBy, $filters);
    }

    protected function headings(): array
    {
        return [
            'Código producto',
            'Producto',
            'Descripción',
            'Categoría / tipo',
            'Bodega',
            'Cantidad',
            'Vida útil (meses)',
            'Costo unitario',
            'Costo total',
            'Última actualización',
        ];
    }

    protected function rows(): array
    {
        return $this->inventarios->map(fn ($item) => [
            $item->producto_codigo,
            $item->nombre,
            $item->descripcion,
            $item->categoria,
            $this->bodega,
            (int) $item->cantidad,
            $item->vida_util_meses !== null ? (int) $item->vida_util_meses : null,
            (float) $item->costo_unitario,
            (float) $item->costo_total,
            $item->updated_at ? Date::dateTimeToExcel(new \DateTime($item->updated_at)) : null,
        ])->all();
    }

    protected function totals(): array
    {
        return [[
            'TOTALES', '', '', '', '',
            (int) $this->inventarios->sum('cantidad'),
            '', '',
            (float) $this->inventarios->sum('costo_total'),
            '',
        ]];
    }

    protected function numberFormats(): array
    {
        return [
            'F' => '#,##0',
            'G' => '#,##0',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => NumberFormat::FORMAT_DATE_DDMMYYYY.' hh:mm',
        ];
    }
}
