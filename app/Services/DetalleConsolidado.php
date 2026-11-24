<?php

namespace App\Services;

use App\Models\Colaborador;
use App\Models\Consolidado;
use App\Models\ConsolidadoColaborador;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DetalleConsolidado
{
    public function inicializar(Consolidado $consolidado): void
    {
        if ($consolidado->detalle_individual) {
            return;
        }

        $inspecciones = $consolidado->inspecciones()->with('lineas')->get();
        $totales = [];
        foreach ($inspecciones->groupBy('colaborador_codigo') as $codigo => $grupo) {
            if ($grupo->pluck('region_id')->unique()->count() > 1) {
                throw ValidationException::withMessages(['inspecciones' => 'Separa las inspecciones de '.$codigo.' que corresponden a regiones diferentes.']);
            }
            $participante = $consolidado->participantes()->create([
                'colaborador_codigo' => (string) $codigo, 'region_id' => $grupo->first()->region_id,
            ]);
            foreach ($grupo->flatMap->lineas->groupBy('producto_codigo') as $producto => $lineas) {
                $cantidad = $lineas->reduce(fn ($suma, $linea) => $this->sumar($suma, $linea->cantidad, 'inspecciones'), 0);
                $participante->necesidades()->create(['producto_codigo' => (string) $producto, 'original' => $cantidad, 'cantidad' => $cantidad]);
                $totales[$producto] = $this->sumar($totales[$producto] ?? 0, $cantidad, 'inspecciones');
            }
        }

        $historicas = $consolidado->lineas()->get();
        if ($inspecciones->isEmpty() || ($historicas->isNotEmpty() && ($historicas->count() !== count($totales)
            || $historicas->contains(fn ($fila) => ($totales[$fila->producto_codigo] ?? null) !== (int) $fila->original || (int) $fila->cantidad !== (int) $fila->original)))) {
            throw ValidationException::withMessages(['inspecciones' => 'El consolidado histórico tiene cantidades sin desglose comprobable. Conserva el resumen y revisa los requerimientos originales.']);
        }

        $this->proyectar($consolidado);
        $consolidado->detalle_individual = true;
    }

    public function ajustar(Consolidado $consolidado, User $usuario, array $datos, Collection $regiones): array
    {
        $participante = $this->participante($consolidado, $usuario, $datos, $regiones);
        $necesidades = $participante->necesidades()->get()->keyBy('id');
        $cambios = [];
        if ($participante->wasRecentlyCreated) {
            $cambios[] = ['colaborador' => $participante->colaborador_codigo, 'region_id' => $participante->region_id, 'incorporado' => true];
        }
        foreach ($datos['cantidades'] ?? [] as $id => $cantidad) {
            $necesidad = $necesidades->get($id);
            if (!$necesidad) {
                throw ValidationException::withMessages(['cantidades' => 'Selecciona productos del colaborador que estás revisando.']);
            }
            if ($necesidad->cantidad !== (int) $cantidad) {
                $cambios[] = $this->cambio($participante, $necesidad->producto_codigo, $necesidad->cantidad, (int) $cantidad);
                $necesidad->update(['cantidad' => $cantidad]);
            }
        }
        if (filled($datos['producto_codigo'] ?? null)) {
            $codigo = Producto::findOrFail($datos['producto_codigo'])->codigo;
            if ($necesidades->contains('producto_codigo', $codigo)) {
                throw ValidationException::withMessages(['producto_codigo' => 'El producto ya está incluido. Edita su cantidad en la ficha del colaborador.']);
            }
            $cantidad = (int) $datos['cantidad_nueva'];
            $participante->necesidades()->create(['producto_codigo' => $codigo, 'original' => 0, 'cantidad' => $cantidad]);
            $cambios[] = $this->cambio($participante, $codigo, 0, $cantidad) + ['producto_agregado' => true];
        }

        $this->proyectar($consolidado);

        return $cambios;
    }

    private function participante(Consolidado $consolidado, User $usuario, array $datos, Collection $regiones): ConsolidadoColaborador
    {
        if (!empty($datos['participante_id'])) {
            return $consolidado->participantes()->findOrFail($datos['participante_id']);
        }

        $codigo = $datos['colaborador_codigo'] ?? null;
        $participante = $consolidado->participantes()->where('colaborador_codigo', $codigo)->first();
        if ($participante) {
            return $participante;
        }

        if (blank($datos['producto_codigo'] ?? null)) {
            throw ValidationException::withMessages(['producto_codigo' => 'Selecciona al menos un producto para incorporar al colaborador.']);
        }

        $colaborador = Colaborador::whereKey($codigo)->where('estado', 'Activo')->lockForUpdate()->first();
        if (!$colaborador || !$regiones->contains($colaborador->region_id)) {
            throw ValidationException::withMessages(['colaborador_codigo' => 'Selecciona un colaborador activo de tus regiones autorizadas.']);
        }

        return $consolidado->participantes()->create([
            'colaborador_codigo' => $colaborador->codigo, 'region_id' => $colaborador->region_id, 'agregado_por' => $usuario->id,
        ]);
    }

    private function cambio(ConsolidadoColaborador $participante, string $producto, int $antes, int $nuevo): array
    {
        return ['colaborador' => $participante->colaborador_codigo, 'region_id' => $participante->region_id,
            'producto' => $producto, 'antes' => $antes, 'nuevo' => $nuevo];
    }

    private function proyectar(Consolidado $consolidado): void
    {
        $resumen = [];
        foreach ($consolidado->participantes()->with('necesidades')->lazyById() as $participante) {
            foreach ($participante->necesidades as $fila) {
                $codigo = $fila->producto_codigo;
                $anterior = $resumen[$codigo] ?? ['original' => 0, 'cantidad' => 0];
                $resumen[$codigo] = [
                    'original' => $this->sumar($anterior['original'], $fila->original, 'inspecciones'),
                    'cantidad' => $this->sumar($anterior['cantidad'], $fila->cantidad, 'cantidades'),
                ];
            }
        }
        foreach ($resumen as $codigo => $valores) {
            $consolidado->lineas()->updateOrCreate(['producto_codigo' => (string) $codigo], $valores);
        }
    }

    private function sumar(int $anterior, int $cantidad, string $campo): int
    {
        if ($cantidad > PHP_INT_MAX - $anterior) {
            throw ValidationException::withMessages([$campo => 'El total supera la capacidad de almacenamiento. Reduce las cantidades o separa las inspecciones.']);
        }

        return $anterior + $cantidad;
    }
}
