<nav aria-label="Pasos del corte">
    <ol class="grid gap-2 rounded-xl border border-slate-200 bg-white p-2 text-sm sm:grid-cols-3">
        @foreach([1 => 'Elegir período', 2 => 'Seleccionar inspecciones', 3 => 'Revisar y confirmar'] as $numero => $etiqueta)
            <li @class(['flex items-center gap-2 rounded-lg px-3 py-2', 'bg-blue-50 font-bold text-blue-900' => $paso === $numero, 'text-slate-600' => $paso !== $numero])
                @if($paso === $numero) aria-current="step" @endif>
                <span class="ui-badge {{ $paso === $numero ? 'ui-badge-info' : '' }}">{{ $numero }}</span>
                {{ $etiqueta }}
            </li>
        @endforeach
    </ol>
</nav>
