<x-ui.estado :variante="$segundos === null ? 'neutra' : ($segundos > 0 ? 'exito' : 'pendiente')">
    {{ $segundos === null ? 'Sin definir' : ($segundos > 0 ? 'Vigente' : 'Vencida') }}
</x-ui.estado>
