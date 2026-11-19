<?php

namespace App\Http\Controllers\Admin;

use App\Sistema\Auditoria;
use App\Sistema\Permisos;
use App\Sistema\Seguridad;
use App\Sistema\ValoresCampos;
use App\Http\Controllers\Controller;
use App\Sistema\ConsultaCampos;
use App\Sistema\Presentacion;
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

        $consulta = User::with(['role', 'bodega', 'creator.role', 'supervisoresAsignados', 'almacenistasAsignados'])
            ->when(!$this->esAdmin(), function ($query) {
                $query->where('created_by', auth()->id());
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($roleId, fn ($query) => $query->where('role_id', $roleId));
        app(ConsultaCampos::class)->filtrar($consulta, $request, 'usuarios');
        $usuarios = $consulta->orderBy('name')
            ->paginate(15)
            ->appends($request->query());

        $rolesFiltro = $this->esAdmin()
            ? Role::orderBy('nombre')->get()
            : $this->rolesPermitidosParaGestion();

        $tablaCampos = app(Presentacion::class)->operativa('usuarios', $request->user(), $usuarios->pluck('id'));

        return view('admin.usuarios.index', compact('usuarios', 'rolesFiltro', 'tablaCampos'));
    }

    public function create()
    {
        $roles = $this->rolesPermitidosParaGestion();
        $bodegas = Bodega::orderBy('nombre')->get();

        $rolAlmacenistaId = $this->rolAlmacenistaId();
        $rolSupervisorId = $this->rolSupervisorId();

        $almacenistas = User::query()
            ->deAmbito($rolAlmacenistaId)
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
        $ambito = Role::find($request->integer('role_id'))?->ambito();
        $almacenistasValidos = User::deAmbito($rolAlmacenistaId)->select('users.id');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'exists:roles,id', Rule::in($rolesPermitidos)],

            'bodega_id' => [
                Rule::requiredIf(fn () => $ambito === $rolAlmacenistaId),
                'nullable',
                'exists:bodegas,id',
            ],

            'almacenista_id' => [
                Rule::requiredIf(fn () => $ambito === $rolSupervisorId),
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('id', $almacenistasValidos)),
            ],
        ];

        $data = $request->validate($rules);

        $campos = app(ValoresCampos::class);
        $seguridad = app(Seguridad::class);
        if ($ambito === 1 && app(Permisos::class)->instalado()) {
            $seguridad->crearCuentaAdministrativa($request, $campos, desdeUsuarios: true);

            return redirect()->route($this->usuariosRoutePrefix().'.usuarios.index')->with('ok', 'Usuario creado correctamente.');
        }
        $valores = $campos->preparar($request, 'usuarios', true);
        $usuario = DB::transaction(function () use ($request, $data, $rolAlmacenistaId, $rolSupervisorId, $campos, $valores, $seguridad) {
            $seguridad->bloquear();
            $rol = Role::findOrFail($data['role_id']);
            abort_unless($seguridad->rolAsignable($request->user(), $rol), 403);
            $usuario = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'role_id' => $data['role_id'],
                'bodega_id' => $rol->ambito() === $rolAlmacenistaId
                    ? ($data['bodega_id'] ?? null)
                    : null,
                'created_by' => auth()->id(),
            ]);

            if ($usuario->rolOperativo() === $rolSupervisorId) {
                $usuario->almacenistasAsignados()->sync([
                    (int) $data['almacenista_id'],
                ]);
            }

            $campos->guardar('usuarios', $usuario->id, $valores);
            app(Auditoria::class)->registrar('seguridad', 'crear_usuario', $usuario->id, null, $usuario->only(['role_id', 'bodega_id']));
            return $usuario;
        });

        if ($usuario->rolOperativo() === $rolSupervisorId) {
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
            ->deAmbito($rolAlmacenistaId)
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
        $ambito = Role::find($request->integer('role_id'))?->ambito();
        $almacenistasValidos = User::deAmbito($rolAlmacenistaId)->select('users.id');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'role_id' => ['required', 'exists:roles,id', Rule::in($rolesPermitidos)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],

            'bodega_id' => [
                Rule::requiredIf(fn () => $ambito === $rolAlmacenistaId),
                'nullable',
                'exists:bodegas,id',
            ],

            'almacenista_id' => [
                Rule::requiredIf(fn () => $ambito === $rolSupervisorId),
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('id', $almacenistasValidos)),
            ],
        ];

        $data = $request->validate($rules);
        $seguridad = app(Seguridad::class);
        if (app(Permisos::class)->instalado() && ($ambito === 1 || $seguridad->rolAdministrativoPersonalizado($usuario->role))) {
            $seguridad->actualizarCuenta($request, $usuario, app(ValoresCampos::class), desdeUsuarios: true);

            return redirect()->route($this->usuariosRoutePrefix().'.usuarios.index')->with('ok', 'Usuario actualizado correctamente.');
        }
        $almacenistaAnteriorId = $usuario->almacenistasAsignados()->value('users.id');

        $campos = app(ValoresCampos::class);
        DB::transaction(function () use ($request, $data, $usuario, $rolAlmacenistaId, $rolSupervisorId, $campos, $seguridad) {
            $seguridad->bloquear();
            $usuario = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            $rol = Role::findOrFail($data['role_id']);
            abort_unless($seguridad->puedeGestionar($request->user(), $usuario) && $seguridad->rolAsignable($request->user(), $rol), 403);
            abort_if($request->user()->is($usuario) && (int) $usuario->role_id !== (int) $rol->id, 403);
            $antes = $usuario->only(['role_id', 'bodega_id']);
            $valores = $campos->preparar($request, 'usuarios', false, (string) $usuario->id);

            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $rol->id,
                'bodega_id' => $rol->ambito() === $rolAlmacenistaId
                    ? ($data['bodega_id'] ?? null)
                    : null,
            ];

            if (!empty($data['password'])) {
                $payload['password'] = bcrypt($data['password']);
            }

            if ((int) $usuario->role_id !== (int) $rol->id && app(Permisos::class)->instalado()) {
                $reglas = app(Permisos::class)->reglasUsuario($usuario);
                DB::table('sistema_permisos_usuario')->where('user_id', $usuario->id)->delete();
                if ($reglas !== []) {
                    app(Auditoria::class)->registrar('seguridad', 'retirar_permisos_por_cambio_rol', $usuario->id, $reglas, []);
                }
            }
            $usuario->update($payload);

            if ($rol->ambito() === $rolSupervisorId) {
                $usuario->almacenistasAsignados()->sync([
                    (int) $data['almacenista_id'],
                ]);
            } else {
                $usuario->almacenistasAsignados()->detach();
            }

            if ($rol->ambito() !== $rolAlmacenistaId) {
                $usuario->supervisoresAsignados()->detach();
            }
            $campos->guardar('usuarios', $usuario->id, $valores);
            app(Auditoria::class)->registrar('seguridad', 'actualizar_usuario', $usuario->id, $antes,
                $usuario->only(['role_id', 'bodega_id']) + ['credencial_actualizada' => isset($payload['password'])]);
        });

        $nuevoAlmacenistaId = $ambito === $rolSupervisorId
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
        if (!app(Seguridad::class)->puedeGestionar(auth()->user(), $usuario)) {
            return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
                ->with('error', 'No tienes permiso para eliminar este usuario.');
        }

        $seguridad = app(Seguridad::class);
        abort_unless(app(Permisos::class)->instalado(), 503);
        DB::transaction(function () use ($seguridad, $usuario) {
            $seguridad->bloquear();
            $usuario = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            abort_unless($seguridad->puedeGestionar(auth()->user(), $usuario) && !auth()->user()->is($usuario), 403);
            $seguridad->activarCuenta($usuario, false);
        });

        return redirect()->route($this->usuariosRoutePrefix() . '.usuarios.index')
            ->with('ok', 'Usuario desactivado.');
    }

    private function usuariosRoutePrefix(): string
    {
        return auth()->user()->rolOperativo() == 4 ? 'rrhh' : 'admin';
    }

    private function rolesPermitidosParaGestion()
    {
        return app(Seguridad::class)->rolesAsignables(auth()->user(), incluirAdministrativos: true);
    }

    private function puedeEditarUsuario(User $usuario): bool
    {
        return app(Seguridad::class)->puedeEditarDesdeUsuarios(auth()->user(), $usuario);
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
        return auth()->user()->rolOperativo() === 1;
    }
}
