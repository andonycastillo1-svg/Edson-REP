<div class="grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-4 sm:items-end">
    @if(!empty($opcion['id']))<input type="hidden" name="opciones[{{ $i }}][id]" value="{{ $opcion['id'] }}">@endif
    <label class="text-sm font-semibold sm:col-span-2">Etiqueta @if(!empty($opcion['id'])) · ID {{ $opcion['id'] }} @endif
        <input name="opciones[{{ $i }}][etiqueta]" value="{{ $opcion['etiqueta'] ?? '' }}" maxlength="100" class="mt-1 w-full rounded-xl border-slate-300">
    </label>
    <label class="text-sm font-semibold">Orden<input type="number" name="opciones[{{ $i }}][orden]" value="{{ $opcion['orden'] ?? 0 }}" min="0" max="65000" class="mt-1 w-full rounded-xl border-slate-300"></label>
    <label class="flex items-center gap-2 py-2 text-sm"><input type="hidden" name="opciones[{{ $i }}][activo]" value="0"><input type="checkbox" name="opciones[{{ $i }}][activo]" value="1" @checked($opcion['activo'] ?? true)> Activa</label>
</div>
