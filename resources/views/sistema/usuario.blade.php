<x-sistema.pagina :titulo="'Cuenta · '.$usuario->name" :volver="route('sistema.seguridad')">
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span @class(['ui-badge', 'ui-badge-success' => $usuario->acceso_activo, 'ui-badge-danger' => !$usuario->acceso_activo])>{{ $usuario->acceso_activo ? 'Cuenta activa' : 'Cuenta inactiva' }}</span>
        <span class="ui-badge ui-badge-info">{{ $rol?->nombre ?? 'Sin rol' }}</span>
        @if($super)<span class="ui-badge ui-badge-warning">Superadministrador</span>@endif
    </div>

    <nav aria-label="Apartados de la cuenta" class="flex flex-wrap gap-2">
        <a href="#informacion-general" class="ui-btn-secondary">Información general</a>
        <a href="#permisos" class="ui-btn-secondary">Permisos</a>
        <a href="#seguridad" class="ui-btn-secondary">Seguridad</a>
    </nav>

    <x-sistema.panel titulo="Información general" id="informacion-general" class="scroll-mt-20">
        <form id="cuenta-acceso" method="POST" action="{{ route('sistema.usuario.acceso', $usuario) }}" class="grid gap-4 sm:grid-cols-2">
            @csrf @method('PUT')
            <label class="text-sm font-semibold">Nombre<input name="name" value="{{ old('name', $usuario->name) }}" maxlength="255" required class="mt-1 w-full"></label>
            <label class="text-sm font-semibold">Correo electrónico<input name="email" type="email" value="{{ old('email', $usuario->email) }}" maxlength="255" required class="mt-1 w-full"></label>
            <label class="text-sm font-semibold">Rol
                <select name="role_id" required class="mt-1 w-full">
                    <option value="">Seleccionar</option>
                    @foreach($roles as $opcion)<option value="{{ $opcion->id }}" @selected((string) old('role_id', $usuario->role_id) === (string) $opcion->id)>{{ $opcion->nombre }} · {{ \App\Sistema\CatalogoPermisos::AMBITOS[$opcion->ambito()] }}</option>@endforeach
                </select>
                <span class="mt-1 block text-xs font-normal">Al cambiar el rol se retiran las excepciones individuales de la cuenta.</span>
            </label>
            <label class="text-sm font-semibold">Bodega
                <select name="bodega_id" class="mt-1 w-full">
                    <option value="">Seleccionar</option>
                    @foreach($bodegas as $bodega)<option value="{{ $bodega->id }}" @selected((string) old('bodega_id', $usuario->bodega_id) === (string) $bodega->id)>{{ $bodega->nombre }}</option>@endforeach
                </select>
                <span class="mt-1 block text-xs font-normal">Obligatoria para cuentas del ámbito Operador.</span>
            </label>
            <label class="text-sm font-semibold">Almacenista
                <select name="almacenista_id" class="mt-1 w-full">
                    <option value="">Seleccionar</option>
                    @foreach($almacenistas as $operador)<option value="{{ $operador->id }}" @selected((string) old('almacenista_id', $almacenistaId) === (string) $operador->id)>{{ $operador->name }}</option>@endforeach
                </select>
                <span class="mt-1 block text-xs font-normal">Obligatorio para cuentas del ámbito Supervisor.</span>
            </label>
            <div class="sm:col-span-2"><x-sistema.confirmacion /></div>
            <div class="sm:col-span-2"><button type="submit" class="ui-btn-primary">Guardar cambios de la cuenta</button></div>
        </form>
    </x-sistema.panel>

    <x-sistema.panel titulo="Permisos" id="permisos" class="scroll-mt-20">
        <form method="POST" action="{{ route('sistema.usuario.permisos', $usuario) }}" class="space-y-4">
            @csrf @method('PUT')
            <x-sistema.reglas :catalogo="$catalogo" :reglas="$reglas" :efectivos="$efectivos" individual />
            <x-sistema.confirmacion />
            <button type="submit" class="ui-btn-primary">Guardar permisos</button>
        </form>
    </x-sistema.panel>

    <x-sistema.panel titulo="Seguridad" id="seguridad" class="scroll-mt-20">
        <div class="grid gap-5 lg:grid-cols-2">
            <section class="space-y-3">
                <h3 class="font-semibold">Contraseña de la cuenta</h3>
                <p class="text-sm">Usa al menos 16 caracteres, con mayúsculas, minúsculas, números y símbolos. Completa ambos campos y guarda los cambios en Información general.</p>
                <label class="block text-sm font-semibold">Nueva contraseña
                    <input form="cuenta-acceso" name="password" type="password" autocomplete="new-password" minlength="16" class="mt-1 w-full">
                </label>
                <label class="block text-sm font-semibold">Repetir nueva contraseña
                    <input form="cuenta-acceso" name="password_confirmation" type="password" autocomplete="new-password" minlength="16" class="mt-1 w-full">
                </label>
                <a href="#informacion-general" class="ui-btn-secondary">Ir a Información general</a>
            </section>
            <section class="space-y-3">
                <h3 class="font-semibold">Estado de la cuenta</h3>
                <p class="text-sm">{{ $usuario->acceso_activo ? 'La cuenta está activa. Al desactivarla se suspende su acceso.' : 'La cuenta está inactiva. Reactívala para restablecer su acceso.' }}</p>
                <form method="POST" action="{{ route('sistema.usuario.estado', $usuario) }}" class="space-y-3"
                      data-confirmar="{{ $usuario->acceso_activo ? '¿Desactivar esta cuenta?' : '¿Reactivar esta cuenta?' }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="activo" value="{{ $usuario->acceso_activo ? 0 : 1 }}">
                    <x-sistema.confirmacion />
                    <button type="submit" class="{{ $usuario->acceso_activo ? 'ui-btn-danger' : 'ui-btn-primary' }}">{{ $usuario->acceso_activo ? 'Desactivar cuenta' : 'Reactivar cuenta' }}</button>
                </form>
            </section>
            @if($usuario->rolOperativo() === 1)
                <section class="space-y-3 lg:col-span-2">
                    <h3 class="font-semibold">Superadministrador</h3>
                    <p class="ui-alert-warning">Este privilegio permite gestionar roles, excepciones de acceso, cuentas administrativas, parámetros protegidos y autorizaciones de Superadministrador. Concédelo solo a cuentas autorizadas para administrar el sistema.</p>
                    <form method="POST" action="{{ route('sistema.usuario.superadministrador', $usuario) }}" class="space-y-3"
                          data-confirmar="{{ $super ? '¿Revocar el acceso de Superadministrador?' : '¿Convertir esta cuenta en Superadministrador?' }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="activo" value="{{ $super ? 0 : 1 }}">
                        <x-sistema.confirmacion />
                        <button type="submit" class="ui-btn-danger">{{ $super ? 'Revocar acceso de Superadministrador' : 'Convertir en Superadministrador' }}</button>
                    </form>
                </section>
            @endif
        </div>
    </x-sistema.panel>
</x-sistema.pagina>
