<?php

use App\Http\Controllers\Sistema\CampoController;
use App\Http\Controllers\Sistema\ConfiguracionController;
use App\Http\Controllers\Sistema\DatosController;
use App\Http\Controllers\Sistema\SeguridadController;
use Illuminate\Support\Facades\Route;

Route::get('identidad-sistema/logo', [ConfiguracionController::class, 'logo'])->name('sistema.logo');

Route::name('sistema.')->middleware(['auth', 'auto.logout'])->group(function () {
    Route::prefix('datos')->controller(DatosController::class)->group(function () {
        Route::get('/', 'indice')->name('datos');
        Route::get('{modulo}/exportar', 'exportar')->name('datos.exportar');
        Route::get('{modulo}', 'lista')->name('datos.lista');
        Route::get('{modulo}/registro/{registro}', 'ficha')->name('datos.ficha');
        Route::get('{modulo}/registro/{registro}/archivo/{campo}', 'archivo')->name('datos.archivo');
    });
    Route::prefix('operacion/{modulo}/campos')->controller(DatosController::class)->group(function () {
        Route::get('/', 'operacion')->name('operacion.campos');
        Route::get('{registro}', 'editar')->name('operacion.campos.editar');
        Route::put('{registro}', 'actualizar')->name('operacion.campos.actualizar');
    });

    Route::prefix('admin/configuracion')->group(function () {
        Route::get('/', [ConfiguracionController::class, 'index'])->middleware('sistema.permiso:configuracion')->name('configuracion');
        Route::controller(SeguridadController::class)->middleware('sistema.permiso:seguridad')->group(function () {
            Route::get('seguridad', 'index')->name('seguridad');
            Route::get('roles/{rol}', 'rol')->name('rol');
            Route::get('cuentas/{usuario}', 'usuario')->name('usuario');
            Route::middleware('throttle:20,1')->group(function () {
                Route::post('roles', 'crearRol')->name('rol.crear');
                Route::post('cuentas', 'crearCuentaAdministrativa')->name('usuario.crear');
                Route::put('roles/{rol}', 'actualizarRol')->name('rol.actualizar');
                Route::put('cuentas/{usuario}/reglas', 'permisosUsuario')->name('usuario.permisos');
                Route::put('cuentas/{usuario}/estado', 'estadoCuenta')->name('usuario.estado');
                Route::put('cuentas/{usuario}/contrasena', 'actualizarContrasena')->name('usuario.contrasena');
                Route::put('cuentas/{usuario}/superadministrador', 'superadministrador')->name('usuario.superadministrador');
                Route::put('cuentas/{usuario}/acceso', 'actualizarCuenta')->name('usuario.acceso');
            });
        });

        Route::prefix('campos')->controller(CampoController::class)->middleware('sistema.permiso:sistema.campos')->group(function () {
            Route::get('/', 'index')->name('campos');
            Route::get('nuevo', 'formulario')->name('campos.crear');
            Route::post('/', 'guardar')->middleware('throttle:20,1')->name('campos.guardar');
            Route::get('{campo}', 'formulario')->name('campos.editar');
            Route::put('{campo}', 'guardar')->middleware('throttle:20,1')->name('campos.actualizar');
            Route::patch('{campo}/archivar', 'archivar')->name('campos.archivar');
        });

        Route::controller(ConfiguracionController::class)->group(function () {
            Route::get('presentacion', 'presentacion')->middleware('sistema.permiso:sistema.presentacion')->name('presentacion');
            Route::put('presentacion/{modulo}', 'guardarPresentacion')->middleware('sistema.permiso:sistema.presentacion')->name('presentacion.guardar');
            Route::get('parametros', 'parametros')->middleware('sistema.permiso:sistema.parametros')->name('parametros');
            Route::put('parametros', 'guardarParametros')->middleware(['sistema.permiso:sistema.parametros', 'throttle:20,1'])->name('parametros.guardar');
            Route::get('notificaciones', 'notificaciones')->middleware('sistema.permiso:sistema.notificaciones')->name('notificaciones');
            Route::put('notificaciones/{evento}', 'guardarNotificaciones')->middleware('sistema.permiso:sistema.notificaciones')->name('notificaciones.guardar');
            Route::get('auditoria', 'auditoria')->middleware('sistema.permiso:sistema.auditoria')->name('auditoria');
        });
    });
});
