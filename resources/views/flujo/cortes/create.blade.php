<x-sistema.pagina titulo="Nuevo corte" :volver="route('flujo.cortes.index')">
    @include('flujo.cortes.pasos', ['paso' => 1])
    <x-sistema.panel titulo="Período de inspecciones">
        <form method="POST" action="{{ route('flujo.cortes.store') }}" class="max-w-2xl space-y-4">
            @csrf
            <label class="ui-label block" for="nombre">Nombre del corte</label>
            <input id="nombre" class="ui-input" name="nombre" type="text" value="{{ old('nombre') }}" maxlength="160" required>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="ui-label" for="desde">Fecha inicial
                    <input id="desde" class="ui-input mt-1" name="desde" type="date" value="{{ old('desde') }}" required>
                </label>
                <label class="ui-label" for="hasta">Fecha final
                    <input id="hasta" class="ui-input mt-1" name="hasta" type="date" value="{{ old('hasta') }}" required>
                </label>
            </div>
            <p class="text-sm text-slate-600">Elige cualquier período. En el siguiente paso podrás revisar también las inspecciones pendientes previas a la fecha inicial.</p>
            <button class="ui-btn-primary">Consultar inspecciones</button>
        </form>
    </x-sistema.panel>
</x-sistema.pagina>
