<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\AutoLogoutOnInactivity;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\ContextoSistema::class]);
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'sistema.permiso' => \App\Http\Middleware\PermisoSistema::class,
            'auto.logout' => AutoLogoutOnInactivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
