<?php

namespace App\Providers;

use App\Sistema\Permisos;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Sistema\Permisos::class);
        $this->app->scoped(\App\Sistema\Ajustes::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::if('rutaDisponible', function (string $ruta): bool {
            $usuario = auth()->user();

            return $usuario !== null && app(Permisos::class)->ruta($usuario, $ruta);
        });
    }
}
