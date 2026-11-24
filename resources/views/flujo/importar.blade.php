<x-sistema.pagina titulo="Importar asignaciones desde Excel" descripcion="Revisa el archivo antes de registrar asignaciones y descontar existencias." :volver="route('dashboard')">
    @include('flujo.accesos')
    <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <x-sistema.panel>
            <div class="flujo-cabecera-panel">
                <h2 class="text-lg font-bold">Cargar asignaciones</h2>
                <span class="ui-badge ui-badge-info">Salida de {{ $bodega->nombre }}</span>
            </div>
            <form method="POST" enctype="multipart/form-data" action="{{ route('flujo.importar.preview') }}" class="space-y-3">
                @csrf
                <label class="block ui-carga text-sm font-semibold">Archivo de asignaciones
                    <input name="excel" type="file" accept=".xlsx" required class="mt-2 block w-full" aria-describedby="excel-formato">
                    <span id="excel-formato" class="mt-2 block font-normal text-slate-600">Formato XLSX de hasta 4 MB. Máximo 500 filas.</span>
                </label>
                <button class="ui-btn-primary w-full sm:w-auto">Revisar archivo</button>
            </form>
        </x-sistema.panel>
        <x-sistema.panel titulo="Plantilla de asignaciones">
            <p class="mb-3 text-sm">Completa la hoja Asignaciones con el código del colaborador y del producto. Usa la misma fecha para los productos del mismo colaborador.</p>
            <a class="ui-btn-secondary" href="{{ route('flujo.importar.plantilla') }}">Descargar plantilla Excel</a>
        </x-sistema.panel>
    </div>
    <x-sistema.panel titulo="Importaciones registradas">
        <ul class="divide-y divide-slate-200 text-sm">
            @forelse($importaciones as $lote)
                <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <a href="{{ route('flujo.importar.show', $lote) }}" class="flujo-enlace">{{ $lote->confirmada_en->format('d/m/Y H:i') }}</a>
                    <span class="ui-badge ui-badge-success">{{ count($lote->entregas) }} hojas de asignación</span>
                </li>
            @empty
                <li class="py-2">Todavía no has registrado importaciones.</li>
            @endforelse
        </ul>
        {{ $importaciones->links() }}
    </x-sistema.panel>
</x-sistema.pagina>
