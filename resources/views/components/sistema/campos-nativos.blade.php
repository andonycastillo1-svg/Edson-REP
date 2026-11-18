@props(['modulo', 'registro' => null])
@php($campos = app(\App\Sistema\Presentacion::class)->nativos($modulo))
<div class="grid gap-3 sm:grid-cols-2">
    @foreach($campos as $clave => $campo)
        @if($campo['visible'])
            <label class="block text-sm font-semibold text-slate-700">
                {{ $campo['etiqueta'] }}
                @if($campo['max'] > 500)
                    <textarea name="{{ $clave }}" maxlength="{{ $campo['max'] }}" rows="2" class="mt-1 w-full rounded-xl border-slate-300 bg-white" @required($campo['obligatorio'] && $registro === null)>{{ old($clave, $registro?->{$clave}) }}</textarea>
                @else
                    <input type="text" name="{{ $clave }}" maxlength="{{ $campo['max'] }}" value="{{ old($clave, $registro?->{$clave}) }}" class="mt-1 w-full rounded-xl border-slate-300 bg-white" @required($campo['obligatorio'] && $registro === null)>
                @endif
                @if($campo['ayuda'])<span class="mt-1 block text-xs font-normal text-slate-600">{{ $campo['ayuda'] }}</span>@endif
            </label>
        @endif
    @endforeach
</div>
