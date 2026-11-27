<div class="ui-table-scroll" role="region" aria-label="Seguimiento de hojas firmadas" tabindex="0">
    <table class="ui-table">
        <thead><tr><th scope="col">Colaborador</th><th scope="col">Región</th><th scope="col">Fecha de entrega</th><th scope="col">Responsable</th><th scope="col">Estado</th><th scope="col" class="text-right">Acción</th></tr></thead>
        <tbody>
            @forelse($entregas as $entrega)
                @php($persona = $entrega->asignaciones->first()?->colaborador)
                <tr>
                    <td><strong class="text-ink">{{ $persona?->nombre ?? '—' }}</strong> <span>— {{ $persona?->codigo }}</span></td>
                    <td>{{ $persona?->region?->nombre ?? '—' }}</td>
                    <td>{{ $entrega->salida_en?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $entrega->asignaciones->first()?->user?->name ?? '—' }}</td>
                    <td>
                        @if($entrega->hoja_firmada)<x-ui.estado variante="exito">Hoja recibida</x-ui.estado>
                        @else<x-ui.estado variante="pendiente">Pendiente de firma</x-ui.estado>@endif
                    </td>
                    <td class="text-right">
                        @if($entregasConsultables->contains($entrega->id))
                            <x-ui.accion :href="route($routePrefix.'.asignaciones.entrega', $entrega->id)">Ver entrega</x-ui.accion>
                        @else
                            <span class="text-sm">Acceso limitado a los artículos propios</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><p class="ui-empty-state">No hay entregas que coincidan con los filtros.</p></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
