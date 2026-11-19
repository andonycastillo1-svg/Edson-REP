<?php

namespace App\Sistema;

use Illuminate\Support\Facades\DB;

class Ajustes
{
    public const EVENTOS = [
        'asignacion_creada' => 'Nueva asignación (inventario, vehículos o refacciones)',
        'asignacion_pendiente' => 'Seguimiento de recepción y hoja firmada',
        'asignacion_estado' => 'Cambios en asignaciones y devoluciones',
        'traslado_creado' => 'Solicitud de traslado',
        'traslado_estado' => 'Decisión de traslado',
        'movimiento_inventario' => 'Entradas de inventario y compras',
        'stock_bajo' => 'Stock bajo de bodega',
    ];

    private array $leidos = [];

    public function __construct(private Permisos $permisos, private Auditoria $auditoria) {}

    public function leer(string $clave, mixed $predeterminado = null): mixed
    {
        if (!$this->permisos->instalado()) {
            return $predeterminado;
        }

        if (!array_key_exists($clave, $this->leidos)) {
            $valor = DB::table('sistema_ajustes')->where('clave', $clave)->value('valor');
            $this->leidos[$clave] = $valor === null ? null : json_decode($valor, true, flags: JSON_THROW_ON_ERROR);
        }

        return $this->leidos[$clave] ?? $predeterminado;
    }

    public function refrescar(string $clave): void
    {
        unset($this->leidos[$clave]);
    }

    public function guardar(string $clave, ?array $valor, string $seccion): void
    {
        $anterior = $this->leer($clave);
        if ($valor === null) {
            DB::table('sistema_ajustes')->where('clave', $clave)->delete();
        } else {
            DB::table('sistema_ajustes')->upsert([
                'clave' => $clave, 'valor' => json_encode($valor, JSON_THROW_ON_ERROR),
            ], ['clave'], ['valor']);
        }
        $this->leidos[$clave] = $valor;
        $limpiar = fn (?array $datos) => $datos === null ? null : array_diff_key($datos, ['logo' => true]);
        $this->auditoria->registrar('configuracion', $seccion, $clave, $limpiar($anterior), $limpiar($valor));
    }

    public function general(?string $clave = null, mixed $predeterminado = null): mixed
    {
        $datos = $this->leer('general', []);

        return $clave === null ? $datos : ($datos[$clave] ?? $predeterminado);
    }
}
