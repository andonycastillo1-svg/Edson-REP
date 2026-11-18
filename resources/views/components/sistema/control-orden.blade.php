@props(['name', 'valor', 'etiqueta'])
<div class="flex flex-wrap items-end gap-1.5">
    <label class="text-xs font-semibold">Orden
        <input type="number" name="{{ $name }}" value="{{ $valor }}" data-posicion min="0" max="65000" required
               aria-label="Posición de {{ $etiqueta }}" class="mt-1 block w-20">
    </label>
    <button type="button" class="ui-btn-secondary min-h-10 w-auto px-2" aria-label="Subir {{ $etiqueta }}" data-mover="arriba" hidden>↑</button>
    <button type="button" class="ui-btn-secondary min-h-10 w-auto px-2" aria-label="Bajar {{ $etiqueta }}" data-mover="abajo" hidden>↓</button>
</div>
