<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Bodega;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = User::with(['role','bodega'])->orderBy('name')->paginate(15);
        return view('admin.usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $roles = Role::orderBy('nombre')->get();
        $bodegas = Bodega::orderBy('nombre')->get();

        $rolEncargadoId = Role::where('nombre', 'Encargado')->value('id');

        return view('admin.usuarios.create', compact('roles','bodegas','rolEncargadoId'));
    }

    public function store(Request $request)
    {
        $rolEncargadoId = Role::where('nombre', 'Encargado')->value('id');

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:6','confirmed'],
            'role_id' => ['required','exists:roles,id'],
            'bodega_id' => [
                Rule::requiredIf(fn() => (int)$request->role_id === (int)$rolEncargadoId),
                'nullable',
                'exists:bodegas,id',
            ],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'role_id' => $data['role_id'],
            'bodega_id' => $data['bodega_id'] ?? null,
        ]);

        return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
            ->with('ok','Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $roles = Role::orderBy('nombre')->get();
        $bodegas = Bodega::orderBy('nombre')->get();
        $rolEncargadoId = Role::where('nombre', 'Encargado')->value('id');

        return view('admin.usuarios.edit', compact('usuario','roles','bodegas','rolEncargadoId'));
    }

    public function update(Request $request, User $usuario)
    {
        $rolEncargadoId = Role::where('nombre', 'Encargado')->value('id');

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255',Rule::unique('users','email')->ignore($usuario->id)],
            'role_id' => ['required','exists:roles,id'],
            'bodega_id' => [
                Rule::requiredIf(fn() => (int)$request->role_id === (int)$rolEncargadoId),
                'nullable',
                'exists:bodegas,id',
            ],
        ]);

        $usuario->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'bodega_id' => $data['bodega_id'] ?? null,
        ]);

        return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
            ->with('ok','Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        $usuario->delete();

        return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
            ->with('ok','Usuario eliminado correctamente.');
    }

    private function usuariosRoutePrefix(): string
    {
        return auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
    }
}
