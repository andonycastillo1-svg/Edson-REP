<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\InventarioController;
use App\Http\Controllers\Admin\OperacionTrasladoController;
use App\Http\Controllers\Admin\ColaboradorController;
use App\Http\Controllers\Admin\AsignacionInventarioController;
use App\Http\Controllers\Admin\AsignacionVehiculoController;
use App\Http\Controllers\Admin\VehiculoProductoController;
use App\Http\Controllers\RrhhDashboardController;
use App\Http\Controllers\NotificacionController;

/*
|--------------------------------------------------------------------------
| Ruta principal
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Dashboard general (redirige según rol)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    $usuario = auth()->user();
    $permiso = app(\App\Sistema\Permisos::class);
    $prefijo = \App\Sistema\CatalogoPermisos::prefijo($usuario->rolOperativo());
    if ($prefijo && $permiso->permite($usuario, 'panel.consultar')) {
        return redirect()->route($prefijo.'.dashboard');
    }
    if ($permiso->esSuperadministrador($usuario) || collect(array_keys(\App\Sistema\CatalogoPermisos::CONFIGURACION))
        ->contains(fn ($clave) => $permiso->permite($usuario, $clave))) {
        return redirect()->route('sistema.configuracion');
    }

    return $prefijo ? redirect()->route('sistema.datos') : redirect()->route('login');
})->middleware(['auth', 'auto.logout'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Dashboards base por rol
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'auto.logout', 'role:3'])->group(function () {
    Route::view('/supervisor/dashboard', 'supervisor.dashboard')->name('supervisor.dashboard');

    // Alias legado: se conserva coordinador.* para enlaces o marcadores existentes.
    Route::redirect('/coordinador/dashboard', '/supervisor/dashboard')->name('coordinador.dashboard');
});

/*
|--------------------------------------------------------------------------
| Perfil (Breeze)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'auto.logout'])->group(function () {
    Route::get('/profile', fn () => redirect()->route('dashboard'))->name('profile.edit');
    Route::get('/profile/edit', fn () => redirect()->route('dashboard'));

    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::patch('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasLeidas'])->name('notificaciones.leer-todas');
    Route::patch('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida'])->name('notificaciones.leer');
    Route::delete('/notificaciones', [NotificacionController::class, 'eliminarTodas'])->name('notificaciones.eliminar-todas');
    Route::delete('/notificaciones/{id}', [NotificacionController::class, 'eliminar'])->name('notificaciones.eliminar');
});

/*
|--------------------------------------------------------------------------
| Rutas Admin (solo role_id = 1)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'auto.logout', 'role:1'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::view('/dashboard', 'admin.dashboard')->name('dashboard');

        Route::resource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class)->except('show');
        Route::get('usuarios/{usuario}/regiones', [\App\Http\Controllers\Admin\CoordinadorRegionController::class, 'edit'])->name('usuarios.regiones');
        Route::put('usuarios/{usuario}/regiones', [\App\Http\Controllers\Admin\CoordinadorRegionController::class, 'update'])->name('usuarios.regiones.update');
        Route::resource('bodegas', \App\Http\Controllers\Admin\BodegaController::class);

        Route::get('bodegas/{bodega}/inventario-export', [\App\Http\Controllers\Admin\BodegaController::class, 'exportInventario'])
            ->name('bodegas.inventario.export');

        Route::get('colaboradores/{colaborador}/detalle', [ColaboradorController::class, 'detalle'])
            ->name('colaboradores.detalle');
        Route::get('colaboradores/{colaborador}/expediente', [ColaboradorController::class, 'expediente'])
            ->name('colaboradores.expediente');

        Route::get('colaboradores/{colaborador}/ficha-tecnica', [ColaboradorController::class, 'fichaTecnica'])
            ->name('colaboradores.ficha_tecnica');

        Route::post('colaboradores/fichas-tecnicas-masivas', [ColaboradorController::class, 'fichasTecnicasMasivas'])
            ->name('colaboradores.fichas_tecnicas_masivas');

        Route::patch('colaboradores/{colaborador}/estado', [ColaboradorController::class, 'cambiarEstado'])
            ->name('colaboradores.estado');

        Route::resource('colaboradores', ColaboradorController::class)
            ->except(['show', 'destroy'])->parameters(['colaboradores' => 'colaborador']);

        /*
        |--------------------------------------------------------------------------
        | Vehículos
        |--------------------------------------------------------------------------
        */
        Route::resource('vehiculos', \App\Http\Controllers\Admin\VehiculoController::class);

        Route::get('vehiculos-asignaciones', [AsignacionVehiculoController::class, 'index'])
            ->name('vehiculos.asignaciones.index');

        Route::get('vehiculos-asignaciones/create', [AsignacionVehiculoController::class, 'create'])
            ->name('vehiculos.asignaciones.create');

        Route::post('vehiculos-asignaciones', [AsignacionVehiculoController::class, 'store'])
            ->name('vehiculos.asignaciones.store');

        Route::post('vehiculos-asignaciones/{asignacion}/desasignar', [AsignacionVehiculoController::class, 'desasignar'])
            ->name('vehiculos.asignaciones.desasignar');

        Route::get('vehiculos-asignaciones/{asignacion}/pdf-asignacion', [AsignacionVehiculoController::class, 'pdfAsignacion'])
            ->name('vehiculos.asignaciones.pdf_asignacion');

        Route::get('vehiculos-asignaciones/{asignacion}/pdf-desasignacion', [AsignacionVehiculoController::class, 'pdfDesasignacion'])
            ->name('vehiculos.asignaciones.pdf_desasignacion');

        /*
        |--------------------------------------------------------------------------
        | PDF firmados de asignación/devolución de vehículos
        |--------------------------------------------------------------------------
        */
        Route::post('vehiculos-asignaciones/{asignacion}/pdf-firmado', [AsignacionVehiculoController::class, 'subirPdfFirmado'])
            ->name('vehiculos.asignaciones.subir_pdf_firmado');

        Route::get('vehiculos-asignaciones/archivos/{archivo}/ver', [AsignacionVehiculoController::class, 'verPdfFirmado'])
            ->name('vehiculos.asignaciones.ver_pdf_firmado');

        /*
        |--------------------------------------------------------------------------
        | Productos / refacciones asignadas a vehículo
        |--------------------------------------------------------------------------
        */
        Route::get('vehiculos-productos', [VehiculoProductoController::class, 'index'])
            ->name('vehiculos.productos.index');

        Route::post('vehiculos-productos', [VehiculoProductoController::class, 'store'])
            ->name('vehiculos.productos.store');

        Route::post('vehiculos-productos/{asignacion}/cerrar', [VehiculoProductoController::class, 'cerrar'])
            ->name('vehiculos.productos.cerrar');

        Route::get('vehiculos-productos/{asignacion}/pdf-asignacion', [VehiculoProductoController::class, 'pdfAsignacion'])
            ->name('vehiculos.productos.pdf_asignacion');

        Route::get('vehiculos-productos/{asignacion}/pdf-devolucion', [VehiculoProductoController::class, 'pdfDevolucion'])
            ->name('vehiculos.productos.pdf_devolucion');

        /*
        |--------------------------------------------------------------------------
        | PDF firmados de productos/refacciones de vehículos
        |--------------------------------------------------------------------------
        */
        Route::post('vehiculos-productos/{asignacion}/pdf-firmado', [VehiculoProductoController::class, 'subirPdfFirmado'])
            ->name('vehiculos.productos.subir_pdf_firmado');

        Route::get('vehiculos-productos/archivos/{archivo}/ver', [VehiculoProductoController::class, 'verPdfFirmado'])
            ->name('vehiculos.productos.ver_pdf_firmado');

        /*
        |--------------------------------------------------------------------------
        | Compras
        |--------------------------------------------------------------------------
        */
        Route::resource('compras', \App\Http\Controllers\Admin\CompraController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::get('compras/{compra}/archivos/{archivo}', [\App\Http\Controllers\Admin\CompraController::class, 'archivo'])
            ->name('compras.archivo');
        Route::post('compras/{compra}/conciliar', [\App\Http\Controllers\Admin\CompraController::class, 'conciliar'])
            ->whereNumber('compra')->name('compras.conciliar');

        /*
        |--------------------------------------------------------------------------
        | Inventario / bodegas
        |--------------------------------------------------------------------------
        */
        Route::get('bodegas/{bodega}/entrada', [InventarioController::class, 'createEntrada'])
            ->name('bodegas.entrada');

        Route::post('bodegas/{bodega}/entrada', [InventarioController::class, 'storeEntrada'])
            ->name('bodegas.entrada.store');

        /*
        |--------------------------------------------------------------------------
        | Operaciones / traslados
        |--------------------------------------------------------------------------
        */
        Route::get('operaciones/traslados', [OperacionTrasladoController::class, 'index'])
            ->name('operaciones.traslados.index');

        Route::get('operaciones/traslados/create', [OperacionTrasladoController::class, 'create'])
            ->name('operaciones.traslados.create');

        Route::post('operaciones/traslados', [OperacionTrasladoController::class, 'store'])
            ->name('operaciones.traslados.store');

        Route::get('operaciones/traslados/{operacion}', [OperacionTrasladoController::class, 'show'])
            ->name('operaciones.traslados.show');

        Route::post('operaciones/traslados/{operacion}/aprobar', [OperacionTrasladoController::class, 'aprobar'])
            ->name('operaciones.traslados.aprobar');

        Route::post('operaciones/traslados/{operacion}/rechazar', [OperacionTrasladoController::class, 'rechazar'])
            ->name('operaciones.traslados.rechazar');

        Route::get('operaciones/traslados/{operacion}/hoja', [OperacionTrasladoController::class, 'hoja'])
            ->name('operaciones.traslados.hoja');

        Route::get('operaciones/traslados/{operacion}/archivo-excel', [OperacionTrasladoController::class, 'archivo'])
            ->name('operaciones.traslados.archivo');

        /*
        |--------------------------------------------------------------------------
        | Asignaciones de inventario
        |--------------------------------------------------------------------------
        */
        Route::get('asignaciones/contexto', [AsignacionInventarioController::class, 'contexto'])->name('asignaciones.contexto');
        Route::get('asignaciones/participantes', [AsignacionInventarioController::class, 'participantes'])->name('asignaciones.participantes');
        Route::get('asignaciones/create', [AsignacionInventarioController::class, 'create'])
            ->name('asignaciones.create');

        Route::get('asignaciones', [AsignacionInventarioController::class, 'index'])
            ->name('asignaciones.index');

        Route::get('asignaciones/historial', [AsignacionInventarioController::class, 'historial'])->name('asignaciones.historial');
        Route::get('asignaciones/colaborador/{codigo}', [AsignacionInventarioController::class, 'colaborador'])->name('asignaciones.colaborador');
        Route::get('asignaciones/articulos/{asignacion}', [AsignacionInventarioController::class, 'articulo'])->name('asignaciones.articulo');

        Route::post('asignaciones', [AsignacionInventarioController::class, 'store'])
            ->name('asignaciones.store');

        Route::post('asignaciones/entregas/{grupo}/recepcion', [AsignacionInventarioController::class, 'confirmarRecepcion'])
            ->whereUuid('grupo')->name('asignaciones.confirmar_recepcion');

        Route::get('asignaciones/entregas/{grupo}', [AsignacionInventarioController::class, 'entrega'])
            ->whereUuid('grupo')->name('asignaciones.entrega');
        Route::post('asignaciones/entregas/{grupo}/documentos', [AsignacionInventarioController::class, 'subirDocumentoEntrega'])
            ->whereUuid('grupo')->name('asignaciones.documento_entrega');
        Route::get('asignaciones/incidencias/{incidencia}', [AsignacionInventarioController::class, 'verIncidencia'])
            ->name('asignaciones.incidencia');
        Route::get('asignaciones/incidencias/{incidencia}/archivo', [AsignacionInventarioController::class, 'archivoIncidencia'])
            ->name('asignaciones.archivo_incidencia');
        Route::post('asignaciones/incidencias/{incidencia}/archivo', [AsignacionInventarioController::class, 'subirArchivoIncidencia'])
            ->name('asignaciones.documento_incidencia');

        Route::get('asignaciones/colaborador/{codigo}/pdf', [AsignacionInventarioController::class, 'pdf'])
            ->name('asignaciones.pdf');

        Route::get('asignaciones/devoluciones/{grupo}', [AsignacionInventarioController::class, 'hojaDevolucion'])
            ->name('asignaciones.hoja_devolucion');

        Route::post('asignaciones/{asignacion}/pdf-firmado', [AsignacionInventarioController::class, 'uploadPdfFirmado'])
            ->name('asignaciones.upload_pdf_firmado');

        Route::post('asignaciones/devoluciones/{grupo}/pdf-firmado', [AsignacionInventarioController::class, 'uploadPdfDevolucionFirmado'])
            ->name('asignaciones.upload_pdf_devolucion_firmado');

        Route::get('asignaciones/archivos/{archivo}/ver', [AsignacionInventarioController::class, 'verPdfFirmado'])
            ->name('asignaciones.ver_pdf_firmado');

        Route::post('asignaciones/{asignacion}/devolver', [AsignacionInventarioController::class, 'rechazarDevolucionParcial'])
            ->name('asignaciones.devolver');

        Route::post('asignaciones/devolver-lote', [AsignacionInventarioController::class, 'rechazarDevolucionParcial'])
            ->name('asignaciones.devolver_lote');

        Route::post('asignaciones/colaborador/{codigo}/devolver-todo', [AsignacionInventarioController::class, 'registrarDevolucionCompleta'])
            ->name('asignaciones.devolver_todo_colaborador');
    });

/*
|--------------------------------------------------------------------------
| Rutas Operador (solo role_id = 2)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'auto.logout', 'role:2'])
    ->prefix('operador')
    ->name('operador.')
    ->group(function () {

        Route::view('/dashboard', 'operador.dashboard')->name('dashboard');

        Route::resource('bodegas', \App\Http\Controllers\Admin\BodegaController::class)
            ->only(['index', 'show']);

        Route::get('bodegas/{bodega}/inventario-export', [\App\Http\Controllers\Admin\BodegaController::class, 'exportInventario'])
            ->name('bodegas.inventario.export');

        Route::resource('compras', \App\Http\Controllers\Admin\CompraController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::get('compras/{compra}/archivos/{archivo}', [\App\Http\Controllers\Admin\CompraController::class, 'archivo'])
            ->name('compras.archivo');
        Route::post('compras/{compra}/conciliar', [\App\Http\Controllers\Admin\CompraController::class, 'conciliar'])
            ->whereNumber('compra')->name('compras.conciliar');

        Route::get('asignaciones/contexto', [AsignacionInventarioController::class, 'contexto'])->name('asignaciones.contexto');
        Route::get('asignaciones/participantes', [AsignacionInventarioController::class, 'participantes'])->name('asignaciones.participantes');
        Route::get('asignaciones/create', [AsignacionInventarioController::class, 'create'])
            ->name('asignaciones.create');

        Route::get('asignaciones', [AsignacionInventarioController::class, 'index'])
            ->name('asignaciones.index');

        Route::get('asignaciones/historial', [AsignacionInventarioController::class, 'historial'])->name('asignaciones.historial');
        Route::get('asignaciones/colaborador/{codigo}', [AsignacionInventarioController::class, 'colaborador'])->name('asignaciones.colaborador');
        Route::get('asignaciones/articulos/{asignacion}', [AsignacionInventarioController::class, 'articulo'])->name('asignaciones.articulo');

        Route::post('asignaciones', [AsignacionInventarioController::class, 'store'])
            ->name('asignaciones.store');

        Route::post('asignaciones/entregas/{grupo}/recepcion', [AsignacionInventarioController::class, 'confirmarRecepcion'])
            ->whereUuid('grupo')->name('asignaciones.confirmar_recepcion');

        Route::get('asignaciones/entregas/{grupo}', [AsignacionInventarioController::class, 'entrega'])
            ->whereUuid('grupo')->name('asignaciones.entrega');
        Route::post('asignaciones/entregas/{grupo}/documentos', [AsignacionInventarioController::class, 'subirDocumentoEntrega'])
            ->whereUuid('grupo')->name('asignaciones.documento_entrega');
        Route::get('asignaciones/incidencias/{incidencia}', [AsignacionInventarioController::class, 'verIncidencia'])
            ->name('asignaciones.incidencia');
        Route::get('asignaciones/incidencias/{incidencia}/archivo', [AsignacionInventarioController::class, 'archivoIncidencia'])
            ->name('asignaciones.archivo_incidencia');
        Route::post('asignaciones/incidencias/{incidencia}/archivo', [AsignacionInventarioController::class, 'subirArchivoIncidencia'])
            ->name('asignaciones.documento_incidencia');

        Route::get('asignaciones/colaborador/{codigo}/pdf', [AsignacionInventarioController::class, 'pdf'])
            ->name('asignaciones.pdf');

        Route::get('asignaciones/devoluciones/{grupo}', [AsignacionInventarioController::class, 'hojaDevolucion'])
            ->name('asignaciones.hoja_devolucion');

        Route::post('asignaciones/{asignacion}/pdf-firmado', [AsignacionInventarioController::class, 'uploadPdfFirmado'])
            ->name('asignaciones.upload_pdf_firmado');

        Route::post('asignaciones/devoluciones/{grupo}/pdf-firmado', [AsignacionInventarioController::class, 'uploadPdfDevolucionFirmado'])
            ->name('asignaciones.upload_pdf_devolucion_firmado');

        Route::get('asignaciones/archivos/{archivo}/ver', [AsignacionInventarioController::class, 'verPdfFirmado'])
            ->name('asignaciones.ver_pdf_firmado');

        Route::post('asignaciones/{asignacion}/devolver', [AsignacionInventarioController::class, 'rechazarDevolucionParcial'])
            ->name('asignaciones.devolver');

        Route::post('asignaciones/devolver-lote', [AsignacionInventarioController::class, 'rechazarDevolucionParcial'])
            ->name('asignaciones.devolver_lote');

        Route::post('asignaciones/colaborador/{codigo}/devolver-todo', [AsignacionInventarioController::class, 'registrarDevolucionCompleta'])
            ->name('asignaciones.devolver_todo_colaborador');

        Route::get('operaciones/traslados', [OperacionTrasladoController::class, 'index'])
            ->name('operaciones.traslados.index');

        Route::get('operaciones/traslados/create', [OperacionTrasladoController::class, 'create'])
            ->name('operaciones.traslados.create');

        Route::post('operaciones/traslados', [OperacionTrasladoController::class, 'store'])
            ->name('operaciones.traslados.store');

        Route::get('operaciones/traslados/{operacion}', [OperacionTrasladoController::class, 'show'])
            ->name('operaciones.traslados.show');

        Route::post('operaciones/traslados/{operacion}/aprobar', [OperacionTrasladoController::class, 'aprobar'])
            ->name('operaciones.traslados.aprobar');

        Route::post('operaciones/traslados/{operacion}/rechazar', [OperacionTrasladoController::class, 'rechazar'])
            ->name('operaciones.traslados.rechazar');

        Route::get('operaciones/traslados/{operacion}/hoja', [OperacionTrasladoController::class, 'hoja'])
            ->name('operaciones.traslados.hoja');

        Route::get('operaciones/traslados/{operacion}/archivo-excel', [OperacionTrasladoController::class, 'archivo'])
            ->name('operaciones.traslados.archivo');
    });

/*
|--------------------------------------------------------------------------
| Rutas Supervisor (role_id = 3; antes Coordinador)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'auto.logout', 'role:3'])
    ->prefix('supervisor')
    ->name('supervisor.')
    ->group(function () {
        Route::get('asignaciones', [AsignacionInventarioController::class, 'index'])
            ->name('asignaciones.index');

        Route::get('asignaciones/historial', [AsignacionInventarioController::class, 'historial'])->name('asignaciones.historial');
        Route::get('asignaciones/colaborador/{codigo}', [AsignacionInventarioController::class, 'colaborador'])->name('asignaciones.colaborador');
        Route::get('asignaciones/articulos/{asignacion}', [AsignacionInventarioController::class, 'articulo'])->name('asignaciones.articulo');

        Route::get('asignaciones/entregas/{grupo}', [AsignacionInventarioController::class, 'entrega'])
            ->whereUuid('grupo')->name('asignaciones.entrega');
        Route::post('asignaciones/entregas/{grupo}/documentos', [AsignacionInventarioController::class, 'subirDocumentoEntrega'])
            ->whereUuid('grupo')->name('asignaciones.documento_entrega');
        Route::get('asignaciones/incidencias/{incidencia}', [AsignacionInventarioController::class, 'verIncidencia'])
            ->name('asignaciones.incidencia');
        Route::get('asignaciones/incidencias/{incidencia}/archivo', [AsignacionInventarioController::class, 'archivoIncidencia'])
            ->name('asignaciones.archivo_incidencia');
        Route::post('asignaciones/incidencias/{incidencia}/archivo', [AsignacionInventarioController::class, 'subirArchivoIncidencia'])
            ->name('asignaciones.documento_incidencia');

        Route::get('asignaciones/colaborador/{codigo}/pdf', [AsignacionInventarioController::class, 'pdf'])
            ->name('asignaciones.pdf');

        Route::post('asignaciones/{asignacion}/pdf-firmado', [AsignacionInventarioController::class, 'uploadPdfFirmado'])
            ->name('asignaciones.upload_pdf_firmado');

        Route::get('asignaciones/archivos/{archivo}/ver', [AsignacionInventarioController::class, 'verPdfFirmado'])
            ->name('asignaciones.ver_pdf_firmado');
    });

/*
|--------------------------------------------------------------------------
| Rutas RRHH (solo role_id = 4)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'auto.logout', 'role:4'])
    ->prefix('rrhh')
    ->name('rrhh.')
    ->group(function () {

        Route::get('/dashboard', [RrhhDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/alertas', [RrhhDashboardController::class, 'alertas'])
            ->name('alertas.index');

        Route::get('/alertas/export', [RrhhDashboardController::class, 'export'])
            ->name('alertas.export');

        Route::get('/alertas/{alerta}', [RrhhDashboardController::class, 'show'])->name('alertas.show');
        Route::get('/alertas/{alerta}/evidencia', [RrhhDashboardController::class, 'evidencia'])->name('alertas.evidencia');
        Route::get('/alertas/{alerta}/documentos/{archivo}', [RrhhDashboardController::class, 'documento'])->name('alertas.documento');
        Route::get('/alertas/{alerta}/refacciones/{archivo}', [RrhhDashboardController::class, 'documentoRefaccion'])->name('alertas.documento_refaccion');
        Route::post('/alertas/{alerta}/regularizacion', [RrhhDashboardController::class, 'regularizacion'])->name('alertas.regularizacion');
        Route::post('/alertas/{alerta}/actuaciones', [RrhhDashboardController::class, 'actuacion'])->name('alertas.actuacion');

        Route::post('/alertas/{alerta}/finalizar', [RrhhDashboardController::class, 'finalizar'])
            ->name('alertas.finalizar');

        Route::resource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class)->except('show');
        Route::get('usuarios/{usuario}/regiones', [\App\Http\Controllers\Admin\CoordinadorRegionController::class, 'edit'])->name('usuarios.regiones');
        Route::put('usuarios/{usuario}/regiones', [\App\Http\Controllers\Admin\CoordinadorRegionController::class, 'update'])->name('usuarios.regiones.update');

        Route::get('colaboradores/{colaborador}/detalle', [ColaboradorController::class, 'detalle'])
            ->name('colaboradores.detalle');
        Route::get('colaboradores/{colaborador}/expediente', [ColaboradorController::class, 'expediente'])
            ->name('colaboradores.expediente');

        Route::get('colaboradores/{colaborador}/ficha-tecnica', [ColaboradorController::class, 'fichaTecnica'])
            ->name('colaboradores.ficha_tecnica');

        Route::post('colaboradores/fichas-tecnicas-masivas', [ColaboradorController::class, 'fichasTecnicasMasivas'])
            ->name('colaboradores.fichas_tecnicas_masivas');

        Route::patch('colaboradores/{colaborador}/estado', [ColaboradorController::class, 'cambiarEstado'])
            ->name('colaboradores.estado');

        Route::resource('colaboradores', ColaboradorController::class)
            ->except(['show', 'destroy'])->parameters(['colaboradores' => 'colaborador']);
    });

/*
|--------------------------------------------------------------------------
| Rutas de autenticación Breeze
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'auto.logout'])->prefix('devoluciones')->name('devoluciones.')->group(function () {
    Route::get('colaborador/{codigo}', [AsignacionInventarioController::class, 'formularioDevolucion'])->name('formulario');
    Route::post('colaborador/{codigo}', [AsignacionInventarioController::class, 'registrarDevolucionCompleta'])->name('registrar');
    Route::get('hoja/{grupo}', [AsignacionInventarioController::class, 'descargarDevolucion'])->whereUuid('grupo')->name('pdf');
});

require __DIR__ . '/auth.php';
require __DIR__.'/sistema.php';

require __DIR__.'/flujo.php';
