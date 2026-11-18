<x-sistema.pagina :titulo="'Rol · '.$rol->nombre" :volver="route('sistema.seguridad')" configuracion>
    <x-sistema.panel titulo="Información y permisos">
        <p class="mb-4 text-sm text-slate-600">Ámbito {{ \App\Sistema\CatalogoPermisos::AMBITOS[$rol->ambito()] ?? 'Sin ámbito' }} · {{ $rol->usuarios_count }} cuentas</p>
        <form method="POST" action="{{ route('sistema.rol.actualizar', $rol) }}" class="space-y-4">
            @csrf @method('PUT')
            <details class="rounded-lg border border-slate-200" @if(old('nombre')) open @endif>
                <summary class="sistema-desplegable">Nombre y estado del rol</summary>
                <div class="space-y-3 p-3">
            <label class="block max-w-lg text-sm font-semibold">Nombre<input name="nombre" required maxlength="50" value="{{ old('nombre', $rol->nombre) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="block max-w-lg text-sm font-semibold">Descripción<textarea name="descripcion" maxlength="500" class="mt-1 w-full rounded-xl border-slate-300">{{ old('descripcion', $rol->descripcion) }}</textarea></label>
            <x-sistema.casilla name="activo" id="rol-activo" :marcada="old('activo', $rol->activo)">Rol activo</x-sistema.casilla>
                </div>
            </details>
            <x-sistema.reglas :catalogo="$catalogo" :reglas="$reglas" :efectivos="$efectivos" :rol-inactivo="!$rol->activo" />
            <x-sistema.confirmacion />
            <button class="ui-btn-primary" type="submit">Guardar rol</button>
        </form>
    </x-sistema.panel>
    <x-sistema.panel>
        <details>
            <summary class="sistema-desplegable">Cuentas asociadas ({{ $rol->usuarios_count }})</summary>
        <ul class="divide-y divide-slate-200">
            @forelse($usuarioRol as $usuario)
                <li class="py-2"><a class="text-sm font-semibold text-blue-700" href="{{ route('sistema.usuario', $usuario) }}">{{ $usuario->name }} · #{{ $usuario->id }}</a></li>
            @empty<li class="py-2 text-sm text-slate-500">Este rol no tiene cuentas asociadas.</li>@endforelse
        </ul>
        {{ $usuarioRol->links() }}
        </details>
    </x-sistema.panel>
</x-sistema.pagina>
