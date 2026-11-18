<?php

namespace App\Sistema;

class CatalogoPermisos
{
    public const AMBITOS = [1 => 'Administrador', 2 => 'Operador', 3 => 'Supervisor', 4 => 'Recursos Humanos'];

    public const CONFIGURACION = [
        'sistema.campos' => 'Administrar campos personalizados',
        'sistema.presentacion' => 'Administrar formularios y tablas',
        'sistema.parametros' => 'Administrar parámetros generales',
        'sistema.notificaciones' => 'Administrar notificaciones',
        'sistema.auditoria' => 'Consultar la bitácora operativa',
    ];

    public static function rutas(int $ambito): array
    {
        $crud = [
            'index' => 'consultar', 'show' => 'consultar', 'create' => 'crear', 'store' => 'crear',
            'edit' => 'modificar', 'update' => 'modificar', 'destroy' => 'eliminar',
        ];
        $asignaciones = [
            'index' => 'consultar', 'create' => 'crear', 'store' => 'crear', 'activas_producto' => 'crear',
            'pdf' => 'exportar', 'hoja_devolucion' => 'exportar', 'upload_pdf_firmado' => 'evidencias',
            'upload_pdf_devolucion_firmado' => 'evidencias', 'ver_pdf_firmado' => 'archivos',
            'devolver' => 'devolver', 'devolver_lote' => 'devolver', 'devolver_todo_colaborador' => 'devolver',
        ];
        $traslados = array_intersect_key($crud, array_flip(['index', 'show', 'create', 'store'])) + [
            'aprobar' => 'aprobar', 'rechazar' => 'aprobar', 'archivo' => 'archivos', 'hoja' => 'exportar',
        ];
        $compras = array_intersect_key($crud, array_flip(['index', 'show', 'create', 'store'])) + ['archivo' => 'archivos'];
        $colaboradores = array_diff_key($crud, array_flip(['show', 'destroy'])) + [
            'detalle' => 'consultar', 'estado' => 'modificar', 'ficha_tecnica' => 'exportar', 'fichas_tecnicas_masivas' => 'exportar',
        ];
        $usuarios = array_replace(array_diff_key($crud, ['show' => true]), ['destroy' => 'desactivar']);

        $definiciones = match ($ambito) {
            1 => [
                'usuarios' => ['usuarios', $usuarios],
                'colaboradores' => ['colaboradores', $colaboradores],
                'bodegas' => ['bodegas', $crud + ['inventario.export' => 'exportar']],
                'compras' => ['compras', $compras],
                'asignaciones' => ['asignaciones', $asignaciones],
                'operaciones.traslados' => ['traslados', $traslados],
                'vehiculos' => ['vehiculos', $crud],
                'vehiculos.asignaciones' => ['vehiculos', [
                    'index' => 'consultar', 'create' => 'asignar', 'store' => 'asignar', 'desasignar' => 'asignar',
                    'pdf_asignacion' => 'exportar', 'pdf_desasignacion' => 'exportar',
                    'subir_pdf_firmado' => 'evidencias', 'ver_pdf_firmado' => 'archivos',
                ]],
                'vehiculos.productos' => ['refacciones', [
                    'index' => 'consultar', 'store' => 'crear', 'cerrar' => 'cerrar', 'ver_pdf_firmado' => 'archivos',
                    'pdf_asignacion' => 'exportar', 'pdf_devolucion' => 'exportar', 'subir_pdf_firmado' => 'evidencias',
                ]],
            ],
            2 => [
                'bodegas' => ['bodegas', ['index' => 'consultar', 'show' => 'consultar', 'inventario.export' => 'exportar']],
                'compras' => ['compras', $compras],
                'asignaciones' => ['asignaciones', $asignaciones],
                'operaciones.traslados' => ['traslados', $traslados],
            ],
            3 => ['asignaciones' => ['asignaciones', array_intersect_key($asignaciones, array_flip([
                'index', 'ver_pdf_firmado', 'pdf', 'upload_pdf_firmado',
            ]))]],
            4 => [
                'usuarios' => ['usuarios', $usuarios],
                'colaboradores' => ['colaboradores', $colaboradores],
                'alertas' => ['alertas', ['index' => 'consultar', 'export' => 'exportar', 'finalizar' => 'modificar']],
            ],
            default => [],
        };

        $prefijo = self::prefijo($ambito);
        if ($prefijo === null) {
            return [];
        }

        $rutas = [$prefijo.'.dashboard' => 'panel.consultar'];
        foreach ($definiciones as $grupo => [$modulo, $acciones]) {
            foreach ($acciones as $ruta => $accion) {
                $rutas[$prefijo.'.'.$grupo.'.'.$ruta] = $modulo.'.'.$accion;
            }
        }

        return $rutas + match ($ambito) {
            1 => ['admin.bodegas.entrada' => 'inventario.entrada', 'admin.bodegas.entrada.store' => 'inventario.entrada'],
            3 => ['coordinador.dashboard' => 'panel.consultar'],
            default => [],
        };
    }

    public static function prefijo(int $ambito): ?string
    {
        return [1 => 'admin', 2 => 'operador', 3 => 'supervisor', 4 => 'rrhh'][$ambito] ?? null;
    }

    public static function disponibles(int $ambito): array
    {
        if (!isset(self::AMBITOS[$ambito])) {
            return [];
        }

        $base = array_values(array_unique(self::rutas($ambito)));
        $extras = array_keys(self::CONFIGURACION);
        foreach (array_keys(Modulos::DEFINICIONES) as $modulo) {
            if (in_array(Modulos::permisoLectura($modulo), $base, true)) {
                $extras[] = $modulo.'.campos_editar';
                $extras[] = $modulo.'.datos_exportar';
                $extras[] = $modulo.'.datos_archivos';
            }
        }
        $permisos = array_values(array_unique([...$base, ...$extras]));
        sort($permisos);

        return $permisos;
    }
}
