<?php

use App\Http\Controllers\Flujo\CatalogosController;
use App\Http\Controllers\Flujo\ConsolidadoController;
use App\Http\Controllers\Flujo\ImportarAsignacionController;
use App\Http\Controllers\Flujo\InspeccionController;
use App\Http\Controllers\Flujo\PlanComprasController;
use Illuminate\Support\Facades\Route;

Route::view('coordinacion/dashboard', 'flujo.coordinacion')->middleware(['auth', 'auto.logout', 'role:5'])
    ->name('coordinacion.dashboard');

Route::middleware(['auth', 'auto.logout'])->prefix('flujo')->name('flujo.')->group(function () {
    Route::get('inspecciones', [InspeccionController::class, 'index'])->name('inspecciones.index');
    Route::get('inspecciones/nueva', [InspeccionController::class, 'create'])->name('inspecciones.create');
    Route::get('regiones/{region}/colaboradores', [InspeccionController::class, 'colaboradores'])->name('inspecciones.colaboradores');
    Route::post('inspecciones', [InspeccionController::class, 'store'])->name('inspecciones.store');
    Route::get('inspecciones/{inspeccion}', [InspeccionController::class, 'show'])->whereNumber('inspeccion')->name('inspecciones.show');
    Route::get('inspecciones/{inspeccion}/fotos/{foto}', [InspeccionController::class, 'foto'])->whereNumber(['inspeccion', 'foto'])->name('inspecciones.foto');

    Route::get('catalogos', [CatalogosController::class, 'index'])->name('catalogos');
    Route::post('catalogos/productos', [CatalogosController::class, 'producto'])->name('catalogos.producto');
    Route::post('catalogos/regiones/{id?}', [CatalogosController::class, 'region'])->whereNumber('id')->name('catalogos.region');
    Route::post('catalogos/secciones/{clave}', [CatalogosController::class, 'seccion'])->name('catalogos.seccion');
    Route::post('catalogos/articulos/{id?}', [CatalogosController::class, 'articulo'])->whereNumber('id')->name('catalogos.articulo');
    Route::post('catalogos/motivos/{id?}', [CatalogosController::class, 'motivo'])->whereNumber('id')->name('catalogos.motivo');

    Route::get('consolidados', [ConsolidadoController::class, 'index'])->name('consolidados.index');
    Route::post('consolidados', [ConsolidadoController::class, 'store'])->name('consolidados.store');
    Route::get('consolidados/{consolidado}', [ConsolidadoController::class, 'show'])->whereNumber('consolidado')->name('consolidados.show');
    Route::post('consolidados/{consolidado}/{accion}', [ConsolidadoController::class, 'transicion'])
        ->whereNumber('consolidado')->name('consolidados.transicion');

    Route::get('necesidades', [PlanComprasController::class, 'index'])->name('compras.index');
    Route::post('necesidades/plan', [PlanComprasController::class, 'guardarPlan'])->name('compras.plan');
    Route::post('constancias', [PlanComprasController::class, 'store'])->name('compras.constancia');
    Route::get('constancias/{constancia}/documento', [PlanComprasController::class, 'documento'])->name('compras.documento');

    Route::get('asignaciones/importar', [ImportarAsignacionController::class, 'index'])->name('importar.index');
    Route::get('asignaciones/plantilla', [ImportarAsignacionController::class, 'plantilla'])->name('importar.plantilla');
    Route::post('asignaciones/importar', [ImportarAsignacionController::class, 'previsualizar'])->name('importar.preview');
    Route::get('asignaciones/importar/{importacion}', [ImportarAsignacionController::class, 'show'])->whereUuid('importacion')->name('importar.show');
    Route::get('asignaciones/importar/{importacion}/hojas', [ImportarAsignacionController::class, 'hojas'])->whereUuid('importacion')->name('importar.hojas');
    Route::post('asignaciones/importar/{importacion}', [ImportarAsignacionController::class, 'confirmar'])->whereUuid('importacion')->name('importar.confirmar');
});
