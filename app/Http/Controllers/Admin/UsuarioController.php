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
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $roleId = $request->query('role_id');

        $usuarios = User::with(['role','bodega','creator.role'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($roleId, fn ($query) => $query->where('role_id', $roleId))
            ->orderBy('name')
            ->paginate(15)
            ->appends($request->query());

        $rolesFiltro = Role::orderBy('nombre')->get();

        return view('admin.usuarios.index', compact('usuarios', 'rolesFiltro'));
    }

    public function create()
    {
        $roles = $this->rolesPermitidosParaGestion();
        $bodegas = Bodega::orderBy('nombre')->get();

        $rolEncargadoId = $this->rolObjetivoRrhhId();

        return view('admin.usuarios.create', compact('roles','bodegas','rolEncargadoId'));
    }

    public function store(Request $request)
    {
        $rolBodegaRequeridaId = $this->rolBodegaRequeridaId();
        $rolesPermitidos = $this->rolesPermitidosParaGestion()->pluck('id')->all();

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:6','confirmed'],
            'role_id' => ['required','exists:roles,id', Rule::in($rolesPermitidos)],
            'bodega_id' => [
                Rule::requiredIf(fn() => (int)$request->role_id === (int)$rolBodegaRequeridaId),
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
            'created_by' => auth()->id(),
        ]);

        return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
            ->with('ok','Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        if (!$this->puedeEditarUsuario($usuario)) {
            return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
                ->with('error', 'No tienes permiso para editar este usuario.');
        }

        $roles = $this->rolesPermitidosParaGestion();
        $bodegas = Bodega::orderBy('nombre')->get();
        $rolEncargadoId = $this->rolObjetivoRrhhId();

        return view('admin.usuarios.edit', compact('usuario','roles','bodegas','rolEncargadoId'));
    }

    public function update(Request $request, User $usuario)
    {
        if (!$this->puedeEditarUsuario($usuario)) {
            return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
                ->with('error', 'No tienes permiso para actualizar este usuario.');
        }

        $rolBodegaRequeridaId = $this->rolBodegaRequeridaId();
        $rolesPermitidos = $this->rolesPermitidosParaGestion()->pluck('id')->all();

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255',Rule::unique('users','email')->ignore($usuario->id)],
            'role_id' => ['required','exists:roles,id', Rule::in($rolesPermitidos)],
            'password' => ['nullable','string','min:6','confirmed'],
            'bodega_id' => [
                Rule::requiredIf(fn() => (int)$request->role_id === (int)$rolBodegaRequeridaId),
                'nullable',
                'exists:bodegas,id',
            ],
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'bodega_id' => $data['bodega_id'] ?? null,
        ];

        if (!empty($data['password'])) {
            $payload['password'] = bcrypt($data['password']);
        }

        $usuario->update($payload);

        return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
            ->with('ok','Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if (!$this->puedeEditarUsuario($usuario)) {
            return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
                ->with('error', 'No tienes permiso para eliminar este usuario.');
        }

        $usuario->delete();

        return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
            ->with('ok','Usuario eliminado correctamente.');
    }

    private function usuariosRoutePrefix(): string
    {
        return auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
    }

    private function rolesPermitidosParaGestion()
    {
        $query = Role::query()->orderBy('nombre');

        if ((int) auth()->user()->role_id === 4) {
            $query->where('nombre', 'Almacenista');
        }

        return $query->get();
    }



    private function puedeEditarUsuario(User $usuario): bool
    {
        if ((int) auth()->user()->role_id !== 4) {
            return true;
        }

        $usuario->loadMissing('creator.role');

        return (int) optional(optional($usuario->creator)->role)->id === 4;
    }

    private function rolObjetivoRrhhId(): ?int
    {
        $rolAlmacenistaId = Role::where('nombre', 'Almacenista')->value('id');
        return $rolAlmacenistaId ? (int) $rolAlmacenistaId : null;
    }

    private function rolBodegaRequeridaId(): int
    {
        return (int) ($this->rolObjetivoRrhhId() ?? 2);
    }
}
