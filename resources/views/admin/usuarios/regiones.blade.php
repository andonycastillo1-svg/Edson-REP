<x-sistema.pagina :titulo="'Regiones de '.$usuario->name" :volver="route('admin.usuarios.edit', $usuario)">
    <x-sistema.panel titulo="Responsabilidad actual">
        <p class="mb-3 text-sm">Selecciona las regiones que atenderá este coordinador. Los consolidados enviados conservan su responsable hasta una reasignación explícita.</p>
        <form method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="formulario_regiones" value="1">
            <fieldset class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <legend class="sr-only">Regiones</legend>
                @foreach($regiones as $region)
                    <label class="flujo-eleccion">
                        <input class="mt-1" name="regiones[]" type="checkbox" value="{{ $region->id }}"
                            @checked(old('formulario_regiones') ? in_array($region->id, old('regiones', [])) : $region->coordinador_id == $usuario->id)
                            @disabled(!$region->activa)>
                        <span><strong class="block">{{ $region->nombre }}</strong>{{ $region->coordinador?->name ?? 'Sin responsable' }}{{ $region->activa ? '' : ' · Inactiva' }}</span>
                    </label>
                @endforeach
            </fieldset>
            <label class="ui-check"><input type="checkbox" name="reasignar" value="1"> Confirmo el cambio de responsable para las regiones ya asignadas</label>
            <div><button class="ui-btn-primary">Guardar regiones</button></div>
        </form>
    </x-sistema.panel>
    <x-sistema.panel titulo="Historial de responsables">
        <ul class="divide-y text-sm">
            @forelse($historial as $cambio)
                <li class="py-3"><strong>{{ $cambio->region }}</strong> · {{ $cambio->anterior ?? 'Sin responsable' }} → {{ $cambio->nuevo ?? 'Sin responsable' }}
                    <span class="block text-slate-600">{{ $cambio->actor }} · {{ $cambio->created_at }}</span></li>
            @empty
                <li>Todavía no hay cambios registrados.</li>
            @endforelse
        </ul>
        {{ $historial->links() }}
    </x-sistema.panel>
</x-sistema.pagina>
