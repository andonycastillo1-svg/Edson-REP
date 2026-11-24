@php
    $esRegional = in_array($ambitoSeleccionado, [2, 3, 5], true);
    $seleccion = old('configurar_regiones') ? old('regiones', []) : ($usuario?->rolOperativo() === 5
        ? $usuario->regionesCoordinadas->modelKeys() : ($usuario?->regionesOperativas->modelKeys() ?? []));
@endphp
<fieldset id="regiones-operativas" class="rounded-xl border border-slate-200 p-4 space-y-3" @disabled(!$esRegional) @if(!$esRegional) hidden @endif>
    <legend class="px-1 font-semibold">Regiones operativas</legend>
    <input type="hidden" name="configurar_regiones" value="1">
    <p class="text-sm">Selecciona las regiones en las que trabajará esta cuenta.</p>
    <div class="grid gap-2 sm:grid-cols-2">
        @forelse($regionesOperativas as $region)
            <label class="flujo-eleccion">
                <input name="regiones[]" type="checkbox" value="{{ $region->id }}"
                    @checked(in_array($region->id, $seleccion)) @disabled(!$region->activa)>
                <span><strong>{{ $region->nombre }}</strong>
                    @if(!$region->activa)<small class="block">Inactiva</small>@endif
                    @if($region->coordinador && !$region->coordinador->is($usuario))
                        <small class="block" data-coordinacion @if($ambitoSeleccionado !== 5) hidden @endif>Coordina {{ $region->coordinador->name }}</small>
                    @endif
                </span>
            </label>
        @empty
            <p class="text-sm">Aún no hay regiones configuradas.</p>
        @endforelse
    </div>
    <label class="ui-check" data-coordinacion @if($ambitoSeleccionado !== 5) hidden @endif>
        <input type="checkbox" name="reasignar_regiones" value="1" @checked(old('reasignar_regiones')) @disabled($ambitoSeleccionado !== 5)>
        Confirmar el cambio si una región ya tiene coordinador
    </label>
    @error('regiones')<p class="ui-alert-error" role="alert">{{ $message }}</p>@enderror
    @error('regiones.*')<p class="ui-alert-error" role="alert">{{ $message }}</p>@enderror
</fieldset>
<script>
    (() => {
        const rol = document.getElementById('role_id');
        const regiones = document.getElementById('regiones-operativas');
        const confirmar = regiones.querySelector('[name=reasignar_regiones]');
        const actualizar = () => {
            const ambito = rol.selectedOptions[0]?.dataset.ambito;
            regiones.hidden = regiones.disabled = !['2', '3', '5'].includes(ambito);
            regiones.querySelectorAll('[data-coordinacion]').forEach(elemento => elemento.hidden = ambito !== '5');
            confirmar.disabled = ambito !== '5';
        };
        rol.addEventListener('change', actualizar);
        actualizar();
    })();
</script>
