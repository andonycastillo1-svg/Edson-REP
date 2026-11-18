@props(['modulo', 'registro' => null])
@if(auth()->check() && app(\App\Sistema\Permisos::class)->instalado() && app(\App\Sistema\Modulos::class)->consultar(auth()->user(), $modulo))
    <a href="{{ $registro === null ? route('sistema.datos.lista', $modulo) : route('sistema.datos.ficha', [$modulo, $registro]) }}"
       class="inline-flex rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-blue-700 shadow-sm">
        {{ $registro === null ? 'Vista configurable' : 'Campos personalizados' }}
    </a>
@endif
