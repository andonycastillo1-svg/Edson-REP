<x-sistema.pagina titulo="Roles y permisos" :volver="route('sistema.configuracion')">
    <div class="grid gap-6 lg:grid-cols-2">
        <x-sistema.panel titulo="Roles">
            <ul class="divide-y divide-slate-200">
                @foreach($roles as $rol)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <a class="font-semibold text-blue-700" href="{{ route('sistema.rol', $rol) }}">{{ $rol->nombre }}</a>
                        <span class="text-sm text-slate-600">{{ $rol->usuarios_count }} cuentas · {{ $rol->activo ? 'Activo' : 'Inactivo' }}</span>
                    </li>
                @endforeach
            </ul>
        </x-sistema.panel>
        <x-sistema.panel titulo="Crear o duplicar un rol">
            <form method="POST" action="{{ route('sistema.rol.crear') }}" class="space-y-4">
                @csrf
                <label class="block text-sm font-semibold">Nombre<input name="nombre" value="{{ old('nombre') }}" required maxlength="50" class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="block text-sm font-semibold">Descripción<textarea name="descripcion" maxlength="500" rows="2" class="mt-1 w-full rounded-xl border-slate-300">{{ old('descripcion') }}</textarea></label>
                <label class="block text-sm font-semibold">Ámbito operativo
                    <select name="ambito_operativo" required class="mt-1 w-full rounded-xl border-slate-300">
                        @foreach(\App\Sistema\CatalogoPermisos::AMBITOS as $id => $nombre)
                            <option value="{{ $id }}" @selected(old('ambito_operativo') == $id)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold">Copiar permisos de un rol del mismo ámbito
                    <select name="copiar_rol" class="mt-1 w-full rounded-xl border-slate-300">
                        <option value="">Crear sin permisos</option>
                        @foreach($roles as $rol)<option value="{{ $rol->id }}" @selected(old('copiar_rol') == $rol->id)>{{ $rol->nombre }}</option>@endforeach
                    </select>
                </label>
                <x-sistema.confirmacion />
                <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Crear rol</button>
            </form>
        </x-sistema.panel>
    </div>
    <x-sistema.panel titulo="Cuentas y permisos individuales">
        <form method="GET" class="mb-4 flex flex-wrap gap-2">
            <input name="q" value="{{ $q }}" aria-label="Buscar por nombre o correo" placeholder="Nombre o correo" class="rounded-xl border-slate-300">
            <button class="rounded-xl bg-slate-100 px-4 py-2 font-semibold">Buscar</button>
        </form>
        <ul class="divide-y divide-slate-200">
            @forelse($usuarios as $usuario)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <a class="font-semibold text-blue-700" href="{{ route('sistema.usuario', $usuario) }}">{{ $usuario->name }} <span class="font-normal text-slate-500">#{{ $usuario->id }}</span></a>
                    <span class="text-sm text-slate-600">{{ $usuario->role?->nombre ?? 'Sin rol' }} · {{ $usuario->acceso_activo ? 'Activa' : 'Inactiva' }}</span>
                </li>
            @empty<li class="py-3 text-sm text-slate-500">Sin coincidencias.</li>@endforelse
        </ul>
        <div class="mt-4">{{ $usuarios->links() }}</div>
    </x-sistema.panel>
    <x-sistema.panel>
        <details>
            <summary class="cursor-pointer font-bold text-slate-900">Crear cuenta administrativa</summary>
            <form method="POST" enctype="multipart/form-data" action="{{ route('sistema.usuario.crear') }}" class="mt-3 grid gap-4 sm:grid-cols-2">
                @csrf
                <label class="text-sm font-semibold">Nombre<input name="name" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="text-sm font-semibold">Correo<input name="email" type="email" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="text-sm font-semibold">Rol
                    <select name="role_id" required class="mt-1 w-full rounded-xl border-slate-300">
                        @foreach($roles->filter(fn ($r) => $r->activo && $r->ambito() === 1) as $rol)<option value="{{ $rol->id }}">{{ $rol->nombre }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold">Contraseña inicial<input name="password" type="password" autocomplete="new-password" minlength="16" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="text-sm font-semibold">Repetir contraseña<input name="password_confirmation" type="password" autocomplete="new-password" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                <x-sistema.confirmacion />
                <div class="sm:col-span-2"><x-sistema.campos modulo="usuarios" /></div>
                <div class="sm:col-span-2"><button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Crear cuenta</button></div>
            </form>
        </details>
    </x-sistema.panel>
</x-sistema.pagina>
