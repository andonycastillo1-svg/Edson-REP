<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;

use App\Http\Controllers\Admin\InventarioController;
use App\Http\Controllers\Admin\OperacionTrasladoController;
use App\Http\Controllers\Admin\ColaboradorController;
use App\Http\Controllers\Admin\AsignacionInventarioController;
use App\Models\AlertaReemplazo;
use Illuminate\Support\Facades\Schema;

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

        Route::get('colaboradores/{colaborador}/detalle', [ColaboradorController::class, 'detalle'])
            ->name('colaboradores.detalle');

        Route::patch('colaboradores/{colaborador}/estado', [ColaboradorController::class, 'cambiarEstado'])
            ->name('colaboradores.estado');

        Route::resource('colaboradores', ColaboradorController::class)
            ->parameters(['colaboradores' => 'colaborador']);

        Route::resource('vehiculos', \App\Http\Controllers\Admin\VehiculoController::class);
        Route::resource('compras', \App\Http\Controllers\Admin\CompraController::class);

        Route::get('bodegas/{bodega}/entrada', [InventarioController::class, 'createEntrada'])
            ->name('bodegas.entrada');

        Route::post('bodegas/{bodega}/entrada', [InventarioController::class, 'storeEntrada'])
            ->name('bodegas.entrada.store');

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

        Route::get('asignaciones/create', [AsignacionInventarioController::class, 'create'])
            ->name('asignaciones.create');

        Route::get('asignaciones', [AsignacionInventarioController::class, 'index'])
            ->name('asignaciones.index');

        Route::post('asignaciones', [AsignacionInventarioController::class, 'store'])
            ->name('asignaciones.store');

        Route::get('asignaciones/grupo/{grupo}/pdf', [AsignacionInventarioController::class, 'pdf'])
            ->name('asignaciones.pdf');

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

        Route::resource('bodegas', \App\Http\Controllers\Admin\BodegaController::class)->only(['index', 'show']);

        Route::resource('compras', \App\Http\Controllers\Admin\CompraController::class);

        Route::get('asignaciones/create', [AsignacionInventarioController::class, 'create'])
            ->name('asignaciones.create');

        Route::get('asignaciones', [AsignacionInventarioController::class, 'index'])
            ->name('asignaciones.index');

        Route::post('asignaciones', [AsignacionInventarioController::class, 'store'])
            ->name('asignaciones.store');

        Route::get('asignaciones/grupo/{grupo}/pdf', [AsignacionInventarioController::class, 'pdf'])
            ->name('asignaciones.pdf');

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

        Route::get('/dashboard', function () {
            $alertas = collect();

            if (Schema::hasTable('alertas_reemplazos_rrhh')) {
                try {
                    $alertas = AlertaReemplazo::query()
                        ->leftJoin('colaboradores as c', 'c.codigo', '=', 'alertas_reemplazos_rrhh.colaborador_codigo')
                        ->leftJoin('productos as p', 'p.codigo', '=', 'alertas_reemplazos_rrhh.producto_codigo')
                        ->addSelect('alertas_reemplazos_rrhh.*')
                        ->addSelect('c.nombre as colaborador_nombre')
                        ->addSelect('p.nombre as producto_nombre')
                        ->addSelect('p.descripcion as producto_descripcion')
                        ->selectRaw("GREATEST(alertas_reemplazos_rrhh.vida_util_meses - TIMESTAMPDIFF(MONTH, alertas_reemplazos_rrhh.fecha_asignacion_anterior, alertas_reemplazos_rrhh.fecha_dano_reemplazo), 0) as meses_restantes_reales")
                        ->selectRaw("COALESCE((SELECT cd.precio_unitario FROM compra_detalles cd WHERE cd.producto_codigo = alertas_reemplazos_rrhh.producto_codigo ORDER BY cd.id DESC LIMIT 1), 0) as costo_producto")
                        ->latest()
                        ->limit(8)
                        ->get();

                    $alertas->transform(function ($alerta) {
                        $vidaUtilMeses = (int) ($alerta->vida_util_meses ?? 0);
                        $mesesRestantes = max(0, (int) ($alerta->meses_restantes_reales ?? 0));
                        $costoProducto = (float) ($alerta->costo_producto ?? 0);

                        $alerta->meses_restantes_reales = $mesesRestantes;
                        $alerta->descuento_calculado = ($alerta->descuento_aplicable && $vidaUtilMeses > 0)
                            ? round($costoProducto * ($mesesRestantes / $vidaUtilMeses), 2)
                            : 0;

                        return $alerta;
                    });
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return view('rrhh.dashboard', compact('alertas'));
        })->name('dashboard');

        Route::resource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class);

        Route::get('colaboradores/{colaborador}/detalle', [ColaboradorController::class, 'detalle'])
            ->name('colaboradores.detalle');

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
