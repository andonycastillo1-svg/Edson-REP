<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitudInventarioService
{
    public static function registrarDevolucion(string $clave): void
    {
        if (DB::table('solicitudes_inventario')->where('user_id', auth()->id())->where('clave', $clave)
            ->whereIn('operacion', ['devolucion_individual', 'devolucion_lote', 'devolucion_total'])->exists()) {
            throw ValidationException::withMessages(['solicitud_id' => 'Esta operación ya fue procesada.']);
        }

        self::registrar('devolucion', $clave);
    }

    public static function registrar(string $operacion, ?string $clave): void
    {
        if ($clave === null) {
            return;
        }

        $insertada = DB::table('solicitudes_inventario')->insertOrIgnore([
            'user_id' => auth()->id(),
            'operacion' => $operacion,
            'clave' => $clave,
            'created_at' => now(),
        ]);

        if ($insertada !== 1) {
            throw ValidationException::withMessages(['solicitud_id' => 'Esta operación ya fue procesada.']);
        }
    }
}
