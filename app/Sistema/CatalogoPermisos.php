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

    public static function presentacion(string $permiso): array
    {
        [$modulo, $accion] = explode('.', $permiso, 2);
        $grupo = Modulos::DEFINICIONES[$modulo][0] ?? match ($modulo) {
            'panel' => 'Panel principal',
            'alertas' => 'Incidencias para revisión de RR. HH.',
            'sistema' => 'Configuración del sistema',
            default => 'Otros permisos',
        };
        $recurso = mb_strtolower($grupo);
        [$titulo, $descripcion] = match ($permiso) {
            'panel.consultar' => ['Abrir el panel principal', 'Ver los accesos disponibles para el ámbito.'],
            'inventario.entrada' => ['Registrar entradas de inventario', 'Agregar existencias mediante una entrada manual.'],
            'usuarios.crear' => ['Crear cuentas de usuarios', 'Dar de alta cuentas dentro del ámbito autorizado.'],
            'usuarios.desactivar' => ['Desactivar cuentas de usuarios', 'Suspender el inicio de sesión de una cuenta.'],
            'asignaciones.crear' => ['Asignar inventario', 'Entregar equipo a colaboradores y registrar la asignación.'],
            'asignaciones.entregar_nuevo' => ['Entregar inventario nuevo por excepción', 'Autorizar una salida de inventario nuevo y registrar el motivo.'],
            'asignaciones.devolver' => ['Registrar devoluciones', 'Registrar el retorno parcial o total de equipo asignado.'],
            'traslados.aprobar' => ['Resolver solicitudes de traslado', 'Aprobar o rechazar traslados de la bodega correspondiente.'],
            'alertas.modificar' => ['Gestionar incidencias de RR. HH.', 'Registrar notas de seguimiento y finalizar incidencias.'],
            'vehiculos.asignar' => ['Asignar y desasignar vehículos', 'Registrar la entrega y el retorno de unidades.'],
            'refacciones.crear' => ['Asignar refacciones', 'Registrar refacciones entregadas a un vehículo.'],
            'refacciones.cerrar' => ['Cerrar asignaciones de refacciones', 'Registrar el cierre o la devolución de refacciones.'],
            'sistema.campos' => [self::CONFIGURACION[$permiso], 'Definir campos, opciones y visibilidad por módulo.'],
            'sistema.presentacion' => [self::CONFIGURACION[$permiso], 'Configurar globalmente las columnas y los campos editables.'],
            'sistema.parametros' => [self::CONFIGURACION[$permiso], 'Editar identidad, bodega principal y parámetros de sesión según la autorización.'],
            'sistema.notificaciones' => [self::CONFIGURACION[$permiso], 'Configurar avisos y sus destinatarios.'],
            'sistema.auditoria' => [self::CONFIGURACION[$permiso], 'Revisar el historial de cambios y eventos autorizados.'],
            default => match ($accion) {
                'consultar' => ["Consultar $recurso", 'Ver listados y registros autorizados.'],
                'crear' => ["Registrar $recurso", 'Guardar registros nuevos en el módulo.'],
                'modificar' => ["Editar $recurso", 'Actualizar los registros existentes.'],
                'eliminar' => ["Eliminar registros de $recurso", 'Realizar las bajas que admite el módulo.'],
                'exportar' => ["Exportar documentos de $recurso", 'Descargar los reportes o comprobantes propios del módulo.'],
                'archivos' => ["Ver documentos de $recurso", 'Abrir archivos y comprobantes adjuntos.'],
                'evidencias' => ["Adjuntar evidencias de $recurso", 'Cargar el documento firmado de la operación.'],
                'campos_editar' => ["Editar campos de $recurso", 'Actualizar campos personalizados autorizados desde el módulo operativo.'],
                'datos_exportar' => ["Exportar datos de $recurso", 'Descargar el listado de datos y los campos habilitados para exportación.'],
                'datos_archivos' => ["Ver archivos personalizados de $recurso", 'Descargar archivos de campos personalizados en la ficha de datos.'],
                default => ["Administrar $recurso", 'Usar la operación autorizada de este módulo.'],
            },
        };

        $categoria = $modulo === 'sistema' ? 'Administración del sistema' : match ($accion) {
            'consultar' => 'Consulta',
            'archivos', 'evidencias', 'datos_archivos', 'exportar', 'datos_exportar' => 'Documentos y reportes',
            default => 'Operaciones',
        };

        return compact('grupo', 'categoria', 'titulo', 'descripcion');
    }

    public static function rutas(int $ambito): array
    {
        $crud = [
            'index' => 'consultar', 'show' => 'consultar', 'create' => 'crear', 'store' => 'crear',
            'edit' => 'modificar', 'update' => 'modificar', 'destroy' => 'eliminar',
        ];
        $asignaciones = [
            'index' => 'consultar', 'create' => 'crear', 'store' => 'crear',
            'pdf' => 'exportar', 'hoja_devolucion' => 'exportar', 'upload_pdf_firmado' => 'evidencias',
            'upload_pdf_devolucion_firmado' => 'evidencias', 'ver_pdf_firmado' => 'archivos',
            'entrega' => 'consultar', 'documento_entrega' => 'evidencias', 'confirmar_recepcion' => 'crear',
            'incidencia' => 'consultar', 'documento_incidencia' => 'evidencias', 'archivo_incidencia' => 'archivos',
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
                'index', 'ver_pdf_firmado', 'pdf', 'upload_pdf_firmado', 'entrega', 'documento_entrega',
                'incidencia', 'documento_incidencia', 'archivo_incidencia',
            ]))]],
            4 => [
                'usuarios' => ['usuarios', $usuarios],
                'colaboradores' => ['colaboradores', $colaboradores],
                'alertas' => ['alertas', [
                    'index' => 'consultar', 'show' => 'consultar', 'evidencia' => 'consultar',
                    'documento' => 'consultar', 'documento_refaccion' => 'consultar',
                    'export' => 'exportar', 'actuacion' => 'modificar', 'regularizacion' => 'modificar', 'finalizar' => 'modificar',
                ]],
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
        if (in_array('asignaciones.crear', $base, true)) {
            $extras[] = 'asignaciones.entregar_nuevo';
        }
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
