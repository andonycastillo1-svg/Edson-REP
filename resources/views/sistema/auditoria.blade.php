<x-sistema.pagina titulo="Bitácora" :volver="route('sistema.configuracion')" configuracion
                 descripcion="Revisa los cambios registrados, la fecha y la cuenta responsable.">
    <x-sistema.panel titulo="Filtros de actividad">
        <form method="GET" action="{{ route('sistema.auditoria') }}" class="space-y-3">
            <label class="block max-w-md text-sm font-semibold">Buscar actividad
                <input type="search" name="q" value="{{ $datos['q'] ?? '' }}" maxlength="100" class="mt-1 w-full" placeholder="Por usuario, módulo o acción">
            </label>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <label for="bitacora-usuario" class="min-w-0 text-sm font-semibold">Usuario
                    <select id="bitacora-usuario" name="actor" class="mt-1 w-full" aria-label="Usuario">
                        <option value="">Todas las cuentas</option>
                        @foreach($usuarios as $usuario)
                            <option value="{{ $usuario->actor_id ?? 0 }}" @selected((string) ($datos['actor'] ?? '') === (string) ($usuario->actor_id ?? 0))>
                                {{ \App\Sistema\Bitacora::persona($usuario) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label for="bitacora-modulo" class="min-w-0 text-sm font-semibold">Módulo
                    <select id="bitacora-modulo" name="modulo" class="mt-1 w-full" aria-label="Módulo">
                        <option value="">Todos los módulos</option>
                        @foreach($modulos as $modulo)
                            <option value="{{ $modulo }}" @selected(($datos['modulo'] ?? null) === $modulo)>{{ \App\Sistema\Bitacora::modulo($modulo) }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="bitacora-accion" class="min-w-0 text-sm font-semibold">Acción
                    <select id="bitacora-accion" name="accion" class="mt-1 w-full" aria-label="Acción">
                        <option value="">Todas las acciones</option>
                        @foreach($acciones as $accion)
                            <option value="{{ $accion }}" @selected(($datos['accion'] ?? null) === $accion)>{{ \App\Sistema\Bitacora::accion($accion) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="min-w-0 text-sm font-semibold">Desde
                    <input name="desde" type="date" value="{{ $datos['desde'] ?? '' }}" class="mt-1 w-full">
                </label>
                <label class="min-w-0 text-sm font-semibold">Hasta
                    <input name="hasta" type="date" value="{{ $datos['hasta'] ?? '' }}" class="mt-1 w-full">
                </label>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="ui-btn-primary">Aplicar filtros</button>
                @if($datos)<a href="{{ route('sistema.auditoria') }}" class="ui-btn-secondary">Limpiar filtros</a>@endif
            </div>
        </form>
    </x-sistema.panel>

    <x-sistema.panel titulo="Actividad registrada">
        <p class="mb-3 text-sm">{{ $eventos->total() }} {{ $eventos->total() === 1 ? 'evento' : 'eventos' }}</p>
        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Actividad registrada">
            <table class="ui-table">
                <thead><tr><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col">Actividad</th><th scope="col">Registro</th><th scope="col">Resultado</th></tr></thead>
                <tbody>
                    @forelse($eventos as $evento)
                        @php($cambios = \App\Sistema\Bitacora::detalles($evento->antes, $evento->despues, $evento->accion))
                        <tr>
                            <td class="whitespace-nowrap"><time datetime="{{ \Illuminate\Support\Carbon::parse($evento->created_at)->toDateTimeLocalString() }}">{{ \Illuminate\Support\Carbon::parse($evento->created_at)->format('d/m/Y H:i') }}</time></td>
                            <td>{{ \App\Sistema\Bitacora::persona($actores->get($evento->actor_id, $evento)) }}</td>
                            <td>
                                <p class="font-semibold">{{ \App\Sistema\Bitacora::accion($evento->accion) }}</p>
                                <p class="text-xs">{{ \App\Sistema\Bitacora::modulo($evento->modulo) }}</p>
                            </td>
                            <td>{{ \App\Sistema\Bitacora::referencia($evento->registro) }}</td>
                            <td>
                                <span @class(['ui-badge', 'ui-badge-success' => $evento->resultado === 'aplicado', 'ui-badge-danger' => $evento->resultado === 'denegado'])>
                                    {{ match ($evento->resultado) { 'aplicado' => 'Aplicado', 'denegado' => 'Denegado', default => 'Registrado' } }}
                                </span>
                                @if($cambios)
                                    <details class="mt-2">
                                        <summary class="cursor-pointer font-semibold text-blue-800">Ver cambios</summary>
                                        <dl class="mt-1 space-y-1">
                                            @foreach($cambios as [$etiqueta, $antes, $despues])
                                                <div class="rounded bg-slate-50 p-2 text-xs">
                                                    <dt class="font-bold">{{ $etiqueta }}</dt>
                                                    <dd>Antes: {{ $antes }} <br> Después: {{ $despues }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><p class="ui-empty-state">No hay eventos para estos filtros.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $eventos->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>
