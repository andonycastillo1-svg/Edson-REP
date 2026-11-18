<?php

namespace App\Http\Middleware;

use App\Sistema\Permisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function __construct(private Permisos $permisos) {}

    public function handle(Request $request, Closure $next, string $roleId): Response
    {
        $user = $request->user();
        abort_unless($user && $user->rolOperativo() === (int) $roleId
            && $this->permisos->ruta($user, (string) $request->route()->getName()), 403);

        return $next($request);
    }
}
