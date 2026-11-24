<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\User;
use App\Services\RegionesCoordinacion;
use App\Sistema\Seguridad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoordinadorRegionController extends Controller
{
    public function edit(Request $request, User $usuario)
    {
        $this->autorizar($request, $usuario);
        $regiones = Region::with('coordinador')->orderBy('nombre')->get();
        $historial = DB::table('region_coordinador_historial as h')->join('regiones as r', 'r.id', '=', 'h.region_id')
            ->join('users as actor', 'actor.id', '=', 'h.user_id')->leftJoin('users as anterior', 'anterior.id', '=', 'h.anterior_id')
            ->leftJoin('users as nuevo', 'nuevo.id', '=', 'h.coordinador_id')
            ->where(fn ($q) => $q->where('h.coordinador_id', $usuario->id)->orWhere('h.anterior_id', $usuario->id))
            ->select('r.nombre as region', 'actor.name as actor', 'anterior.name as anterior', 'nuevo.name as nuevo', 'h.created_at')
            ->orderByDesc('h.id')->paginate(15);

        return view('admin.usuarios.regiones', compact('usuario', 'regiones', 'historial'));
    }

    public function update(Request $request, User $usuario, RegionesCoordinacion $coordinacion)
    {
        $this->autorizar($request, $usuario);
        $datos = $request->validate([
            'regiones' => ['nullable', 'array'], 'regiones.*' => ['required', 'integer', 'distinct', 'exists:regiones,id'],
            'reasignar' => ['nullable', 'boolean'],
        ]);
        $coordinacion->asignar($request->user(), $usuario, array_map('intval', $datos['regiones'] ?? []), $request->boolean('reasignar'));

        return back()->with('success', 'Regiones actualizadas.');
    }

    private function autorizar(Request $request, User $usuario): void
    {
        abort_unless(app(Seguridad::class)->puedeGestionar($request->user(), $usuario), 403);
        abort_unless(User::deAmbito(5)->whereKey($usuario->id)->exists(), 404);
    }
}
