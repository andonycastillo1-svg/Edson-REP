@props(['tabla', 'registro'])

@if($valores = $tabla['valores']->get((string) $registro))
    <dl class="my-3 grid gap-2 text-sm sm:grid-cols-2">
        @foreach($tabla['campos'] as $campo)
            @if($valores->has($campo->id))
                <div>
                    <dt class="font-semibold">{{ $campo->etiqueta }}</dt>
                    <dd>{{ app(\App\Sistema\ValoresCampos::class)->mostrar($campo, $valores->get($campo->id)) }}</dd>
                </div>
            @endif
        @endforeach
    </dl>
@endif
