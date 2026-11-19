<?php

namespace App\Sistema;

use Illuminate\Support\Str;

class Bitacora
{
    public static function modulo(string $modulo): string
    {
        return Modulos::DEFINICIONES[$modulo][0] ?? match ($modulo) {
            'configuracion' => 'Configuración',
            'seguridad' => 'Seguridad',
            default => Str::headline($modulo),
        };
    }

    public static function accion(string $accion): string
    {
        if (str_starts_with($accion, 'notificacion_')) {
            $evento = substr($accion, strlen('notificacion_'));

            return 'Regla de aviso: '.(Ajustes::EVENTOS[$evento] ?? Str::headline($evento));
        }

        return match ($accion) {
            'parametros' => 'Cambiar parámetros generales',
            'presentacion' => 'Cambiar presentación',
            'crear_campo' => 'Crear campo personalizado',
            'actualizar_campo' => 'Actualizar campo personalizado',
            'archivar_campo' => 'Archivar campo personalizado',
            'opcion_campo' => 'Actualizar opción de lista',
            'guardar_campo' => 'Guardar valor de un campo',
            'crear_rol' => 'Crear rol',
            'actualizar_rol' => 'Actualizar rol',
            'permiso_rol' => 'Cambiar permiso de un rol',
            'permiso_usuario' => 'Cambiar acceso de una cuenta',
            'retirar_permisos_por_cambio_rol' => 'Retirar excepciones al cambiar el rol',
            'superadministrador' => 'Cambiar acceso de Superadministrador',
            'crear_cuenta_administrativa' => 'Crear cuenta administrativa',
            'actualizar_acceso_cuenta' => 'Actualizar acceso de una cuenta',
            'estado_cuenta' => 'Cambiar estado de una cuenta',
            'confirmacion_invalida' => 'Confirmación de seguridad incorrecta',
            default => Str::headline($accion),
        };
    }

    public static function referencia(?string $registro): string
    {
        if ($registro === null || Str::isUuid($registro)) {
            return '—';
        }
        if (str_starts_with($registro, 'presentacion.')) {
            return self::modulo(Str::after($registro, '.'));
        }

        return match ($registro) {
            'general' => 'Parámetros generales',
            'notificaciones' => 'Notificaciones',
            default => $registro,
        };
    }

    public static function detalles(?string $antes, ?string $despues, string $accion): array
    {
        $anterior = $antes ? (array) json_decode($antes, true) : [];
        $actual = $despues ? (array) json_decode($despues, true) : [];
        $etiquetas = [
            'nombre' => 'Nombre', 'empresa' => 'Empresa', 'descripcion' => 'Descripción', 'etiqueta' => 'Etiqueta',
            'ayuda' => 'Ayuda', 'orden' => 'Posición', 'activo' => 'Activo', 'obligatorio' => 'Obligatorio',
            'formulario' => 'Formulario', 'tabla' => 'Visible en tabla', 'filtro' => 'Filtro',
            'exportacion' => 'Incluido en exportación', 'stock_minimo' => 'Nivel mínimo', 'vida_util' => 'Vida útil (meses)',
            'inactividad' => 'Inactividad (minutos)', 'pagina' => 'Registros por página', 'direccion' => 'Sentido del orden',
        ];
        $opciones = [
            'role_id' => 'Rol', 'bodega_id' => 'Bodega', 'bodega_principal' => 'Bodega principal', 'almacenista_id' => 'Almacenista',
            'categorias' => 'Categorías', 'columnas' => 'Columnas', 'ordenar' => 'Columna de ordenamiento',
            'roles' => 'Roles destinatarios', 'usuarios' => 'Cuentas destinatarias',
            'credencial_actualizada' => 'Contraseña', 'permiso_lectura' => 'Acceso adicional de lectura',
            'permiso_edicion' => 'Acceso adicional de edición',
        ];
        $permisos = in_array($accion, ['permiso_usuario', 'permiso_rol', 'retirar_permisos_por_cambio_rol'], true);
        $cambios = [];
        foreach (array_unique([...array_keys($anterior), ...array_keys($actual)]) as $clave) {
            $valorAnterior = $anterior[$clave] ?? null;
            $valorActual = $actual[$clave] ?? null;
            if ($valorAnterior === $valorActual) {
                continue;
            }
            if ($permisos && str_contains((string) $clave, '.')) {
                $cambios[] = [
                    CatalogoPermisos::presentacion($clave)['titulo'],
                    self::regla($valorAnterior),
                    self::regla($valorActual),
                ];
            } elseif (isset($opciones[$clave]) || (isset($etiquetas[$clave]) && (is_array($valorAnterior) || is_array($valorActual)))) {
                $cambios[] = [$opciones[$clave] ?? $etiquetas[$clave], '—', 'Configuración actualizada'];
            } elseif (isset($etiquetas[$clave])) {
                $cambios[] = [$etiquetas[$clave], self::valor($valorAnterior, $clave), self::valor($valorActual, $clave)];
            }
        }

        return $cambios;
    }

    private static function regla(mixed $valor): string
    {
        return $valor === null ? 'Heredar' : ($valor ? 'Permitir' : 'Denegar');
    }

    private static function valor(mixed $valor, string $clave): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }
        if ($clave === 'direccion') {
            return $valor === 'asc' ? 'Ascendente' : 'Descendente';
        }
        if (in_array($clave, ['activo', 'obligatorio', 'formulario', 'tabla', 'filtro', 'exportacion'], true)) {
            return $valor ? 'Sí' : 'No';
        }

        return is_scalar($valor) ? (string) $valor : '—';
    }
}
