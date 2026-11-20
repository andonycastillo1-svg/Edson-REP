<x-sistema.pagina titulo="Importar asignaciones desde Excel" :volver="route('dashboard')">
    @include('flujo.accesos')
    <x-sistema.panel :titulo="'Bodega de salida · '.$bodega->nombre">
        <p class="mb-3 text-sm">Completa la hoja Asignaciones. Agrupa en una misma fecha los productos de cada colaborador.</p>
        <a class="ui-btn-secondary" href="{{ route('flujo.importar.plantilla') }}">Descargar plantilla Excel</a>
        <form method="POST" enctype="multipart/form-data" action="{{ route('flujo.importar.preview') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <label class="text-sm font-semibold">Archivo XLSX<input name="excel" type="file" accept=".xlsx" required class="mt-1 block w-full"></label>
            <button class="ui-btn-primary">Validar y ver asignaciones</button>
        </form>
    </x-sistema.panel>
    <x-sistema.panel titulo="Importaciones registradas">
        <ul class="divide-y divide-slate-200 text-sm">
            @forelse($importaciones as $lote)
                <li class="py-3"><a href="{{ route('flujo.importar.show', $lote) }}" class="font-semibold underline">{{ $lote->confirmada_en->format('d/m/Y H:i') }} · {{ count($lote->entregas) }} entregas</a></li>
            @empty
                <li class="py-2">Todavía no has registrado importaciones.</li>
            @endforelse
        </ul>
        {{ $importaciones->links() }}
    </x-sistema.panel>
</x-sistema.pagina>
