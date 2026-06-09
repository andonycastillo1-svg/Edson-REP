<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Shared\Date;

class ColaboradoresFichaExport extends FormattedExcelExport
{
    public function __construct(
        protected array $fichas,
        string $generatedBy,
        array $filters = [],
    ) {
        $title = count($fichas) === 1
            ? 'Ficha técnica de colaborador'
            : 'Fichas técnicas de colaboradores seleccionados';

        parent::__construct($title, $generatedBy, $filters);
    }

    protected function headings(): array
    {
        return [
            'Tipo de registro',
            'Código colaborador',
            'Colaborador',
            'Puesto',
            'Estado colaborador',
            'Código producto',
            'Producto / refacción',
            'Bodega',
            'Cantidad',
            'Costo unitario',
            'Total asignado',
            'Vehículo',
            'Marca',
            'Modelo',
            'Placa',
            'VIN',
            'Fecha asignación',
            'Fecha vencimiento',
            'Estado / vida útil',
            'Motivo',
            'Aplica cobro',
            'Monto cobro',
            'Estado cobro',
            'Detalle / observaciones',
        ];
    }

    protected function rows(): array
    {
        $rows = [];

        foreach ($this->fichas as $ficha) {
            $colaborador = $ficha['colaborador'];
            $base = [
                $colaborador['codigo'],
                $colaborador['nombre'],
                $colaborador['puesto'],
                $colaborador['estado'],
            ];

            if (count($ficha['asignaciones']) > 0) {
                foreach ($ficha['asignaciones'] as $item) {
                    $rows[] = $this->makeRow('Inventario directo', $base, [
                        5 => $item['producto_codigo'] ?? '',
                        6 => $item['producto'] ?? '',
                        7 => $item['bodega'] ?? '',
                        8 => (int) ($item['cantidad'] ?? 0),
                        9 => (float) ($item['costo_unitario'] ?? 0),
                        10 => (float) ($item['total'] ?? 0),
                        16 => $this->excelDate($item['fecha_asignacion'] ?? ''),
                        17 => $this->excelDate($item['fecha_vencimiento'] ?? ''),
                        18 => $item['estado_vida_util'] ?? '',
                    ]);
                }
            } else {
                $rows[] = $this->makeRow('Inventario directo', $base, [
                    6 => 'Sin productos asignados directamente',
                ]);
            }

            $vehiculo = $ficha['vehiculo_asignado'];
            if ($vehiculo) {
                $rows[] = $this->makeRow('Vehículo asignado', $base, [
                    10 => (float) ($ficha['total_productos_vehiculo'] ?? 0),
                    11 => trim(($vehiculo['marca'] ?? '').' '.($vehiculo['modelo'] ?? '')),
                    12 => $vehiculo['marca'] ?? '',
                    13 => $vehiculo['modelo'] ?? '',
                    14 => $vehiculo['placa'] ?? '',
                    15 => $vehiculo['vin'] ?? '',
                    16 => $this->excelDate($vehiculo['fecha_asignacion'] ?? ''),
                    18 => $vehiculo['estado'] ?? '',
                ]);
            } else {
                $rows[] = $this->makeRow('Vehículo asignado', $base, [
                    11 => 'Sin vehículo asignado',
                ]);
            }

            if (count($ficha['productos_vehiculo']) > 0) {
                foreach ($ficha['productos_vehiculo'] as $item) {
                    $rows[] = $this->makeRow('Producto / refacción de vehículo', $base, [
                        5 => $item['producto_codigo'] ?? '',
                        6 => $item['producto'] ?? '',
                        7 => $item['bodega'] ?? '',
                        8 => (int) ($item['cantidad'] ?? 0),
                        9 => (float) ($item['costo_unitario'] ?? 0),
                        10 => (float) ($item['total'] ?? 0),
                        11 => $vehiculo ? trim(($vehiculo['marca'] ?? '').' '.($vehiculo['modelo'] ?? '')) : '',
                        12 => $vehiculo['marca'] ?? '',
                        13 => $vehiculo['modelo'] ?? '',
                        14 => $vehiculo['placa'] ?? '',
                        15 => $vehiculo['vin'] ?? '',
                        16 => $this->excelDate($item['fecha'] ?? ''),
                        18 => $item['estado'] ?? '',
                        19 => $item['motivo'] ?? '',
                        23 => $item['observaciones'] ?? '',
                    ]);
                }
            } else {
                $rows[] = $this->makeRow('Producto / refacción de vehículo', $base, [
                    6 => 'Sin productos o refacciones en vehículo',
                ]);
            }

            if (count($ficha['cobros']) > 0) {
                foreach ($ficha['cobros'] as $cobro) {
                    $rows[] = $this->makeRow('Cobro / descuento RRHH', $base, [
                        5 => $cobro['producto_codigo'] ?? '',
                        6 => $cobro['producto'] ?? '',
                        9 => (float) ($cobro['costo_producto'] ?? 0),
                        16 => $this->excelDate($cobro['fecha_asignacion_anterior'] ?? ''),
                        17 => $this->excelDate($cobro['fecha_dano_reemplazo'] ?? ''),
                        18 => 'Vida útil: '.($cobro['vida_util_meses'] ?? 0).' meses / Restante: '.($cobro['meses_restantes'] ?? 0).' meses',
                        19 => $cobro['motivo'] ?? '',
                        20 => ! empty($cobro['descuento_aplicable']) ? 'Sí' : 'No',
                        21 => (float) ($cobro['monto_cobro'] ?? 0),
                        22 => $cobro['estado'] ?? '',
                        23 => $cobro['detalle'] ?? '',
                    ]);
                }
            } else {
                $rows[] = $this->makeRow('Cobro / descuento RRHH', $base, [
                    6 => 'Sin cobros o descuentos registrados',
                ]);
            }

            $rows[] = $this->makeRow('TOTAL INVENTARIO DIRECTO', $base, [
                10 => (float) ($ficha['total_inventario'] ?? 0),
            ]);
            $rows[] = $this->makeRow('TOTAL PRODUCTOS VEHÍCULO', $base, [
                10 => (float) ($ficha['total_productos_vehiculo'] ?? 0),
            ]);
            $rows[] = $this->makeRow('TOTAL GENERAL ASIGNADO', $base, [
                10 => (float) ($ficha['total_general'] ?? 0),
            ]);
            $rows[] = $this->makeRow('TOTAL COBROS / DESCUENTOS', $base, [
                21 => (float) ($ficha['total_cobros'] ?? 0),
            ]);
        }

        return $rows;
    }

    protected function numberFormats(): array
    {
        return [
            'I' => '#,##0',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
            'Q' => 'dd/mm/yyyy',
            'R' => 'dd/mm/yyyy',
            'V' => '#,##0.00',
        ];
    }

    private function excelDate(mixed $value): float|string
    {
        if ($value === null || $value === '' || $value === '—') {
            return '';
        }

        foreach (['d/m/Y', 'd/m/Y H:i', 'Y-m-d', 'Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, (string) $value);
            if ($date !== false) {
                return Date::dateTimeToExcel($date);
            }
        }

        return (string) $value;
    }

    private function makeRow(string $type, array $base, array $values = []): array
    {
        $row = array_fill(0, 24, '');
        $row[0] = $type;
        $row[1] = $base[0];
        $row[2] = $base[1];
        $row[3] = $base[2];
        $row[4] = $base[3];

        foreach ($values as $index => $value) {
            $row[$index] = $value;
        }

        return $row;
    }
}
