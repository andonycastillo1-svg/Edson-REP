<?php

namespace App\Http\Middleware;

use App\Sistema\Permisos;
use App\Sistema\CatalogoPermisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function __construct(private Permisos $permisos) {}

    public function handle(Request $request, Closure $next, string $roleId): Response
    {
        $user = $request->user();
        $ruta = (string) $request->route()->getName();
        abort_unless($user && (CatalogoPermisos::permisoRutaInventario($ruta) !== null || $user->rolOperativo() === (int) $roleId)
            && $this->permisos->ruta($user, $ruta), 403);

        return $next($request);
    }
}
