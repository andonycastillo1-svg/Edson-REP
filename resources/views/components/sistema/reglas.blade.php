@props(['catalogo', 'reglas', 'efectivos', 'individual' => false, 'rolInactivo' => false])
@php
    $grupos = collect($catalogo)->mapWithKeys(fn ($clave) => [
        $clave => \App\Sistema\CatalogoPermisos::presentacion($clave),
    ])->groupBy('grupo', preserveKeys: true);
    $anteriores = old('reglas', []);
@endphp

<section data-buscador-permisos class="space-y-3">
    <p class="text-sm">
        @if($individual)
            Configura el acceso general en el rol. Usa reglas individuales para excepciones. Estas prevalecen sobre el rol dentro del ámbito operativo de la cuenta.
        @else
            Los permisos del rol se aplican a sus cuentas dentro del ámbito indicado. Administra las excepciones desde la cuenta correspondiente.
        @endif
    </p>
    <p class="text-sm"><strong>Heredar</strong> aplica {{ $individual ? 'la regla del rol' : 'el acceso base del ámbito' }}. <strong>Permitir</strong> concede acceso y <strong>Denegar</strong> lo retira. El acceso efectivo refleja las reglas guardadas.</p>
    @if($grupos->isEmpty())
        <p class="ui-empty-state">No hay permisos para el ámbito operativo de este registro.</p>
    @else
        <label class="block max-w-md text-sm font-semibold">
            Buscar permisos
            <input type="search" data-filtro-permisos class="mt-1 block w-full" placeholder="Buscar por nombre o módulo">
        </label>
        <p role="status" class="text-sm" data-sin-permisos hidden>No hay permisos que coincidan con la búsqueda.</p>
        @foreach($grupos as $grupo => $permisos)
            <details data-grupo-permisos class="rounded-xl border border-slate-200 bg-white" @if($loop->first) open @endif>
                <summary class="min-h-11 cursor-pointer rounded-xl bg-slate-50 px-3 py-2.5 font-semibold text-slate-900">
                    {{ $grupo }} <span class="ml-1 text-sm font-normal text-slate-600">({{ $permisos->count() }})</span>
                </summary>
                @foreach($permisos as $clave => $presentacion)
                    @php
                        $estado = is_array($anteriores) && isset($anteriores[$clave]) ? $anteriores[$clave]
                            : (isset($reglas[$clave]) ? ($reglas[$clave] ? 'permitir' : 'denegar') : 'heredar');
                        $efectivo = $rolInactivo ? ['permitido' => false, 'origen' => 'Rol inactivo'] : $efectivos[$clave];
                    @endphp
                    <div data-regla-permiso data-busqueda="{{ $grupo }} {{ $presentacion['titulo'] }} {{ $presentacion['descripcion'] }}" class="regla-permiso">
                        <div>
                            <label for="permiso-{{ $clave }}" id="nombre-{{ $clave }}" class="text-sm font-semibold">{{ $presentacion['titulo'] }}</label>
                            <p class="mt-0.5 text-xs" id="ayuda-{{ $clave }}">{{ $presentacion['descripcion'] }}</p>
                        </div>
                        <select id="permiso-{{ $clave }}" name="reglas[{{ $clave }}]" aria-describedby="ayuda-{{ $clave }}" class="w-full text-sm">
                            @foreach(['heredar' => 'Heredar', 'permitir' => 'Permitir', 'denegar' => 'Denegar'] as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected($estado === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        <div class="text-xs">
                            <p>Acceso efectivo</p>
                            <span @class(['ui-badge mt-0.5', 'ui-badge-success' => $efectivo['permitido'], 'ui-badge-danger' => !$efectivo['permitido']])>{{ $efectivo['permitido'] ? 'Permitido' : 'Denegado' }}</span>
                            <p class="mt-0.5">{{ $efectivo['origen'] }}</p>
                        </div>
                    </div>
                @endforeach
            </details>
        @endforeach
    @endif
</section>
