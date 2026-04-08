<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $roleId): Response
    {
        if (!auth()->check()) {
            abort(403);
        }

        if ((int) auth()->user()->role_id !== (int) $roleId) {
            abort(403);
        }

        return $next($request);
    }
}