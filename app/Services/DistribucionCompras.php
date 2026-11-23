<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistribucionCompras
{
    public function __construct(private PlanCompras $plan) {}

    public static function reglas(): array
    {
        return [
            'distribucion' => ['nullable', 'array', 'max:200'],
            'distribucion.*' => ['array', 'max:1000'],
            'distribucion.*.*' => ['nullable', 'integer', 'between:0,2000000'],
        ];
    }

    public function validar(Collection $consolidados, Collection $lineasFactura, array $reparto, bool $historica = false): array
    {
        if (array_diff(array_keys($reparto), $consolidados->pluck('id')->all())) {
            $this->error('Selecciona los consolidados a los que quieres distribuir la factura.');
        }

        if (!$historica && $this->plan->conciliacionesPendientes($consolidados, bloquear: true)) {
            $this->error('Concilia las facturas históricas de los consolidados seleccionados antes de vincular una compra nueva.');
        }

        $avance = $this->plan->avance($consolidados, bloquear: true)->groupBy('consolidado_id');
        $restante = $lineasFactura->pluck('cantidad', 'clave')->all();
        $distribucion = [];

        foreach ($consolidados as $consolidado) {
            $lineas = $avance->get($consolidado->id, collect())->keyBy('codigo');
            $cantidades = array_filter($reparto[$consolidado->id] ?? [], fn ($cantidad) => (int) $cantidad > 0);
            if (!$historica && !$cantidades) {
                $this->error("Indica al menos una cantidad para {$consolidado->nombre}.");
            }
            foreach ($cantidades as $clave => $cantidad) {
                $detalle = $lineasFactura->get($clave);
                $destino = $detalle ? $lineas->get($detalle['codigo']) : null;
                $cantidad = (int) $cantidad;
                if ($destino === null) {
                    $this->error('Distribuye únicamente productos que aparecen en la factura y en el consolidado seleccionado.');
                }
                if ($cantidad > $destino['pendiente']) {
                    $this->error("La cantidad de {$destino['codigo']} supera el pendiente de {$consolidado->nombre}.");
                }
                if ($cantidad > $restante[$clave]) {
                    $this->error("La distribución de {$destino['codigo']} supera la cantidad facturada.");
                }

                $restante[$clave] -= $cantidad;
                $distribucion[] = ['consolidado_linea_id' => $destino['id'], 'clave' => $clave, 'cantidad' => $cantidad];
            }
        }

        return $distribucion;
    }

    public function guardar(int $compra, array $distribucion, array $detalles): void
    {
        foreach ($distribucion as $fila) {
            DB::table('compra_detalle_consolidado')->insert([
                'consolidado_linea_id' => $fila['consolidado_linea_id'],
                'compra_detalle_id' => $detalles[$fila['clave']],
                'cantidad' => $fila['cantidad'],
            ]);
        }
        DB::table('compras')->where('id', $compra)->update(['distribucion_confirmada_en' => now()]);
    }

    private function error(string $mensaje): never
    {
        throw ValidationException::withMessages(['distribucion' => $mensaje]);
    }
}
