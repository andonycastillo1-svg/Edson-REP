@props(['modulo', 'registro' => null])
@if(auth()->check() && app(\App\Sistema\Permisos::class)->instalado() && app(\App\Sistema\Modulos::class)->consultar(auth()->user(), $modulo))
    <div class="inline-flex flex-wrap gap-2">
        <a href="{{ $registro === null ? route('sistema.datos.lista', $modulo) : route('sistema.datos.ficha', [$modulo, $registro]) }}"
           class="ui-btn-secondary ui-btn-sm">{{ $registro === null ? 'Consultar datos' : 'Ver campos' }}</a>
        @if(app(\App\Sistema\Modulos::class)->puedeEditarCampos(auth()->user(), $modulo))
            <a href="{{ route('sistema.operacion.campos', $modulo) }}" class="ui-btn-secondary ui-btn-sm">Editar campos operativos</a>
        @endif
    </div>
@endif
