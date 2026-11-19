<x-sistema.pagina titulo="Notificaciones" :volver="route('sistema.configuracion')" configuracion
                 descripcion="Elige un evento para definir si se envía un aviso y quién puede recibirlo.">
    <x-sistema.panel>
        <form method="GET" action="{{ route('sistema.notificaciones') }}" class="flex flex-wrap items-end gap-3" data-buscar-destinatarios>
            <label for="evento-notificacion" class="block w-full max-w-md text-sm font-semibold">Evento
                <select id="evento-notificacion" name="evento" class="mt-1 w-full" aria-label="Evento">
                    @foreach(\App\Sistema\Ajustes::EVENTOS as $clave => $titulo)<option value="{{ $clave }}" @selected($evento === $clave)>{{ $titulo }}</option>@endforeach
                </select>
            </label>
            <label for="buscar-destinatario" class="block w-full max-w-xs text-sm font-semibold">Buscar cuentas
                <input id="buscar-destinatario" type="search" name="q" value="{{ $q }}" maxlength="100" class="mt-1 w-full" placeholder="Nombre de la cuenta">
            </label>
            <button type="submit" class="ui-btn-secondary">Abrir evento o buscar cuentas</button>
        </form>
    </x-sistema.panel>
    <x-sistema.panel :titulo="\App\Sistema\Ajustes::EVENTOS[$evento]">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span @class(['ui-badge', 'ui-badge-success' => $envioActivo, 'ui-badge-warning' => !$envioActivo])>
                {{ $envioActivo ? 'Envío activo' : 'Envío desactivado' }}
            </span>
            <p class="text-xs">Estado guardado</p>
        </div>
        <form method="POST" action="{{ route('sistema.notificaciones.guardar', $evento) }}" class="space-y-4" data-destinatarios>
            @csrf @method('PUT')
            <input type="hidden" name="evento_formulario" value="{{ $evento }}">
            <x-sistema.casilla name="activo" id="notificacion-activa" :marcada="$regla['activo'] ?? true">Enviar aviso de este evento</x-sistema.casilla>
            <p class="text-sm">Marca los roles o cuentas que quieras incluir. Solo recibirán el aviso las personas que ya tienen acceso a la operación, según sus permisos y bodega. Si dejas ambas listas vacías, se usan los destinatarios habituales.</p>

            <div class="sistema-formulario">
                <fieldset class="min-w-0">
                    <legend class="mb-1 text-sm font-semibold">Roles destinatarios</legend>
                    <div class="sistema-opciones">
                        @foreach($roles as $rol)
                            <label class="ui-check">
                                <input type="checkbox" name="roles[]" value="{{ $rol->id }}" @checked(in_array($rol->id, $regla['roles'] ?? []))>
                                <span>{{ $rol->nombre }}@unless($rol->activo) (inactivo)@endunless</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset class="min-w-0">
                    <legend class="mb-1 text-sm font-semibold">Cuentas específicas</legend>
                    <div class="sistema-opciones">
                        @forelse($usuarios as $usuario)
                            <label class="ui-check">
                                <input type="checkbox" name="usuarios[]" value="{{ $usuario->id }}" @checked(in_array($usuario->id, $regla['usuarios'] ?? []))>
                                <span>{{ $usuario->name }}@unless($usuario->acceso_activo) (inactiva)@endunless</span>
                            </label>
                        @empty
                            <p class="p-2 text-sm">No hay coincidencias. Prueba con otro nombre.</p>
                        @endforelse
                    </div>
                    <p class="mt-1 text-xs">Se muestran hasta 50 coincidencias y las cuentas ya seleccionadas para este evento.</p>
                </fieldset>
            </div>
            @if($evento === 'stock_bajo' && app(\App\Sistema\Permisos::class)->permite(auth()->user(), 'sistema.parametros'))
                <a class="text-sm font-semibold text-blue-700 underline" href="{{ route('sistema.parametros') }}">Cambiar el nivel mínimo de inventario</a>
            @endif
            <div><button type="submit" class="ui-btn-primary">Guardar regla</button></div>
        </form>
    </x-sistema.panel>
    <x-sistema.panel titulo="Avisos obligatorios">
        <p class="text-sm">Los avisos obligatorios de seguridad, supervisión y revisión de Recursos Humanos siguen activos. La bitácora registra los cambios aunque desactives un aviso operativo.</p>
    </x-sistema.panel>
</x-sistema.pagina>
