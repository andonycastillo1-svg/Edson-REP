<x-sistema.pagina :titulo="$corte->nombre" :volver="$esEditable ? route('flujo.cortes.seleccion', $corte) : route('flujo.cortes.index')">
    @include('flujo.cortes.pasos', ['paso' => 3])
    <dl class="flujo-cifras">
        @foreach(['total' => 'Inspecciones seleccionadas', 'colaboradores' => 'Colaboradores', 'regiones' => 'Regiones', 'coordinadores' => 'Coordinadores', 'anteriores' => 'Inspecciones anteriores incluidas'] as $clave => $etiqueta)
            <div><dt>{{ $etiqueta }}</dt><dd>{{ $resumen[$clave] }}</dd></div>
        @endforeach
    </dl>
    @if($resumen['alertas'])
        <div class="ui-alert-warning" role="alert">
            <p class="font-semibold">Revisa la selección antes de generar los consolidados</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach($resumen['alertas'] as $mensaje)<li>{{ $mensaje }}</li>@endforeach
            </ul>
        </div>
    @endif
    @if($esEditable && $resumen['opciones']->isNotEmpty())
        <x-sistema.panel titulo="Elegir responsables">
            <form method="POST" action="{{ route('flujo.cortes.coordinacion', $corte) }}" class="space-y-3">
                @csrf @method('PUT')
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($resumen['opciones'] as $region => $candidatos)
                        <label class="ui-label">{{ $resumen['regiones_nombres'][$region] }}
                            <select name="responsables[{{ $region }}]" class="ui-input mt-1" required>
                                <option value="">Selecciona un coordinador</option>
                                @foreach($candidatos as $candidato)
                                    <option value="{{ $candidato->id }}" @selected((int) old("responsables.$region", $corte->coordinadores_elegidos[$region] ?? 0) === $candidato->id)>{{ $candidato->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
                <button class="ui-btn-primary">Actualizar vista previa</button>
            </form>
        </x-sistema.panel>
    @endif
    <x-sistema.panel titulo="Consolidados previstos">
        <p class="mb-3 text-sm text-slate-600">{{ $corte->desde->format('d/m/Y') }} – {{ $corte->hasta->format('d/m/Y') }}. Cada grupo se enviará a su coordinador al confirmar.</p>
        <div class="space-y-2">
            @forelse($resumen['grupos'] as $grupo)
                <details class="flujo-lectura">
                    <summary>
                        {{ $grupo['coordinador']->name }}
                        <span class="block font-normal text-slate-600 sm:inline">
                            · {{ $grupo['inspecciones']->count() }} inspecciones · {{ $grupo['colaboradores'] }} colaboradores
                        </span>
                    </summary>
                    <div class="p-3">
                        <p class="pb-2 text-sm font-semibold">{{ $grupo['regiones']->implode(', ') }}</p>
                        <ul class="divide-y divide-slate-200">
                            @foreach($grupo['inspecciones'] as $fila)
                                <li class="py-2 text-sm">
                                    <a class="flujo-enlace" href="{{ route('flujo.inspecciones.show', $fila) }}">#{{ $fila->id }} · {{ $fila->colaborador?->nombre }}</a>
                                    <span>{{ $fila->fecha->format('d/m/Y') }} · {{ $fila->region?->nombre }} · {{ $fila->proceso_nombre ?? 'Histórica sin proceso' }}</span>
                                    @if($fila->fecha->lt($corte->desde))<span class="ui-badge ui-badge-info">Anterior al período</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </details>
            @empty
                <p class="text-sm">{{ $resumen['total'] ? 'La selección todavía no produce un consolidado válido.' : 'Selecciona al menos una inspección para continuar.' }}</p>
            @endforelse
        </div>
    </x-sistema.panel>
    @if($esEditable)
        <div class="flex flex-wrap gap-3">
            @if($resumen['total'] && !$resumen['alertas'] && app(\App\Services\FlujoAcceso::class)->puede(auth()->user(), 'crear_consolidados') && app(\App\Services\FlujoAcceso::class)->puede(auth()->user(), 'enviar_consolidados'))
                <form method="POST" action="{{ route('flujo.cortes.confirmar', $corte) }}" data-confirmar="¿Generar y enviar los consolidados a coordinación?">
                    @csrf
                    <input type="hidden" name="firma" value="{{ $resumen['firma'] }}">
                    <button class="ui-btn-primary">Confirmar y generar consolidados</button>
                </form>
            @endif
            <a class="ui-btn-secondary" href="{{ route('flujo.cortes.seleccion', $corte) }}">Editar selección</a>
            <button class="ui-btn-secondary" type="submit" form="cancelar-corte">Cancelar borrador</button>
        </div>
        @include('flujo.cortes.cancelar')
    @endif
</x-sistema.pagina>
