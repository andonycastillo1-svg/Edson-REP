<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $roleId = $request->query('role_id');

        $usuarios = User::with(['role', 'bodega', 'creator.role', 'supervisoresAsignados', 'almacenistasAsignados'])
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
        $rolSupervisorId = $this->rolSupervisorId();

        $almacenistas = User::query()
            ->where('role_id', $rolAlmacenistaId)
            ->orderBy('name')
            ->get();

        return view('admin.usuarios.create', compact(
            'roles',
            'bodegas',
            'almacenistas',
            'rolAlmacenistaId',
            'rolSupervisorId'
        ));
    }

    public function store(Request $request)
    {
        $rolAlmacenistaId = $this->rolAlmacenistaId();
        $rolSupervisorId = $this->rolSupervisorId();

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

            'almacenista_id' => [
                Rule::requiredIf(fn () => (int) $request->role_id === $rolSupervisorId),
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role_id', $rolAlmacenistaId)),
            ],
        ];

        $data = $request->validate($rules);

        $usuario = DB::transaction(function () use ($data, $rolAlmacenistaId, $rolSupervisorId) {
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
                $usuario->almacenistasAsignados()->sync([
                    (int) $data['almacenista_id'],
                ]);
            }

            return $usuario;
        });

        if ((int) $usuario->role_id === $rolSupervisorId) {
            $almacenista = User::find((int) $data['almacenista_id']);

            if ($almacenista) {
                app(NotificacionService::class)->safeAction(
                    fn (NotificacionService $service) => $service->notificarRelacionSupervisorAlmacenista(
                        $usuario,
                        $almacenista,
                        $request->user()
                    )
                );
            }
        }

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
        $rolSupervisorId = $this->rolSupervisorId();

        $almacenistas = User::query()
            ->where('role_id', $rolAlmacenistaId)
            ->where('id', '!=', $usuario->id)
            ->orderBy('name')
            ->get();

        $usuario->load('almacenistasAsignados');

        return view('admin.usuarios.edit', compact(
            'usuario',
            'roles',
            'bodegas',
            'almacenistas',
            'rolAlmacenistaId',
            'rolSupervisorId'
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

            'almacenista_id' => [
                Rule::requiredIf(fn () => (int) $request->role_id === $rolSupervisorId),
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role_id', $rolAlmacenistaId)),
            ],
        ];

        $data = $request->validate($rules);
        $almacenistaAnteriorId = $usuario->almacenistasAsignados()->value('users.id');

        DB::transaction(function () use ($data, $usuario, $rolAlmacenistaId, $rolSupervisorId) {
            $nuevoRolId = (int) $data['role_id'];

            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $nuevoRolId,
                'bodega_id' => $nuevoRolId === $rolAlmacenistaId
                    ? ($data['bodega_id'] ?? null)
                    : null,
            ];

            if (!empty($data['password'])) {
                $payload['password'] = bcrypt($data['password']);
            }

            $usuario->update($payload);

            if ($nuevoRolId === $rolSupervisorId) {
                $usuario->almacenistasAsignados()->sync([
                    (int) $data['almacenista_id'],
                ]);
            } else {
                $usuario->almacenistasAsignados()->detach();
            }

            if ($nuevoRolId !== $rolAlmacenistaId) {
                $usuario->supervisoresAsignados()->detach();
            }
        });

        $nuevoAlmacenistaId = (int) $data['role_id'] === $rolSupervisorId
            ? (int) $data['almacenista_id']
            : null;

        if ($nuevoAlmacenistaId && (int) $almacenistaAnteriorId !== $nuevoAlmacenistaId) {
            $almacenista = User::find($nuevoAlmacenistaId);

            if ($almacenista) {
                app(NotificacionService::class)->safeAction(
                    fn (NotificacionService $service) => $service->notificarRelacionSupervisorAlmacenista(
                        $usuario->fresh(),
                        $almacenista,
                        $request->user()
                    )
                );
            }
        }

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

    private function puedeEditarUsuario(User $usuario): bool
    {
        if ($this->esAdmin()) {
            return true;
        }

        return (int) $usuario->created_by === (int) auth()->id();
    }

    private function rolAlmacenistaId(): int
    {
        return 2;
    }

    private function rolSupervisorId(): int
    {
        return 3;
    }

    private function esAdmin(): bool
    {
        return (int) auth()->user()->role_id === 1;
    }
}