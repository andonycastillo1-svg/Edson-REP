<?php

namespace App\Exports;

use App\Models\AlertaReemplazo;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AlertasRrhhExport implements WithMultipleSheets
{
    public function __construct(private Collection $alertas, private string $generatedBy, private array $filters = []) {}

    public function sheets(): array
    {
        $resumen = collect(AlertaReemplazo::ESTADOS)->map(fn ($estado, $clave) => [
            $estado, $this->alertas->where('estado', $clave)->count(),
        ])->values()->all();

        return [
            new ResumenIncidenciasExport($resumen, $this->alertas->count(), $this->generatedBy, $this->filters),
            new DetalleIncidenciasExport($this->alertas, $this->generatedBy, $this->filters),
        ];
    }
}
