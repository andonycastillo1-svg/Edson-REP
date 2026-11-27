<x-sistema.pagina titulo="Nueva asignación" :volver="route($prefijo.'.asignaciones.index')">
    <x-sistema.panel titulo="¿Cómo quieres registrar la entrega?">
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="space-y-2">
                <x-ui.accion :href="route($prefijo.'.asignaciones.create')" variante="primaria">Asignación manual</x-ui.accion>
                <p class="text-sm text-muted">Entrega de productos a un colaborador.</p>
            </div>
            @if($puedeImportar)
                <div class="space-y-2">
                    <x-ui.accion :href="route('flujo.importar.index')">Asignación masiva por Excel</x-ui.accion>
                    <p class="text-sm text-muted">Entrega a varios colaboradores e historial de importaciones.</p>
                </div>
            @endif
        </div>
    </x-sistema.panel>
</x-sistema.pagina>
