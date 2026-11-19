<div data-fila-opcion="{{ $i }}" class="grid gap-2 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-4 sm:items-end">
    @if(!empty($opcion['id']))<input type="hidden" name="opciones[{{ $i }}][id]" value="{{ $opcion['id'] }}">@endif
    <label class="text-sm font-semibold sm:col-span-2">Etiqueta de la opción
        <input data-etiqueta-opcion name="opciones[{{ $i }}][etiqueta]" value="{{ $opcion['etiqueta'] ?? '' }}" maxlength="100" class="mt-1 w-full">
    </label>
    <label class="text-sm font-semibold">Orden<input type="number" name="opciones[{{ $i }}][orden]" value="{{ $opcion['orden'] ?? 0 }}" min="0" max="65000" class="mt-1 w-full rounded-xl border-slate-300"></label>
    <x-sistema.casilla :name="'opciones['.$i.'][activo]'" :id="'opcion-activa-'.$i" :marcada="$opcion['activo'] ?? true">Opción activa</x-sistema.casilla>
    @if(empty($opcion['id']))
        <button type="button" data-quitar-opcion class="text-left text-xs font-semibold text-rose-800 underline sm:col-span-4">Quitar opción nueva</button>
    @endif
</div>
