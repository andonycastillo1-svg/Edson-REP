@extends('layouts.app')

@section('title', 'Registrar devolución')
@section('content')
<div class="space-y-4">
    <x-ui.encabezado titulo="Registrar devolución" :descripcion="$colaborador->nombre.' · '.$colaborador->codigo">
        <x-ui.accion :href="$volver">Volver al expediente</x-ui.accion>
    </x-ui.encabezado>

    <div class="ui-panel px-4 py-3">
        <dl class="grid grid-cols-2 gap-3 text-sm lg:grid-cols-4">
            <div><dt class="text-muted">Colaborador</dt><dd class="font-semibold">{{ $colaborador->nombre }} · {{ $colaborador->codigo }}</dd></div>
            <div><dt class="text-muted">Región</dt><dd class="font-semibold">{{ $colaborador->region?->nombre ?? 'Sin región registrada' }}</dd></div>
            <div><dt class="text-muted">Fecha de devolución</dt><dd class="font-semibold">{{ now()->format('d/m/Y') }}</dd></div>
            <div><dt class="text-muted">Registra</dt><dd class="font-semibold">{{ auth()->user()->name }}</dd></div>
        </dl>
    </div>

    <x-sistema.panel titulo="Artículos bajo custodia">
        <p class="mb-4 text-sm">Clasifica las {{ $articulos->sum('cantidad_asignada') }} unidades pendientes. Usa cero en las condiciones que no correspondan.</p>
        <form id="devolucion-completa" action="{{ route('devoluciones.registrar', $colaborador->codigo) }}" method="POST"
              data-confirmar="¿Confirmar la devolución de toda la custodia? Se generará una sola hoja de devolución.">
            @csrf
            <input type="hidden" name="solicitud_id" value="{{ old('solicitud_id', (string) \Illuminate\Support\Str::uuid()) }}">
            <input type="hidden" name="articulos_json" disabled>
            <ul class="divide-y divide-line">
                @foreach($articulos as $fila)
                    <li class="py-4 first:pt-0">
                        <fieldset class="grid items-start gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]"
                                  data-articulo="{{ $fila->id }}" data-pendientes="{{ $fila->cantidad_asignada }}"
                                  aria-describedby="detalle-{{ $fila->id }} @if($errors->has('articulos.'.$fila->id) || $errors->has('articulos.'.$fila->id.'.*')) error-{{ $fila->id }} @endif">
                            <legend class="font-semibold text-ink">{{ $fila->producto?->nombre ?? $fila->producto_codigo }}</legend>
                            <div class="text-sm" id="detalle-{{ $fila->id }}">
                                <p>{{ $fila->producto_codigo }} · {{ $fila->bodega?->nombre ?? '—' }}</p>
                                <p>Asignación #{{ $fila->id }} · {{ ($fila->entrega?->salida_en ?? $fila->fecha)?->format('d/m/Y') ?? 'Fecha sin registrar' }}</p>
                                <p class="font-semibold">{{ $fila->cantidad_asignada }} bajo custodia</p>
                                <p role="status" data-resultado></p>
                            </div>
                            <div class="grid grid-cols-3 items-end gap-2">
                                @foreach(\App\Services\DevolverCustodiaCompleta::CONDICIONES as $condicion => $nombre)
                                    <label class="ui-label mb-0">{{ $nombre }}
                                        <input class="ui-input mt-1" name="articulos[{{ $fila->id }}][{{ $condicion }}]"
                                               type="number" min="0" max="{{ $fila->cantidad_asignada }}" required step="1"
                                               data-condicion="{{ $condicion }}" value="{{ old('articulos.'.$fila->id.'.'.$condicion, 0) }}">
                                    </label>
                                @endforeach
                            </div>
                            @if($errors->has('articulos.'.$fila->id) || $errors->has('articulos.'.$fila->id.'.*'))
                                <p class="text-sm text-red-800 lg:col-span-2" id="error-{{ $fila->id }}">
                                    {{ $errors->first('articulos.'.$fila->id) ?: $errors->first('articulos.'.$fila->id.'.*') }}
                                </p>
                            @endif
                        </fieldset>
                    </li>
                @endforeach
            </ul>
            @error('articulos')<p class="ui-alert-error">{{ $message }}</p>@enderror
            <div class="mt-3 flex justify-end">
                <x-ui.accion variante="primaria" type="submit">Confirmar devolución completa</x-ui.accion>
            </div>
        </form>
    </x-sistema.panel>
</div>
<script>
(() => {
    const formulario = document.getElementById('devolucion-completa');
    const grupos = [...formulario.querySelectorAll('[data-articulo]')];
    for (const grupo of grupos) {
        const campos = [...grupo.querySelectorAll('[data-condicion]')];
        const mensaje = grupo.querySelector('[data-resultado]');
        const actualizar = () => {
            const restantes = Number(grupo.dataset.pendientes) - campos.reduce((suma, campo) => suma + (campo.valueAsNumber || 0), 0);
            const texto = restantes > 0 ? `Faltan ${restantes} por clasificar.` : restantes < 0 ? `Hay ${-restantes} unidades de más.` : '';
            campos[0].setCustomValidity(texto);
            mensaje.textContent = texto;
        };
        grupo.addEventListener('input', actualizar);
        actualizar();
    }
    formulario.addEventListener('submit', () => {
        const cantidades = {};
        for (const grupo of grupos) {
            cantidades[grupo.dataset.articulo] = Object.fromEntries(
                [...grupo.querySelectorAll('[data-condicion]')].map(campo => {
                    campo.removeAttribute('name');
                    return [campo.dataset.condicion, campo.valueAsNumber];
                })
            );
        }
        const datos = formulario.elements.articulos_json;
        datos.value = JSON.stringify(cantidades);
        datos.disabled = false;
    });
})();
</script>
@endsection
