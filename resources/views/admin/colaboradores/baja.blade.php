@extends('layouts.app')

@section('title', 'Baja laboral')
@section('content')
<div class="mx-auto max-w-3xl space-y-4">
    <x-ui.encabezado titulo="Baja laboral" :descripcion="$colaborador->nombre.' · '.$colaborador->codigo">
        <x-ui.accion :href="route($ruta.'.index')">Volver a colaboradores</x-ui.accion>
    </x-ui.encabezado>
    @if($baja = $colaborador->bajaVigente)
        <x-sistema.panel titulo="Baja registrada">
            <dl class="flujo-dato">
                <div><dt>Motivo</dt><dd>{{ \App\Models\BajaLaboral::MOTIVOS[$baja->motivo] }}</dd></div>
                <div><dt>Fecha efectiva</dt><dd>{{ $baja->fecha_efectiva->format('d/m/Y') }}</dd></div>
                <div><dt>Registró</dt><dd>{{ $baja->registrador?->name ?? '—' }}</dd></div>
            </dl>
            @if($baja->observaciones)<p class="mt-3 whitespace-pre-wrap">{{ $baja->observaciones }}</p>@endif
            <p class="mt-3 text-sm">El responsable de bodega verifica los artículos en Asignaciones y devoluciones.</p>
        </x-sistema.panel>
    @else
        <x-sistema.panel titulo="Registrar renuncia o despido">
            <form class="space-y-4" method="POST" action="{{ route($ruta.'.baja.store', $colaborador) }}"
                  data-confirmar="¿Registrar la baja laboral de este colaborador?">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="ui-label">Motivo
                        <select name="motivo" class="ui-select mt-1" required>
                            <option value="">Selecciona un motivo</option>
                            @foreach(\App\Models\BajaLaboral::MOTIVOS as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(old('motivo') === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ui-label">Fecha efectiva
                        <input class="ui-input mt-1" type="date" name="fecha_efectiva" max="{{ today()->toDateString() }}"
                               value="{{ old('fecha_efectiva', today()->toDateString()) }}" required>
                    </label>
                </div>
                <label class="ui-label">Observaciones (opcional)
                    <textarea class="ui-input mt-1" name="observaciones" rows="3" maxlength="1000">{{ old('observaciones') }}</textarea>
                </label>
                <p class="text-sm">La baja habilita la revisión de la custodia. La bodega registra después el resultado de cada artículo.</p>
                <x-ui.accion variante="primaria" type="submit">Registrar baja laboral</x-ui.accion>
            </form>
        </x-sistema.panel>
    @endif
</div>
@endsection
