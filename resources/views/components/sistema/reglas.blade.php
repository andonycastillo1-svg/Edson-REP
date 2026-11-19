@props(['catalogo', 'reglas', 'efectivos', 'heredados' => [], 'individual' => false, 'bloqueo' => null])
@php
    $grupos = collect($catalogo)->mapWithKeys(fn ($clave) => [
        $clave => \App\Sistema\CatalogoPermisos::presentacion($clave),
    ])->groupBy('grupo', preserveKeys: true);
    $anteriores = old('reglas', []);
@endphp

<section data-buscador-permisos class="space-y-4">
    @if($grupos->isEmpty())
        <p class="ui-empty-state">No hay permisos para el ámbito de este registro.</p>
    @else
        <p class="text-sm">
            {{ $individual
                ? 'Usa Heredar para aplicar el acceso del rol. Permitir o Denegar establece una excepción para esta cuenta.'
                : 'Usa Heredar para aplicar el acceso base del rol. Permitir o Denegar fija una regla para las cuentas de este rol.' }}
            El acceso efectivo refleja lo guardado y sigue sujeto al alcance de cada cuenta y bodega.
        </p>
        @if($bloqueo)
            <p class="ui-alert-warning" role="status">{{ $bloqueo }}. {{ $individual ? 'El acceso de la cuenta está suspendido.' : 'El acceso de las cuentas asociadas está suspendido.' }}</p>
        @endif
        <div class="flex flex-wrap items-end justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
            <div class="flex grow flex-wrap items-end gap-3">
                <label class="block w-full max-w-xs text-sm font-semibold">Buscar permisos
                    <input type="search" data-filtro-permisos class="mt-1 block w-full" placeholder="Nombre, módulo o categoría">
                </label>
                <label for="filtro-acceso" class="text-sm font-semibold">Acceso guardado
                    <select id="filtro-acceso" aria-label="Acceso guardado" data-filtro-estado class="mt-1 block w-full">
                        <option value="">Todos</option>
                        <option value="permitido">Permitidos</option>
                        <option value="denegado">Denegados</option>
                    </select>
                </label>
            </div>
            <button type="button" class="ui-btn-secondary" data-expandir-permisos hidden>Expandir módulos</button>
            <a href="#confirmar-permisos" class="ui-btn-secondary">Ir a guardar</a>
        </div>
        <p role="status" class="text-sm font-semibold text-slate-700" data-permisos-resumen></p>
        <p class="ui-empty-state" data-sin-permisos hidden>No hay permisos que coincidan con la búsqueda.</p>
        @foreach($grupos as $grupo => $permisos)
            @php
                $cantidadPermitida = $bloqueo ? 0 : $permisos->keys()->filter(fn ($clave) => $efectivos[$clave]['permitido'])->count();
            @endphp
            <details data-grupo-permisos class="rounded-xl border border-slate-200 bg-white" @if($loop->first) open @endif>
                <summary class="sistema-desplegable">
                    {{ $grupo }} <span class="ml-1 font-normal text-slate-600">· {{ $cantidadPermitida }} de {{ $permisos->count() }} permitidos</span>
                </summary>
                <div class="space-y-3 p-3">
                    @foreach($permisos->groupBy('categoria', preserveKeys: true) as $categoria => $tarjetas)
                        <section data-categoria-permisos>
                            <h3 class="mb-2 text-sm font-semibold">{{ $categoria }}</h3>
                            <div class="grid gap-2 lg:grid-cols-2">
                                @foreach($tarjetas as $clave => $permiso)
                                    @php
                                        $guardado = isset($reglas[$clave]) ? ($reglas[$clave] ? 'permitir' : 'denegar') : 'heredar';
                                        $enviado = is_array($anteriores) ? ($anteriores[$clave] ?? null) : null;
                                        $estado = in_array($enviado, ['heredar', 'permitir', 'denegar'], true) ? $enviado : $guardado;
                                        $efectivo = $bloqueo ? ['permitido' => false, 'origen' => $bloqueo] : $efectivos[$clave];
                                    @endphp
                                    <div data-regla-permiso data-regla-guardada="{{ $guardado }}"
                                         data-efectivo="{{ $efectivo['permitido'] ? 'permitido' : 'denegado' }}"
                                         data-busqueda="{{ $grupo }} {{ $categoria }} {{ $permiso['titulo'] }} {{ $permiso['descripcion'] }}"
                                         class="min-w-0 space-y-2 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                                        <div>
                                            <h4 class="text-sm font-bold" id="nombre-{{ $clave }}">{{ $permiso['titulo'] }}</h4>
                                            <p id="ayuda-{{ $clave }}" class="mt-0.5 text-xs">{{ $permiso['descripcion'] }}</p>
                                        </div>
                                        <fieldset aria-labelledby="nombre-{{ $clave }}" aria-describedby="ayuda-{{ $clave }}" class="permiso-opciones">
                                            <legend class="sr-only">Regla</legend>
                                            @foreach(['heredar' => 'Heredar', 'permitir' => 'Permitir', 'denegar' => 'Denegar'] as $valor => $etiqueta)
                                                <label class="permiso-opcion" data-valor="{{ $valor }}">
                                                    <input type="radio" name="reglas[{{ $clave }}]" value="{{ $valor }}" @checked($estado === $valor)>
                                                    <span>{{ $etiqueta }}</span>
                                                </label>
                                            @endforeach
                                        </fieldset>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                            @if($individual)
                                                <p>Del rol <strong>{{ $heredados[$clave]['permitido'] ? 'Permitido' : 'Denegado' }}</strong></p>
                                            @endif
                                            <p class="flex flex-wrap items-center gap-1">
                                                Acceso efectivo
                                                <span @class(['ui-badge', 'ui-badge-success' => $efectivo['permitido'], 'ui-badge-danger' => !$efectivo['permitido']])>{{ $efectivo['permitido'] ? 'Permitido' : 'Denegado' }}</span>
                                            </p>
                                            <p>{{ $efectivo['origen'] }}</p>
                                            <span class="ui-badge ui-badge-warning" data-regla-modificada @if($estado === $guardado) hidden @endif>Sin guardar</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </details>
        @endforeach
    @endif
</section>
