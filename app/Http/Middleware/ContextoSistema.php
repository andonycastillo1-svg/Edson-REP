<?php

namespace App\Http\Middleware;

use App\Sistema\Ajustes;
use App\Services\DocumentoPrivado;
use Illuminate\Support\Facades\DB;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ContextoSistema
{
    public function __construct(private Ajustes $ajustes) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ($user->acceso_activo === false || $user->role?->activo === false)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'La cuenta o su rol está desactivado.');
        }

        $empresa = $this->ajustes->general('empresa');
        $nombre = $this->ajustes->general('nombre');
        $original = config('app.name');
        config(['app.name' => $nombre ?: $original, 'app.empresa' => $empresa]);

        try {
            return $next($request);
        } finally {
            config(['app.name' => $original, 'app.empresa' => null]);
            foreach ($request->attributes->get('archivos_campos_sistema', []) as $ruta) {
                if (!DB::table('sistema_campo_valores')->where('valor->ruta', $ruta)->exists()
                    && !DB::table('sistema_campo_historial')->where('valor->ruta', $ruta)->exists()) {
                    DocumentoPrivado::descartar($ruta);
                }
            }
        }
    }
}
