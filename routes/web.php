<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;

use App\Http\Controllers\Admin\InventarioController;
use App\Http\Controllers\Admin\OperacionTrasladoController;
use App\Http\Controllers\Admin\ColaboradorController;
use App\Http\Controllers\Admin\AsignacionInventarioController;
use App\Http\Controllers\Admin\AsignacionVehiculoController;
use App\Http\Controllers\Admin\VehiculoProductoController;
use App\Http\Controllers\RrhhDashboardController;

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
    $roleId = auth()->user()->role_id;

    return match ((int) $roleId) {
        1 => redirect()->route('admin.dashboard'),
        2 => redirect()->route('operador.dashboard'),
        3 => redirect()->route('coordinador.dashboard'),
        4 => redirect()->route('rrhh.dashboard'),
        default => redirect()->route('login'),
    };
})->middleware(['auth', 'auto.logout'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Dashboards base por rol
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'auto.logout'])->group(function () {
    Route::view('/coordinador/dashboard', 'coordinador.dashboard')->name('coordinador.dashboard');
});

/*
|--------------------------------------------------------------------------
| Perfil (Breeze)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'auto.logout'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
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

        Route::resource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class);
        Route::resource('bodegas', \App\Http\Controllers\Admin\BodegaController::class);

        Route::get('bodegas/{bodega}/inventario-export', [\App\Http\Controllers\Admin\BodegaController::class, 'exportInventario'])
            ->name('bodegas.inventario.export');

        Route::get('colaboradores/{colaborador}/detalle', [ColaboradorController::class, 'detalle'])
            ->name('colaboradores.detalle');

        Route::get('colaboradores/{colaborador}/ficha-tecnica', [ColaboradorController::class, 'fichaTecnica'])
            ->name('colaboradores.ficha_tecnica');

        Route::post('colaboradores/fichas-tecnicas-masivas', [ColaboradorController::class, 'fichasTecnicasMasivas'])
            ->name('colaboradores.fichas_tecnicas_masivas');

        Route::patch('colaboradores/{colaborador}/estado', [ColaboradorController::class, 'cambiarEstado'])
            ->name('colaboradores.estado');

        Route::resource('colaboradores', ColaboradorController::class)
            ->parameters(['colaboradores' => 'colaborador']);

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
        | Compras
        |--------------------------------------------------------------------------
        */
        Route::resource('compras', \App\Http\Controllers\Admin\CompraController::class);

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
        Route::get('asignaciones/create', [AsignacionInventarioController::class, 'create'])
            ->name('asignaciones.create');

        Route::get('asignaciones', [AsignacionInventarioController::class, 'index'])
            ->name('asignaciones.index');

        Route::post('asignaciones', [AsignacionInventarioController::class, 'store'])
            ->name('asignaciones.store');

        Route::get('asignaciones/colaborador/{codigo}/pdf', [AsignacionInventarioController::class, 'pdf'])
            ->name('asignaciones.pdf');

        Route::get('asignaciones/devoluciones/{grupo}', [AsignacionInventarioController::class, 'hojaDevolucion'])
            ->name('asignaciones.hoja_devolucion');

        Route::post('asignaciones/{asignacion}/pdf-firmado', [AsignacionInventarioController::class, 'uploadPdfFirmado'])
            ->name('asignaciones.upload_pdf_firmado');

        Route::post('asignaciones/{asignacion}/devolver', [AsignacionInventarioController::class, 'devolver'])
            ->name('asignaciones.devolver');

        Route::post('asignaciones/devolver-lote', [AsignacionInventarioController::class, 'devolverLote'])
            ->name('asignaciones.devolver_lote');

        Route::post('asignaciones/colaborador/{codigo}/devolver-todo', [AsignacionInventarioController::class, 'devolverTodoColaborador'])
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

        Route::resource('compras', \App\Http\Controllers\Admin\CompraController::class);

        Route::get('asignaciones/create', [AsignacionInventarioController::class, 'create'])
            ->name('asignaciones.create');

        Route::get('asignaciones', [AsignacionInventarioController::class, 'index'])
            ->name('asignaciones.index');

        Route::post('asignaciones', [AsignacionInventarioController::class, 'store'])
            ->name('asignaciones.store');

        Route::get('asignaciones/colaborador/{codigo}/pdf', [AsignacionInventarioController::class, 'pdf'])
            ->name('asignaciones.pdf');

        Route::get('asignaciones/devoluciones/{grupo}', [AsignacionInventarioController::class, 'hojaDevolucion'])
            ->name('asignaciones.hoja_devolucion');

        Route::post('asignaciones/{asignacion}/pdf-firmado', [AsignacionInventarioController::class, 'uploadPdfFirmado'])
            ->name('asignaciones.upload_pdf_firmado');

        Route::post('asignaciones/{asignacion}/devolver', [AsignacionInventarioController::class, 'devolver'])
            ->name('asignaciones.devolver');

        Route::post('asignaciones/devolver-lote', [AsignacionInventarioController::class, 'devolverLote'])
            ->name('asignaciones.devolver_lote');

        Route::post('asignaciones/colaborador/{codigo}/devolver-todo', [AsignacionInventarioController::class, 'devolverTodoColaborador'])
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

        Route::resource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class);

        Route::get('colaboradores/{colaborador}/detalle', [ColaboradorController::class, 'detalle'])
            ->name('colaboradores.detalle');

        Route::get('colaboradores/{colaborador}/ficha-tecnica', [ColaboradorController::class, 'fichaTecnica'])
            ->name('colaboradores.ficha_tecnica');

        Route::post('colaboradores/fichas-tecnicas-masivas', [ColaboradorController::class, 'fichasTecnicasMasivas'])
            ->name('colaboradores.fichas_tecnicas_masivas');

        Route::patch('colaboradores/{colaborador}/estado', [ColaboradorController::class, 'cambiarEstado'])
            ->name('colaboradores.estado');

        Route::resource('colaboradores', ColaboradorController::class)
            ->parameters(['colaboradores' => 'colaborador']);
    });

/*
|--------------------------------------------------------------------------
| Rutas de autenticación Breeze
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';