<x-sistema.panel titulo="Delegaciones temporales" id="delegaciones" data-panel-pestana>
    <p class="mb-3 text-sm">Las delegaciones tienen vigencia por hora y operan con la identidad de {{ $usuario->name }}.</p>
    <details class="flujo-lectura" @if(old('formulario_cuenta') === 'delegar') open @endif>
        <summary>Crear delegación</summary>
        <form method="POST" action="{{ route('sistema.usuario.delegaciones.store', $usuario) }}" class="space-y-4 p-3" data-confirmar="¿Autorizar esta delegación temporal?">
            @csrf
            <input type="hidden" name="panel_cuenta" value="#delegaciones">
            <input type="hidden" name="formulario_cuenta" value="delegar">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="ui-label sm:col-span-2">Usuario titular
                    <select name="titular_id" class="ui-input mt-1" required>
                        <option value="">Selecciona la persona que será sustituida</option>
                        @foreach($titulares as $titular)
                            <option value="{{ $titular->id }}" @selected((int) old('titular_id') === $titular->id)>{{ $titular->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ui-label">Inicio<input type="datetime-local" name="vigente_desde" class="ui-input mt-1" value="{{ old('vigente_desde') }}" required></label>
                <label class="ui-label">Fin<input type="datetime-local" name="vigente_hasta" class="ui-input mt-1" value="{{ old('vigente_hasta') }}" required></label>
                <p class="text-sm sm:col-span-2">Zona horaria {{ config('app.timezone') }}.</p>
                <label class="ui-label sm:col-span-2">Motivo<textarea name="motivo" class="ui-input mt-1" maxlength="500" rows="2" required>{{ old('motivo') }}</textarea></label>
            </div>
            <fieldset>
                <legend class="font-semibold">Funciones temporales</legend>
                <p class="mb-2 text-sm">Selecciona también la consulta del módulo para delegar registros, revisiones o exportaciones.</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach([...\App\Services\DelegacionesOperativas::REGIONALES, ...\App\Services\DelegacionesOperativas::BODEGAS] as $permiso)
                        <label class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm">
                            <input type="checkbox" name="permisos[]" value="{{ $permiso }}" @checked(in_array($permiso, old('permisos', [])))>
                            {{ \App\Sistema\CatalogoPermisos::presentacion($permiso)['titulo'] }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @foreach(['regiones' => ['Regiones delegadas', $regiones], 'bodegas' => ['Bodega delegada', $bodegas]] as $campo => [$titulo, $opciones])
                <fieldset>
                    <legend class="font-semibold">{{ $titulo }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach($opciones as $opcion)
                            <label class="flex min-h-11 items-center gap-2 text-sm">
                                <input type="checkbox" name="{{ $campo }}[]" value="{{ $opcion->id }}" @checked(in_array($opcion->id, old($campo, [])))>{{ $opcion->nombre }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            <x-sistema.confirmacion />
            <button class="ui-btn-primary">Autorizar delegación</button>
        </form>
    </details>
    <div class="mt-4 space-y-2">
        @forelse($delegaciones as $fila)
            @php($estado = $fila->revocada_en ? 'Revocada' : ($fila->vigente_hasta->isPast() ? 'Vencida' : ($fila->vigente_desde->isFuture() ? 'Programada' : 'Activa')))
            <details class="flujo-lectura">
                <summary>{{ $fila->titular->name }} · <span class="ui-badge ui-badge-info">{{ $estado }}</span></summary>
                <div class="space-y-2 p-3 text-sm">
                    <p>{{ $fila->vigente_desde->format('d/m/Y H:i') }} – {{ $fila->vigente_hasta->format('d/m/Y H:i') }}</p>
                    <p>{{ $fila->motivo }} · Autorizó {{ $fila->autorizador->name }}</p>
                    <p>{{ $fila->regiones->concat($fila->bodegas)->pluck('nombre')->join(', ') }}</p>
                    <ul class="list-disc pl-5">
                        @foreach($fila->permisos as $permiso)<li>{{ \App\Sistema\CatalogoPermisos::presentacion($permiso)['titulo'] }}</li>@endforeach
                    </ul>
                    @if(in_array($estado, ['Activa', 'Programada'], true))
                        <form method="POST" action="{{ route('sistema.usuario.delegaciones.revocar', [$usuario, $fila]) }}" class="space-y-2" data-confirmar="¿Revocar esta delegación?">
                            @csrf @method('PATCH')
                            <input type="hidden" name="panel_cuenta" value="#delegaciones">
                            <x-sistema.confirmacion />
                            <button class="ui-btn-danger">Revocar delegación</button>
                        </form>
                    @endif
                </div>
            </details>
        @empty
            <p class="text-sm">La cuenta aún no tiene delegaciones registradas.</p>
        @endforelse
    </div>
    {{ $delegaciones->fragment('delegaciones')->links() }}
</x-sistema.panel>
