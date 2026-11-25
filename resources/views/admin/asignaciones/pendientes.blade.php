<div class="ui-table-scroll" role="region" aria-label="Hojas firmadas faltantes" tabindex="0">
    <table class="ui-table">
        <thead><tr><th scope="col">Colaborador</th><th scope="col">Fecha de entrega</th><th scope="col">Documento</th><th scope="col">Estado</th><th scope="col" class="text-right">Acción</th></tr></thead>
        <tbody>
            @forelse($entregas as $entrega)
                @php($persona = $entrega->asignaciones->first()?->colaborador)
                <tr>
                    <td><strong class="text-ink">{{ $persona?->nombre ?? '—' }}</strong><p>{{ $persona?->codigo }}</p></td>
                    <td>{{ $entrega->salida_en?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>Hoja de asignación firmada</td>
                    <td><x-ui.estado variante="pendiente">Pendiente</x-ui.estado></td>
                    <td class="text-right">
                        @if($entregasConsultables->contains($entrega->id))
                            <x-ui.accion :href="route($routePrefix.'.asignaciones.entrega', $entrega->id)">Ver entrega</x-ui.accion>
                        @else
                            <span class="text-sm">Acceso limitado a los artículos propios</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><p class="ui-empty-state">No hay hojas pendientes que coincidan con la búsqueda.</p></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
