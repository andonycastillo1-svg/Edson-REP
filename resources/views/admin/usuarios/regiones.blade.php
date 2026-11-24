<x-sistema.pagina :titulo="'Regiones de '.$usuario->name" :volver="route(auth()->user()->rolOperativo() === 4 ? 'rrhh.usuarios.edit' : 'admin.usuarios.edit', $usuario)">
    <x-sistema.panel titulo="Responsabilidad actual">
        <p class="mb-3 text-sm">Edita el usuario para cambiar sus regiones. Los consolidados enviados conservan su responsable hasta una reasignación explícita.</p>
        <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($regiones->where('coordinador_id', $usuario->id) as $region)
                <li class="flujo-card">{{ $region->nombre }} · {{ $region->activa ? 'Activa' : 'Inactiva' }}</li>
            @empty
                <li class="text-sm">Este coordinador no tiene regiones asignadas.</li>
            @endforelse
        </ul>
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
