<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $roleId = $request->query('role_id');

        $usuarios = User::with(['role', 'bodega', 'creator.role', 'supervisoresAsignados'])
            ->when(!$this->esAdmin(), function ($query) {
                $query->where('created_by', auth()->id());
            })
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

        $rolesFiltro = $this->esAdmin()
            ? Role::orderBy('nombre')->get()
            : $this->rolesPermitidosParaGestion();

        return view('admin.usuarios.index', compact('usuarios', 'rolesFiltro'));
    }

    public function create()
    {
        $roles = $this->rolesPermitidosParaGestion();
        $bodegas = Bodega::orderBy('nombre')->get();
        $rolEncargadoId = $this->rolAlmacenistaId();
        $rolSupervisorId = $this->rolSupervisorId();
        $supervisores = $this->esAdmin() ? $this->supervisoresDisponibles() : collect();

        return view('admin.usuarios.create', compact(
            'roles',
            'bodegas',
            'rolEncargadoId',
            'rolSupervisorId',
            'supervisores'
        ));
    }

    public function store(Request $request)
    {
        $rolAlmacenistaId = $this->rolAlmacenistaId();
        $rolesPermitidos = $this->rolesPermitidosParaGestion()->pluck('id')->all();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role_id' => ['required', 'exists:roles,id', Rule::in($rolesPermitidos)],
            'bodega_id' => [
                Rule::requiredIf(fn () => (int) $request->role_id === $rolAlmacenistaId),
                'nullable',
                'exists:bodegas,id',
            ],
        ];

        if ($this->esAdmin()) {
            $rules['supervisores'] = ['nullable', 'array'];
            $rules['supervisores.*'] = [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role_id', $this->rolSupervisorId())),
            ];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($data, $rolAlmacenistaId) {
            $usuario = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'role_id' => $data['role_id'],
                'bodega_id' => $data['bodega_id'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if ($this->esAdmin() && (int) $usuario->role_id === $rolAlmacenistaId) {
                $usuario->supervisoresAsignados()->sync($data['supervisores'] ?? []);
            }
        });

        return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
            ->with('ok', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        if (!$this->puedeEditarUsuario($usuario)) {
            return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
                ->with('error', 'No tienes permiso para editar este usuario.');
        }

        $roles = $this->rolesPermitidosParaGestion();
        $bodegas = Bodega::orderBy('nombre')->get();
        $rolEncargadoId = $this->rolAlmacenistaId();
        $rolSupervisorId = $this->rolSupervisorId();
        $supervisores = $this->esAdmin() ? $this->supervisoresDisponibles($usuario->id) : collect();
        $usuario->load('supervisoresAsignados');

        return view('admin.usuarios.edit', compact(
            'usuario',
            'roles',
            'bodegas',
            'rolEncargadoId',
            'rolSupervisorId',
            'supervisores'
        ));
    }

    public function update(Request $request, User $usuario)
    {
        if (!$this->puedeEditarUsuario($usuario)) {
            return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
                ->with('error', 'No tienes permiso para actualizar este usuario.');
        }

        $rolAlmacenistaId = $this->rolAlmacenistaId();
        $rolSupervisorId = $this->rolSupervisorId();
        $rolesPermitidos = $this->rolesPermitidosParaGestion()->pluck('id')->all();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'role_id' => ['required', 'exists:roles,id', Rule::in($rolesPermitidos)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'bodega_id' => [
                Rule::requiredIf(fn () => (int) $request->role_id === $rolAlmacenistaId),
                'nullable',
                'exists:bodegas,id',
            ],
        ];

        if ($this->esAdmin()) {
            $rules['supervisores'] = ['nullable', 'array'];
            $rules['supervisores.*'] = [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role_id', $rolSupervisorId)),
            ];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($data, $usuario, $rolAlmacenistaId, $rolSupervisorId) {
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

            if ((int) $usuario->role_id === $rolAlmacenistaId) {
                if ($this->esAdmin()) {
                    $usuario->supervisoresAsignados()->sync($data['supervisores'] ?? []);
                }
            } else {
                $usuario->supervisoresAsignados()->detach();
            }

            if ((int) $usuario->role_id !== $rolSupervisorId) {
                $usuario->almacenistasAsignados()->detach();
            }
        });

        return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
            ->with('ok', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if (!$this->puedeEditarUsuario($usuario)) {
            return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
                ->with('error', 'No tienes permiso para eliminar este usuario.');
        }

        $usuario->delete();

        return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
            ->with('ok', 'Usuario eliminado correctamente.');
    }

    private function usuariosRoutePrefix(): string
    {
        return auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
    }

    private function rolesPermitidosParaGestion()
    {
        $query = Role::query()->orderBy('nombre');

        if (!$this->esAdmin()) {
            $query->whereIn('id', array_filter([
                $this->rolAlmacenistaId(),
                $this->rolSupervisorId(),
            ]));
        }

        return $query->get();
    }

    private function supervisoresDisponibles(?int $usuarioExcluidoId = null)
    {
        return User::query()
            ->where('role_id', $this->rolSupervisorId())
            ->when($usuarioExcluidoId, fn ($query) => $query->where('id', '!=', $usuarioExcluidoId))
            ->orderBy('name')
            ->get();
    }

    private function puedeEditarUsuario(User $usuario): bool
    {
        if ($this->esAdmin()) {
            return true;
        }

        return (int) $usuario->created_by === (int) auth()->id();
    }

    private function rolAlmacenistaId(): int
    {
        $rol = Role::whereRaw('LOWER(TRIM(nombre)) = ?', ['almacenista'])->first()
            ?? Role::whereRaw('LOWER(TRIM(nombre)) = ?', ['encargado'])->first()
            ?? Role::whereRaw('LOWER(TRIM(nombre)) = ?', ['operador'])->first();

        return (int) ($rol?->id ?? 2);
    }

    private function rolSupervisorId(): int
    {
        $rol = Role::whereRaw('LOWER(TRIM(nombre)) = ?', ['supervisor'])->first()
            ?? Role::whereRaw('LOWER(TRIM(nombre)) = ?', ['coordinador'])->first();

        return (int) ($rol?->id ?? 3);
    }

    private function esAdmin(): bool
    {
        return (int) auth()->user()->role_id === 1;
    }
}
