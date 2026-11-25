<?php

namespace Tests\Support;

use App\Models\BajaLaboral;
use App\Models\Colaborador;

trait ConBajasLaborales
{
    private function bajaFixture(string $codigo, string $motivo = 'renuncia'): BajaLaboral
    {
        $colaborador = Colaborador::findOrFail($codigo);
        $colaborador->update(['estado' => 'Inactivo']);

        return $colaborador->bajasLaborales()->create([
            'motivo' => $motivo, 'fecha_efectiva' => today(),
        ]);
    }
}
