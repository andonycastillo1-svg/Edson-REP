<?php

namespace App\Http\Middleware;

use App\Sistema\Permisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermisoSistema
{
    public function __construct(private Permisos $permisos) {}

    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        abort_unless($request->user(), 403);
        abort_unless($this->permisos->instalado(), 503, 'Configuración aún no habilitada.');

        $permitido = match ($permiso) {
            'configuracion' => $this->permisos->esSuperadministrador($request->user())
                || collect(array_keys(\App\Sistema\CatalogoPermisos::CONFIGURACION))->contains(fn ($key) => $this->permisos->permite($request->user(), $key)),
            'seguridad' => $this->permisos->esSuperadministrador($request->user()),
            default => $this->permisos->permite($request->user(), $permiso),
        };
        abort_unless($permitido, 403);

        return $next($request);
    }
}
