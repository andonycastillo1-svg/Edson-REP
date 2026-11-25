<x-sistema.pagina titulo="Roles y permisos" :volver="route('sistema.configuracion')" configuracion>
    @php($verCuentas = request('vista') === 'cuentas' || request()->filled('q') || old('formulario_seguridad') === 'cuenta')
    <nav aria-label="Administración de acceso" class="sistema-pestanas">
        <a href="{{ route('sistema.seguridad') }}" @unless($verCuentas) aria-current="page" @endunless>Roles</a>
        <a href="{{ route('sistema.seguridad', ['vista' => 'cuentas']) }}" @if($verCuentas) aria-current="page" @endif>Cuentas y excepciones</a>
    </nav>
    @unless($verCuentas)
    <div class="grid gap-4 lg:grid-cols-2">
        <x-sistema.panel titulo="Roles">
            <p class="mb-2 text-sm">Selecciona un rol para administrar los permisos de sus cuentas.</p>
            <ul class="divide-y divide-slate-200">
                @foreach($roles as $rol)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <a class="font-semibold text-blue-700" href="{{ route('sistema.rol', $rol) }}">{{ $rol->nombre }}</a>
                        <span class="text-sm text-slate-600">{{ $rol->usuarios_count }} cuentas · {{ $rol->activo ? 'Activo' : 'Inactivo' }}</span>
                    </li>
                @endforeach
            </ul>
        </x-sistema.panel>
        <x-sistema.panel>
            <details @if(old('ambito_operativo')) open @endif>
                <summary class="sistema-desplegable">Crear o duplicar un rol</summary>
            <form method="POST" action="{{ route('sistema.rol.crear') }}" class="mt-3 space-y-4">
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
                <button type="submit" class="ui-btn-primary">Crear rol</button>
            </form>
            </details>
        </x-sistema.panel>
    </div>
    @else
    <x-sistema.panel titulo="Cuentas y permisos individuales">
        <form method="GET" class="mb-4 flex flex-wrap gap-2">
            <input type="hidden" name="vista" value="cuentas">
            <input name="q" value="{{ $q }}" aria-label="Buscar por nombre o correo" placeholder="Nombre o correo" class="rounded-xl border-slate-300">
            <button type="submit" class="ui-btn-secondary">Buscar cuenta</button>
        </form>
        <ul class="divide-y divide-slate-200">
            @forelse($usuarios as $usuario)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <a class="font-semibold text-blue-700" href="{{ route('sistema.usuario', $usuario) }}">{{ $usuario->name }} <span class="ml-1 break-all font-normal text-slate-600">{{ $usuario->email }}</span></a>
                    <span class="text-sm text-slate-600">{{ $usuario->role?->nombre ?? 'Sin rol' }} · {{ $usuario->acceso_activo ? 'Activa' : 'Inactiva' }}</span>
                </li>
            @empty<li class="py-3 text-sm text-slate-500">Sin coincidencias.</li>@endforelse
        </ul>
        <div class="mt-4">{{ $usuarios->links() }}</div>
    </x-sistema.panel>
    <x-sistema.panel>
        <details @if(old('name') || old('email') || old('role_id')) open @endif>
            <summary class="sistema-desplegable">Crear cuenta administrativa</summary>
            <form method="POST" enctype="multipart/form-data" action="{{ route('sistema.usuario.crear') }}" class="mt-3 sistema-formulario">
                @csrf
                <input type="hidden" name="formulario_seguridad" value="cuenta">
                <label class="text-sm font-semibold">Nombre<input name="name" value="{{ old('name') }}" maxlength="255" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="text-sm font-semibold">Correo electrónico<input name="email" type="email" value="{{ old('email') }}" maxlength="255" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="text-sm font-semibold">Rol
                    <select name="role_id" required class="mt-1 w-full rounded-xl border-slate-300">
                        @foreach($roles->filter(fn ($r) => $r->activo && $r->ambito() === 1) as $rol)<option value="{{ $rol->id }}" @selected(old('role_id') == $rol->id)>{{ $rol->nombre }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold">Contraseña inicial<input name="password" type="password" autocomplete="new-password" minlength="16" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                <label class="text-sm font-semibold">Repetir contraseña<input name="password_confirmation" type="password" autocomplete="new-password" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                <x-sistema.confirmacion />
                <div class="sm:col-span-2"><x-sistema.campos modulo="usuarios" /></div>
                <div class="sm:col-span-2"><button class="ui-btn-primary">Crear cuenta</button></div>
            </form>
        </details>
    </x-sistema.panel>
    @endunless
</x-sistema.pagina>
