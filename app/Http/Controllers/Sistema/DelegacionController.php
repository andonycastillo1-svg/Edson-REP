<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\DelegacionOperativa;
use App\Models\User;
use App\Services\DelegacionesOperativas;
use App\Sistema\Auditoria;
use App\Sistema\Seguridad;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DelegacionController extends Controller
{
    public function __construct(private Seguridad $seguridad, private DelegacionesOperativas $delegaciones, private Auditoria $auditoria) {}

    public function store(Request $request, User $usuario)
    {
        $datos = $request->validate([
            'titular_id' => ['required', 'integer', 'exists:users,id'],
            'permisos' => ['required', 'array', 'list'],
            'permisos.*' => ['required', 'distinct', Rule::in([...DelegacionesOperativas::REGIONALES, ...DelegacionesOperativas::permisosBodega()])],
            'regiones' => ['nullable', 'array', 'list'],
            'regiones.*' => ['required', 'integer', 'distinct', 'exists:regiones,id'],
            'bodegas' => ['nullable', 'array', 'list'],
            'bodegas.*' => ['required', 'integer', 'distinct', 'exists:bodegas,id'],
            'vigente_desde' => ['required', 'date_format:Y-m-d\TH:i'],
            'vigente_hasta' => ['required', 'date_format:Y-m-d\TH:i', 'after:vigente_desde', 'after:now'],
            'motivo' => ['required', 'string', 'max:500'],
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        $this->seguridad->cambiar($request->user(), $datos['current_password'], function (User $actor) use ($usuario, $datos) {
            $titular = User::findOrFail($datos['titular_id']);
            $sustituto = User::findOrFail($usuario->id);
            $this->delegaciones->validar($titular, $sustituto, $datos);
            $fila = DelegacionOperativa::create($datos + ['sustituto_id' => $sustituto->id, 'autorizada_por' => $actor->id]);
            $fila->regiones()->attach($datos['regiones'] ?? []);
            $fila->bodegas()->attach($datos['bodegas'] ?? []);
            $this->auditoria->registrar('seguridad', 'crear_delegacion', $fila->id, null,
                $fila->only(['titular_id', 'sustituto_id', 'permisos', 'vigente_desde', 'vigente_hasta', 'motivo'])
                + ['regiones' => $datos['regiones'] ?? [], 'bodegas' => $datos['bodegas'] ?? []]);
        });

        return redirect()->to(route('sistema.usuario', $usuario).'#delegaciones')->with('ok', 'Delegación registrada.');
    }

    public function revocar(Request $request, User $usuario, int $delegacion)
    {
        $datos = $request->validate(['current_password' => ['required', 'string', 'max:255']]);
        $this->seguridad->cambiar($request->user(), $datos['current_password'], function (User $actor) use ($usuario, $delegacion) {
            $fila = DelegacionOperativa::where('sustituto_id', $usuario->id)->lockForUpdate()->findOrFail($delegacion);
            if ($fila->revocada_en === null && $fila->vigente_hasta->isFuture()) {
                $fila->update(['revocada_en' => now(), 'revocada_por' => $actor->id]);
                $this->auditoria->registrar('seguridad', 'revocar_delegacion', $fila->id, null,
                    $fila->only(['titular_id', 'sustituto_id', 'revocada_en']));
            }
        });

        return redirect()->to(route('sistema.usuario', $usuario).'#delegaciones')->with('ok', 'Delegación actualizada.');
    }
}
