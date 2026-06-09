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

        $usuarios = User::with(['role', 'bodega', 'creator.role'])
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
        $rolAlmacenistaId = $this->rolAlmacenistaId();
        $rolEncargadoId = $rolAlmacenistaId; // Alias conservado para compatibilidad con vistas antiguas.
        $rolSupervisorId = $this->rolSupervisorId();
        $almacenistas = $this->almacenistasDisponibles();

        return view('admin.usuarios.create', compact(
            'roles',
            'bodegas',
            'rolAlmacenistaId',
            'rolEncargadoId',
            'rolSupervisorId',
            'almacenistas'
        ));
    }

    public function store(Request $request)
    {
        $rolAlmacenistaId = $this->rolAlmacenistaId();
        $rolSupervisorId = $this->rolSupervisorId();
        $rolesPermitidos = $this->rolesPermitidosParaGestion()->pluck('id')->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role_id' => ['required', 'exists:roles,id', Rule::in($rolesPermitidos)],
            'bodega_id' => [
                Rule::requiredIf(fn () => (int) $request->role_id === $rolAlmacenistaId),
                'nullable',
                'exists:bodegas,id',
            ],
            'almacenista_id' => [
                Rule::requiredIf(fn () => (int) $request->role_id === $rolSupervisorId),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('role_id', $rolAlmacenistaId)
                ),
            ],
        ], [
            'almacenista_id.required' => 'Debes seleccionar el almacenista asignado al Supervisor.',
            'almacenista_id.exists' => 'El almacenista seleccionado no es válido o no tiene el rol Almacenista.',
        ]);

        DB::transaction(function () use ($data, $rolAlmacenistaId, $rolSupervisorId) {
            $usuario = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'role_id' => $data['role_id'],
                'bodega_id' => (int) $data['role_id'] === $rolAlmacenistaId
                    ? ($data['bodega_id'] ?? null)
                    : null,
                'created_by' => auth()->id(),
            ]);

            if ((int) $usuario->role_id === $rolSupervisorId) {
                $usuario->almacenistasAsignados()->sync([(int) $data['almacenista_id']]);
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
        $rolAlmacenistaId = $this->rolAlmacenistaId();
        $rolEncargadoId = $rolAlmacenistaId; // Alias conservado para compatibilidad con vistas antiguas.
        $rolSupervisorId = $this->rolSupervisorId();
        $almacenistas = $this->almacenistasDisponibles($usuario->id);
        $usuario->load('almacenistasAsignados');
        $almacenistaAsignadoId = $usuario->almacenistasAsignados->first()?->id;

        return view('admin.usuarios.edit', compact(
            'usuario',
            'roles',
            'bodegas',
            'rolAlmacenistaId',
            'rolEncargadoId',
            'rolSupervisorId',
            'almacenistas',
            'almacenistaAsignadoId'
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

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'role_id' => ['required', 'exists:roles,id', Rule::in($rolesPermitidos)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'bodega_id' => [
                Rule::requiredIf(fn () => (int) $request->role_id === $rolAlmacenistaId),
                'nullable',
                'exists:bodegas,id',
            ],
            'almacenista_id' => [
                Rule::requiredIf(fn () => (int) $request->role_id === $rolSupervisorId),
                'nullable',
                'integer',
                Rule::notIn([$usuario->id]),
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('role_id', $rolAlmacenistaId)
                ),
            ],
        ], [
            'almacenista_id.required' => 'Debes seleccionar el almacenista asignado al Supervisor.',
            'almacenista_id.exists' => 'El almacenista seleccionado no es válido o no tiene el rol Almacenista.',
            'almacenista_id.not_in' => 'Un Supervisor no puede asignarse a sí mismo como almacenista.',
        ]);

        DB::transaction(function () use ($data, $usuario, $rolAlmacenistaId, $rolSupervisorId) {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $data['role_id'],
                'bodega_id' => (int) $data['role_id'] === $rolAlmacenistaId
                    ? ($data['bodega_id'] ?? null)
                    : null,
            ];

            if (!empty($data['password'])) {
                $payload['password'] = bcrypt($data['password']);
            }

            $usuario->update($payload);

            if ((int) $usuario->role_id === $rolSupervisorId) {
                // La tabla pivote se conserva, pero sync limita al Supervisor a un solo Almacenista.
                $usuario->almacenistasAsignados()->sync([(int) $data['almacenista_id']]);
            } else {
                $usuario->almacenistasAsignados()->detach();
            }

            if ((int) $usuario->role_id !== $rolAlmacenistaId) {
                // Si deja de ser Almacenista, sus Supervisores dejan de apuntar a este usuario.
                $usuario->supervisoresAsignados()->detach();
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

    private function almacenistasDisponibles(?int $usuarioExcluidoId = null)
    {
        return User::query()
            ->where('role_id', $this->rolAlmacenistaId())
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
        // role_id=2 es el rol operativo usado por rutas y permisos actuales.
        // Se valida también el nombre para tolerar instalaciones que lo muestran
        // como Almacenista, Encargado u Operador.
        $rolOperativo = Role::query()
            ->where('id', 2)
            ->whereRaw('LOWER(TRIM(nombre)) IN (?, ?, ?)', ['almacenista', 'encargado', 'operador'])
            ->first();

        $rol = $rolOperativo ?? Role::query()
            ->whereRaw('LOWER(TRIM(nombre)) IN (?, ?, ?)', ['almacenista', 'encargado', 'operador'])
            ->orderByRaw('CASE WHEN id = 2 THEN 0 ELSE 1 END')
            ->first();

        return (int) ($rol?->id ?? 2);
    }

    private function rolSupervisorId(): int
    {
        // Se prioriza el ID histórico 3 para conservar compatibilidad con rutas
        // y permisos, aceptando tanto el nombre nuevo como el legado.
        $rolHistorico = Role::query()
            ->where('id', 3)
            ->whereRaw('LOWER(TRIM(nombre)) IN (?, ?)', ['supervisor', 'coordinador'])
            ->first();

        $rol = $rolHistorico ?? Role::query()
            ->whereRaw('LOWER(TRIM(nombre)) IN (?, ?)', ['supervisor', 'coordinador'])
            ->orderByRaw('CASE WHEN id = 3 THEN 0 ELSE 1 END')
            ->first();

        return (int) ($rol?->id ?? 3);
    }

    private function esAdmin(): bool
    {
        return (int) auth()->user()->role_id === 1;
    }
}
