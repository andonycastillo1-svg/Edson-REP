@props(['modulos', 'seleccionado' => null, 'accion', 'texto' => 'Abrir módulo', 'origen' => null])
<form method="GET" action="{{ $accion }}" class="flex flex-wrap items-end gap-2 sm:flex-nowrap">
    @if($origen)<input type="hidden" name="origen" value="{{ $origen }}">@endif
    <label for="selector-modulo" class="block w-full max-w-xs text-sm font-semibold sm:w-64">Módulo
        <select id="selector-modulo" name="modulo" aria-label="Módulo" class="mt-1 block w-full" required>
            <option value="">Selecciona un módulo</option>
            @foreach($modulos as $clave => $modulo)<option value="{{ $clave }}" @selected($seleccionado === $clave)>{{ $modulo[0] }}</option>@endforeach
        </select>
    </label>
    <button type="submit" class="ui-btn-secondary">{{ $texto }}</button>
</form>
