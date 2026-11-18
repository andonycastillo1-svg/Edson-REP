<x-sistema.pagina :titulo="'Cuenta · '.$usuario->name" :volver="route('sistema.seguridad')">
    <x-sistema.panel titulo="Permisos individuales">
        <p class="mb-4 text-sm text-slate-600">{{ $rol?->nombre ?? 'Sin rol' }} · {{ $usuario->acceso_activo ? 'Activa' : 'Inactiva' }}@if($super) · Superadministrador activo @endif</p>
        <form method="POST" action="{{ route('sistema.usuario.permisos', $usuario) }}" class="space-y-4">
            @csrf @method('PUT')
            <x-sistema.reglas :catalogo="$catalogo" :reglas="$reglas" :efectivos="$efectivos" />
            <x-sistema.confirmacion />
            <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Guardar permisos</button>
        </form>
    </x-sistema.panel>
    <div class="grid gap-5 lg:grid-cols-2">
        <x-sistema.panel titulo="Estado de la cuenta">
            <form method="POST" action="{{ route('sistema.usuario.estado', $usuario) }}" class="space-y-4">
                @csrf @method('PUT')
                <input type="hidden" name="activo" value="{{ $usuario->acceso_activo ? 0 : 1 }}">
                <x-sistema.confirmacion />
                <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">{{ $usuario->acceso_activo ? 'Desactivar cuenta' : 'Reactivar cuenta' }}</button>
            </form>
        </x-sistema.panel>
        @if($usuario->rolOperativo() === 1)
            <x-sistema.panel titulo="Superadministrador">
                <form method="POST" action="{{ route('sistema.usuario.superadministrador', $usuario) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="activo" value="{{ $super ? 0 : 1 }}">
                    <x-sistema.confirmacion />
                    <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">{{ $super ? 'Revocar autorización' : 'Otorgar autorización' }}</button>
                </form>
            </x-sistema.panel>
        @endif
    </div>
    <x-sistema.panel titulo="Administrar acceso de la cuenta">
        <form method="POST" action="{{ route('sistema.usuario.acceso', $usuario) }}" class="grid gap-4 sm:grid-cols-2">
            @csrf @method('PUT')
            <label class="text-sm font-semibold">Nombre<input name="name" value="{{ $usuario->name }}" required class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Correo<input name="email" type="email" value="{{ $usuario->email }}" required class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Rol
                <select name="role_id" required class="mt-1 w-full rounded-xl border-slate-300">
                    <option value="">Seleccionar</option>
                    @foreach($roles as $opcion)<option value="{{ $opcion->id }}" @selected($usuario->role_id === $opcion->id)>{{ $opcion->nombre }} · {{ \App\Sistema\CatalogoPermisos::AMBITOS[$opcion->ambito()] }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm font-semibold">Bodega para el ámbito Operador
                <select name="bodega_id" class="mt-1 w-full rounded-xl border-slate-300">
                    <option value="">Seleccionar</option>
                    @foreach($bodegas as $bodega)<option value="{{ $bodega->id }}" @selected($usuario->bodega_id === $bodega->id)>{{ $bodega->nombre }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm font-semibold">Almacenista para el ámbito Supervisor
                <select name="almacenista_id" class="mt-1 w-full rounded-xl border-slate-300">
                    <option value="">Seleccionar</option>
                    @foreach($almacenistas as $operador)<option value="{{ $operador->id }}" @selected((int) $almacenistaId === $operador->id)>{{ $operador->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm font-semibold">Nueva contraseña (opcional)<input name="password" type="password" autocomplete="new-password" minlength="16" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Repetir nueva contraseña<input name="password_confirmation" type="password" autocomplete="new-password" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <x-sistema.confirmacion />
            <div class="sm:col-span-2"><button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Guardar acceso de la cuenta</button></div>
        </form>
    </x-sistema.panel>
</x-sistema.pagina>
