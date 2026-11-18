<x-sistema.pagina titulo="Configuración de notificaciones" :volver="route('sistema.configuracion')">
    <p class="text-sm text-slate-700">Los destinatarios seleccionados deben tener acceso al evento en el módulo de origen. Una selección vacía conserva la regla de entrega del módulo.</p>
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <label class="text-sm font-semibold">Buscar cuentas para los selectores
            <input name="q" value="{{ $q }}" maxlength="100" class="mt-1 block rounded-xl border-slate-300">
        </label>
        <button class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Buscar</button>
    </form>
    <div class="grid gap-5 lg:grid-cols-2">
        @foreach(\App\Sistema\Ajustes::EVENTOS as $evento => $titulo)
            @php($regla = $reglas[$evento] ?? [])
            <x-sistema.panel :titulo="$titulo">
                <form method="POST" action="{{ route('sistema.notificaciones.guardar', $evento) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <label class="flex items-center gap-2 text-sm font-semibold"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" @checked($regla['activo'] ?? true)> Notificación activa</label>
                    <label class="block text-sm font-semibold">Roles
                        <select name="roles[]" multiple size="4" class="mt-1 block w-full rounded-xl border-slate-300">
                            @foreach($roles as $rol)<option value="{{ $rol->id }}" @selected(in_array($rol->id, $regla['roles'] ?? []))>{{ $rol->nombre }}</option>@endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">Usuarios
                        <select name="usuarios[]" multiple size="4" class="mt-1 block w-full rounded-xl border-slate-300">
                            @foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected(in_array($usuario->id, $regla['usuarios'] ?? []))>{{ $usuario->name }} · #{{ $usuario->id }}</option>@endforeach
                        </select>
                    </label>
                    @if($evento === 'stock_bajo' && app(\App\Sistema\Permisos::class)->permite(auth()->user(), 'sistema.parametros'))
                        <p class="text-sm"><a class="text-blue-700 underline" href="{{ route('sistema.parametros') }}">Configurar el nivel mínimo</a></p>
                    @endif
                    <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Guardar regla</button>
                </form>
            </x-sistema.panel>
        @endforeach
    </div>
    <x-sistema.panel titulo="Notificaciones obligatorias">
        <p class="text-sm text-slate-700">Las notificaciones de seguridad, supervisión y revisión de Recursos Humanos se mantienen activas.</p>
    </x-sistema.panel>
</x-sistema.pagina>
