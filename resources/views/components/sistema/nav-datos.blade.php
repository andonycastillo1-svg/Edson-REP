@props(['modulo' => null, 'configurar' => false])
<nav aria-label="Funciones de datos" class="sistema-pestanas">
    <a href="{{ $modulo && app(\App\Sistema\Modulos::class)->consultar(auth()->user(), $modulo) ? route('sistema.datos.lista', $modulo) : route('sistema.datos') }}"
       @unless($configurar) aria-current="page" @endunless>Consultar información</a>
    @if(app(\App\Sistema\Permisos::class)->permite(auth()->user(), 'sistema.presentacion'))
        <a href="{{ route('sistema.presentacion', array_filter(['modulo' => $modulo, 'origen' => 'datos'])) }}"
           @if($configurar) aria-current="page" @endif>Configurar presentación</a>
    @endif
</nav>
