@php
    $avisos = [
        'ok' => 'success',
        'success' => 'success',
        'status' => 'success',
        'info' => 'info',
        'warning' => 'warning',
        'error' => 'error',
    ];
@endphp

@foreach($avisos as $clave => $tipo)
    @if(is_string(session($clave)) && session($clave) !== '')
        <p class="ui-alert ui-alert-{{ $tipo }}" role="{{ $tipo === 'error' ? 'alert' : 'status' }}">{{ session($clave) }}</p>
    @endif
@endforeach

@if($errors->any())
    @php($formularioError = request()->routeIs('sistema.usuario') ? old('formulario_cuenta') : null)
    <section role="alert" tabindex="-1" aria-labelledby="titulo-errores" class="ui-alert-error" data-resumen-validacion
             @if(is_string($formularioError)) data-validacion-origen="{{ $formularioError }}" @endif>
        <h2 id="titulo-errores" class="text-base font-bold text-rose-900">Revisa los datos indicados</h2>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            @foreach($errors->getMessages() as $campo => $mensajes)
                @foreach(array_unique($mensajes) as $mensaje)
                    <li data-campo-error="{{ $campo }}">{{ $mensaje }}</li>
                @endforeach
            @endforeach
        </ul>
    </section>
@endif
