<x-sistema.pagina :titulo="'Rol · '.$rol->nombre" :volver="route('sistema.seguridad')">
    <x-sistema.panel titulo="Información y permisos">
        <p class="mb-4 text-sm text-slate-600">Ámbito {{ \App\Sistema\CatalogoPermisos::AMBITOS[$rol->ambito()] ?? 'Sin ámbito' }} · {{ $rol->usuarios_count }} cuentas</p>
        <form method="POST" action="{{ route('sistema.rol.actualizar', $rol) }}" class="space-y-4">
            @csrf @method('PUT')
            <label class="block max-w-lg text-sm font-semibold">Nombre<input name="nombre" required maxlength="50" value="{{ old('nombre', $rol->nombre) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="block max-w-lg text-sm font-semibold">Descripción<textarea name="descripcion" maxlength="500" class="mt-1 w-full rounded-xl border-slate-300">{{ old('descripcion', $rol->descripcion) }}</textarea></label>
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" @checked($rol->activo)> Rol activo</label>
            <x-sistema.reglas :catalogo="$catalogo" :reglas="$reglas" :efectivos="$efectivos" />
            <x-sistema.confirmacion />
            <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Guardar rol</button>
        </form>
    </x-sistema.panel>
    <x-sistema.panel titulo="Cuentas asociadas">
        <ul class="divide-y divide-slate-200">
            @forelse($usuarioRol as $usuario)
                <li class="py-2"><a class="text-sm font-semibold text-blue-700" href="{{ route('sistema.usuario', $usuario) }}">{{ $usuario->name }} · #{{ $usuario->id }}</a></li>
            @empty<li class="py-2 text-sm text-slate-500">Este rol no tiene cuentas asociadas.</li>@endforelse
        </ul>
        {{ $usuarioRol->links() }}
    </x-sistema.panel>
</x-sistema.pagina>
