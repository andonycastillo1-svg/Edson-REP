<?php

namespace App\Sistema;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Auditoria
{
    public function __construct(private Permisos $permisos) {}

    public function registrar(string $modulo, string $accion, string|int|null $registro = null, ?array $antes = null, ?array $despues = null, string $resultado = 'aplicado'): void
    {
        if (!$this->permisos->instalado()) {
            return;
        }

        $solicitud = app()->bound('request') ? request()->attributes->get('auditoria_solicitud') : null;
        if ($solicitud === null && app()->bound('request')) {
            request()->attributes->set('auditoria_solicitud', $solicitud = (string) Str::uuid());
        }

        DB::table('sistema_auditoria')->insert([
            'actor_id' => auth()->id(),
            'modulo' => $modulo,
            'accion' => $accion,
            'registro' => $registro,
            'resultado' => $resultado,
            'antes' => $antes === null ? null : json_encode($antes, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
            'despues' => $despues === null ? null : json_encode($despues, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
            'solicitud' => $solicitud,
            'created_at' => now(),
        ]);
    }
}
