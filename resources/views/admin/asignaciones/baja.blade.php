@extends('layouts.app')

@section('title', 'Devolución por baja laboral')
@section('content')
<div class="space-y-4">
    <x-ui.encabezado titulo="Devolución por baja laboral" :descripcion="$colaborador->nombre.' · '.$colaborador->codigo">
        @rutaDisponible($routePrefix.'.asignaciones.colaborador')
            <x-ui.accion :href="route($routePrefix.'.asignaciones.colaborador', $colaborador->codigo)">Volver a productos</x-ui.accion>
        @endrutaDisponible
        @if(session('grupo_devolucion'))
            @rutaDisponible($routePrefix.'.asignaciones.hoja_devolucion')
                <x-ui.accion :href="route($routePrefix.'.asignaciones.hoja_devolucion', session('grupo_devolucion'))" target="_blank" rel="noopener">Hoja de devolución</x-ui.accion>
            @endrutaDisponible
        @endif
    </x-ui.encabezado>

    <x-sistema.panel titulo="Verificación en bodega">
        <p class="mb-4 text-sm">{{ \App\Models\BajaLaboral::MOTIVOS[$baja->motivo] }} del {{ $baja->fecha_efectiva->format('d/m/Y') }} · {{ trans_choice('{1} :count artículo pendiente|[0,*] :count artículos pendientes', $asignaciones->total()) }} en tu ámbito.</p>
        @if($asignaciones->isNotEmpty())
            <p class="mb-3 text-sm">Selecciona solo los artículos que verificaste. Para un artículo no recuperado, registra una pérdida, un robo u otra incidencia con cierre sin retorno físico.</p>
            <form method="POST" action="{{ route($routePrefix.'.asignaciones.devolver_lote') }}" class="space-y-3"
                  data-confirmar="¿Registrar el resultado de los artículos seleccionados?">
                @csrf
                <input type="hidden" name="solicitud_id" value="{{ old('solicitud_id', (string) \Illuminate\Support\Str::uuid()) }}">
                <input type="hidden" name="baja_laboral_id" value="{{ $baja->id }}">
                @foreach($asignaciones as $fila)
                    @php($elegida = in_array((string) $fila->id, array_map('strval', (array) old('seleccionadas', [])), true))
                    <div class="ui-card p-3" data-verificacion>
                        <label class="flex min-h-11 items-center gap-3">
                            <input type="checkbox" name="seleccionadas[]" value="{{ $fila->id }}" @checked($elegida)>
                            <span><strong>{{ $fila->producto?->nombre ?? $fila->producto_codigo }}</strong>
                            <span class="block text-sm">{{ $fila->producto_codigo }} · {{ $fila->bodega?->nombre ?? '—' }} · {{ $fila->cantidad_asignada }} bajo custodia</span></span>
                        </label>
                        <fieldset class="mt-2 space-y-3 border-t border-line pt-3" @disabled(!$elegida) @if(!$elegida) hidden @endif>
                            <label class="ui-label max-w-xs">Unidades verificadas
                                <input type="number" name="devoluciones[{{ $fila->id }}]" class="ui-input mt-1" min="1" max="{{ $fila->cantidad_asignada }}"
                                       value="{{ old('devoluciones.'.$fila->id, 1) }}" required>
                            </label>
                            @include('admin.asignaciones.condicion', ['prefijo' => 'resoluciones['.$fila->id.']', 'claveAnterior' => 'resoluciones.'.$fila->id])
                        </fieldset>
                    </div>
                @endforeach
                <x-ui.accion variante="primaria" type="submit">Registrar verificación</x-ui.accion>
            </form>
            {{ $asignaciones->links() }}
        @else
            <p class="ui-empty-state">No quedan artículos pendientes de verificación en tu ámbito.</p>
        @endif
    </x-sistema.panel>
</div>
@include('admin.asignaciones.condicion-script')
<script>
document.querySelectorAll('[data-verificacion]').forEach((fila) => {
    const casilla = fila.querySelector('input[type="checkbox"]');
    const campos = fila.querySelector('fieldset');
    casilla.addEventListener('change', () => {
        campos.hidden = !casilla.checked;
        campos.disabled = !casilla.checked;
    });
});
</script>
@endsection
