<x-sistema.pagina :titulo="'Cuenta · '.$usuario->name" :volver="route('sistema.seguridad', ['vista' => 'cuentas'])" configuracion>
    <div data-pestanas data-pestana-inicial="{{ old('panel_cuenta', '#informacion-general') }}" class="space-y-4">
        <nav aria-label="Apartados de la cuenta" class="sistema-pestanas">
            <a href="#informacion-general" data-pestana>Información general</a>
            <a href="#permisos" data-pestana>Permisos</a>
            <a href="#relaciones" data-pestana>Relaciones</a>
            <a href="#delegaciones" data-pestana>Delegaciones</a>
            <a href="#seguridad" data-pestana>Seguridad</a>
        </nav>

        <x-sistema.panel titulo="Información general" id="informacion-general" class="scroll-mt-20" data-panel-pestana>
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                <span @class(['ui-badge', 'ui-badge-success' => $usuario->acceso_activo, 'ui-badge-danger' => !$usuario->acceso_activo])>{{ $usuario->acceso_activo ? 'Cuenta activa' : 'Cuenta inactiva' }}</span>
                @if($super)<span class="ui-badge ui-badge-warning">Superadministrador</span>@endif
            </div>
            <form method="POST" action="{{ route('sistema.usuario.acceso', $usuario) }}" class="sistema-formulario"
                  data-validacion-formulario="cuenta" data-datos-cuenta>
                @csrf @method('PUT')
                <input type="hidden" name="formulario_cuenta" value="cuenta">
                <label class="text-sm font-semibold">Nombre<input name="name" value="{{ old('name', $usuario->name) }}" maxlength="255" required class="mt-1 w-full"></label>
                <label class="text-sm font-semibold">Correo electrónico<input name="email" type="email" value="{{ old('email', $usuario->email) }}" maxlength="255" required class="mt-1 w-full"></label>
                <label for="rol-cuenta" class="text-sm font-semibold">Rol
                    <select name="role_id" id="rol-cuenta" aria-label="Rol" required class="mt-1 w-full">
                        <option value="">Seleccionar</option>
                        @foreach($roles as $opcion)
                            <option value="{{ $opcion->id }}" data-ambito="{{ $opcion->ambito() }}" @selected((string) old('role_id', $usuario->role_id) === (string) $opcion->id)>{{ $opcion->nombre }} · {{ \App\Sistema\CatalogoPermisos::AMBITOS[$opcion->ambito()] }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs font-normal">Al cambiar el rol se retiran las excepciones individuales de la cuenta.</span>
                </label>
                <label for="bodega-cuenta" class="text-sm font-semibold" data-para-ambito="2">Bodega asignada
                    <select name="bodega_id" id="bodega-cuenta" aria-label="Bodega asignada" class="mt-1 w-full">
                        <option value="">Seleccionar</option>
                        @foreach($bodegas as $bodega)<option value="{{ $bodega->id }}" @selected((string) old('bodega_id', $usuario->bodega_id) === (string) $bodega->id)>{{ $bodega->nombre }}</option>@endforeach
                    </select>
                    <span class="mt-1 block text-xs font-normal">Obligatoria para el ámbito Operador.</span>
                </label>
                <label for="almacenista-cuenta" class="text-sm font-semibold" data-para-ambito="3">Almacenista
                    <select name="almacenista_id" id="almacenista-cuenta" aria-label="Almacenista" class="mt-1 w-full">
                        <option value="">Seleccionar</option>
                        @foreach($almacenistas as $operador)<option value="{{ $operador->id }}" @selected((string) old('almacenista_id', $almacenistaId) === (string) $operador->id)>{{ $operador->name }}</option>@endforeach
                    </select>
                    <span class="mt-1 block text-xs font-normal">Obligatorio para el ámbito Supervisor.</span>
                </label>
                <div class="sm:col-span-2"><x-sistema.confirmacion /></div>
                <div class="sm:col-span-2"><button type="submit" class="ui-btn-primary">Guardar información general</button></div>
            </form>
        </x-sistema.panel>

        <x-sistema.panel titulo="Permisos" id="permisos" class="scroll-mt-20" data-panel-pestana>
            @if($rol)<p class="mb-3 text-sm">Rol asignado <strong>{{ $rol->nombre }}</strong></p>@endif
            <form method="POST" action="{{ route('sistema.usuario.permisos', $usuario) }}" class="space-y-4" data-validacion-formulario="reglas">
                @csrf @method('PUT')
                <input type="hidden" name="panel_cuenta" value="#permisos">
                <input type="hidden" name="formulario_cuenta" value="reglas">
                <x-sistema.reglas :catalogo="$catalogo" :reglas="$reglas" :efectivos="$efectivos" :heredados="$heredados"
                    :bloqueo="!$usuario->acceso_activo ? 'Cuenta inactiva' : ($rol?->activo === false ? 'Rol inactivo' : null)" individual />
                <div id="confirmar-permisos" tabindex="-1" class="scroll-mt-20 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <x-sistema.confirmacion />
                    <button type="submit" class="ui-btn-primary">Guardar permisos</button>
                </div>
            </form>
        </x-sistema.panel>

        @include('sistema.relaciones-usuario')
        @include('sistema.delegaciones-usuario')

        <x-sistema.panel titulo="Seguridad" id="seguridad" class="scroll-mt-20" data-panel-pestana>
            <div class="space-y-3">
                <details class="rounded-xl border border-slate-200" @if(old('formulario_cuenta') === 'clave') open @endif>
                    <summary class="sistema-desplegable">Cambiar contraseña</summary>
                    <form method="POST" action="{{ route('sistema.usuario.contrasena', $usuario) }}" class="space-y-3 p-3"
                          data-validacion-formulario="clave" data-confirmar="¿Cambiar la contraseña de esta cuenta?">
                        @csrf @method('PUT')
                        <input type="hidden" name="panel_cuenta" value="#seguridad">
                        <input type="hidden" name="formulario_cuenta" value="clave">
                        <p class="text-sm">Usa al menos 16 caracteres, con mayúsculas, minúsculas, números y símbolos.</p>
                        <div class="sistema-formulario">
                            <label class="text-sm font-semibold">Nueva contraseña
                                <input name="password" type="password" autocomplete="new-password" minlength="16" required class="mt-1 w-full">
                            </label>
                            <label class="text-sm font-semibold">Repetir nueva contraseña
                                <input name="password_confirmation" type="password" autocomplete="new-password" minlength="16" required class="mt-1 w-full">
                            </label>
                        </div>
                        <x-sistema.confirmacion />
                        <button type="submit" class="ui-btn-primary">Guardar contraseña</button>
                    </form>
                </details>

                <details class="rounded-xl border border-slate-200" @if(old('formulario_cuenta') === 'estado') open @endif>
                    <summary class="sistema-desplegable">Estado de la cuenta · {{ $usuario->acceso_activo ? 'Activa' : 'Inactiva' }}</summary>
                    <form method="POST" action="{{ route('sistema.usuario.estado', $usuario) }}" class="space-y-3 p-3"
                          data-validacion-formulario="estado" data-confirmar="{{ $usuario->acceso_activo ? '¿Desactivar esta cuenta?' : '¿Reactivar esta cuenta?' }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="panel_cuenta" value="#seguridad">
                        <input type="hidden" name="formulario_cuenta" value="estado">
                        <input type="hidden" name="activo" value="{{ $usuario->acceso_activo ? 0 : 1 }}">
                        <p class="text-sm">{{ $usuario->acceso_activo ? 'Al desactivarla se suspende su acceso y se conservan sus registros.' : 'Al reactivarla recupera el acceso según su rol y sus reglas individuales.' }}</p>
                        <x-sistema.confirmacion />
                        <button type="submit" class="{{ $usuario->acceso_activo ? 'ui-btn-danger' : 'ui-btn-primary' }}">{{ $usuario->acceso_activo ? 'Desactivar cuenta' : 'Reactivar cuenta' }}</button>
                    </form>
                </details>

                @if($usuario->rolOperativo() === 1)
                    <details class="rounded-xl border border-slate-200" @if(old('formulario_cuenta') === 'super') open @endif>
                        <summary class="sistema-desplegable">Acceso de Superadministrador</summary>
                        <form method="POST" action="{{ route('sistema.usuario.superadministrador', $usuario) }}" class="space-y-3 p-3"
                              data-validacion-formulario="super" data-confirmar="{{ $super ? '¿Revocar el acceso de Superadministrador?' : '¿Convertir esta cuenta en Superadministrador?' }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="panel_cuenta" value="#seguridad">
                            <input type="hidden" name="formulario_cuenta" value="super">
                            <input type="hidden" name="activo" value="{{ $super ? 0 : 1 }}">
                            <p class="ui-alert-warning">Este privilegio permite gestionar roles, accesos de cuentas y configuraciones protegidas. Concédelo solo a quienes tengan esa responsabilidad.</p>
                            <x-sistema.confirmacion />
                            <button type="submit" class="ui-btn-danger">{{ $super ? 'Revocar acceso de Superadministrador' : 'Convertir en Superadministrador' }}</button>
                        </form>
                    </details>
                @endif
            </div>
        </x-sistema.panel>
    </div>
</x-sistema.pagina>
