<?php

namespace Tests\Support;

use App\Models\AsignacionInventario;
use Illuminate\Support\Str;

trait ConDevolucionesCompletas
{
    private function datosDevolucion(AsignacionInventario $fila, string $condicion = 'buen_estado'): array
    {
        return [
            'solicitud_id' => (string) Str::uuid(),
            'articulos' => [$fila->id => array_replace(
                ['buen_estado' => 0, 'danado' => 0, 'sin_retorno' => 0], [$condicion => $fila->cantidad_asignada],
            )],
        ];
    }
}
