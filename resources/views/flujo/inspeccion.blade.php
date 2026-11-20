<x-sistema.pagina :titulo="'Inspección de '.$inspeccion->colaborador?->nombre" :volver="route('flujo.inspecciones.index')">
    <x-sistema.panel titulo="Datos registrados">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="font-bold">Fecha</dt><dd>{{ $inspeccion->fecha->format('d/m/Y') }}</dd></div>
            <div><dt class="font-bold">Región</dt><dd>{{ $inspeccion->region?->nombre }}</dd></div>
            <div><dt class="font-bold">Supervisor</dt><dd>{{ $inspeccion->supervisor?->name }}</dd></div>
            <div><dt class="font-bold">Puesto</dt><dd>{{ $inspeccion->puesto }}</dd></div>
            <div><dt class="font-bold">Estado</dt><dd>{{ \App\Models\Consolidado::ESTADOS[$inspeccion->consolidados->first()?->estado] ?? 'Recibido' }}</dd></div>
        </dl>
    </x-sistema.panel>
    <x-sistema.panel titulo="Productos solicitados">
        <ul class="divide-y divide-slate-200">
            @forelse($inspeccion->lineas as $linea)
                <li class="flex justify-between gap-3 py-2 text-sm">{{ $linea->etiqueta }} · {{ $linea->producto_codigo }} <strong>{{ $linea->cantidad }}</strong></li>
            @empty
                <li class="py-2 text-sm">Sin productos solicitados.</li>
            @endforelse
        </ul>
    </x-sistema.panel>
    <x-sistema.panel titulo="Fotografías">
        @forelse($inspeccion->fotos as $foto)
            <a class="block py-1 underline" href="{{ route('flujo.inspecciones.foto', [$inspeccion, $foto]) }}">{{ $foto->nombre }}</a>
        @empty
            <p class="text-sm">Sin fotografías.</p>
        @endforelse
    </x-sistema.panel>
</x-sistema.pagina>
