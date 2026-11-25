<x-sistema.pagina :titulo="$consolidado->nombre" :volver="route('flujo.consolidados.index')">
    <x-slot:acciones>
        @if($seguimiento && $consolidado->detalle_individual && app(\App\Services\FlujoAcceso::class)->puede(auth()->user(), 'importar')
            && app(\App\Sistema\Permisos::class)->permite(auth()->user(), 'asignaciones.crear'))
            <a class="ui-btn-secondary" href="{{ route('flujo.importar.index', ['consolidado_id' => $consolidado->id]) }}">Preparar asignaciones en Excel</a>
        @endif
        @if(app(\App\Services\FlujoAcceso::class)->puede(auth()->user(), 'exportar_consolidados'))
        <x-ui.exportar-excel :url="route('flujo.consolidados.index', ['xlsx' => 1, 'consolidado_id' => $consolidado->id])" />
        @endif
    </x-slot:acciones>
    @php
        $version = $consolidado->eventos->max('id');
        $originales = $consolidado->inspecciones->groupBy('colaborador_codigo');
        $etiquetas = [
            'crear' => 'Crear consolidado', 'enviar' => 'Enviar a coordinación', 'revisar' => 'Iniciar revisión',
            'preparar_detalle' => 'Verificar desglose histórico', 'ajustar' => 'Ajustar por colaborador',
            'corregir' => 'Corrección histórica', 'aprobar' => 'Aprobar consolidado', 'devolver' => 'Devolver para corrección',
            'retirar' => 'Retirar para corrección', 'reabrir' => 'Reabrir aprobación', 'cerrar' => 'Cerrar compras',
            'plan_compra' => 'Actualizar plan de compra', 'reasignar' => 'Reasignar responsable regional',
        ];
    @endphp
    @include('flujo.accesos')
    <dl class="flujo-dato">
        <div><dt>Estado</dt><dd>@include('flujo.estado', ['estado' => $consolidado->estado])</dd></div>
        <div><dt>Período</dt><dd>{{ $consolidado->desde->format('d/m/Y') }} – {{ $consolidado->hasta->format('d/m/Y') }}</dd></div>
        <div><dt>Coordinador responsable</dt><dd>{{ $consolidado->coordinador?->name }}</dd></div>
        @if($consolidado->corte && app(\App\Services\FlujoAcceso::class)->puedeCortes(auth()->user()))
            <div><dt>Corte de origen</dt><dd><a class="flujo-enlace" href="{{ route('flujo.cortes.show', $consolidado->corte) }}">#{{ $consolidado->corte->id }} · {{ $consolidado->corte->nombre }}</a></dd></div>
        @endif
        @if($consolidado->aprobado_en)
            <div><dt>Aprobó</dt><dd>{{ $consolidado->aprobador?->name }}<span class="block text-xs">{{ $consolidado->aprobado_en->format('d/m/Y H:i') }}</span></dd></div>
        @endif
    </dl>
    @if($consolidado->estado === 'cerrado')
        <p class="ui-alert ui-alert-info">La gestión de compras está cerrada. Consulta las asignaciones para conocer el avance de las entregas.</p>
    @endif
    <nav class="sistema-pestanas" aria-label="Vista del consolidado">
        @foreach(['colaborador' => 'Por colaborador', 'resumen' => 'Resumen general'] as $clave => $titulo)
            <a href="{{ route('flujo.consolidados.show', [$consolidado, 'vista' => $clave]) }}" @if($vista === $clave) aria-current="page" @endif>{{ $titulo }}</a>
        @endforeach
    </nav>

    @unless($consolidado->detalle_individual)
        <p class="ui-alert-warning">El desglose histórico está pendiente de verificación. Las cantidades aprobadas constan en el resumen.</p>
    @endunless
    @if($vista === 'resumen')
        <x-sistema.panel titulo="Totales por producto">
            <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Totales por producto">
                <table class="ui-table min-w-[440px]">
                    <thead><tr><th>Producto</th><th>Original</th><th>{{ $consolidado->aprobado_en ? 'Aprobado' : 'Propuesto' }}</th><th>Diferencia</th></tr></thead>
                    <tbody>
                        @forelse($consolidado->lineas as $linea)
                            <tr><th scope="row">{{ $linea->producto?->nombre }} <small>{{ $linea->producto_codigo }}</small></th><td>{{ $linea->original }}</td>
                                <td>{{ $linea->cantidad }}</td><td>{{ sprintf('%+d', $linea->cantidad - $linea->original) }}</td></tr>
                        @empty
                            <tr><td colspan="4">Sin productos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-sistema.panel>
    @elseif($consolidado->detalle_individual)
        <x-sistema.panel titulo="Necesidades por colaborador">
            <div class="space-y-3">
                @forelse($consolidado->participantes->sortBy('colaborador.nombre') as $persona)
                    @include('flujo.consolidado-participante', ['inspecciones' => $originales->get($persona->colaborador_codigo, collect())])
                @empty
                    <p class="text-sm">Sin colaboradores registrados.</p>
                @endforelse
            </div>
        </x-sistema.panel>
        @if($puedeAgregar)
            <details class="flujo-lectura" @if(old('busqueda_colaborador') || old('colaborador_codigo')) open @endif>
                <summary>Agregar colaborador</summary>
                <form method="POST" action="{{ route('flujo.consolidados.transicion', [$consolidado, 'ajustar']) }}" class="grid gap-3 p-4 sm:grid-cols-2" data-buscar-colaborador="{{ route('flujo.consolidados.colaboradores', $consolidado) }}">
                    @csrf
                    <input type="hidden" name="version" value="{{ $version }}">
                    <label class="ui-label">Buscar por nombre o código
                        <input class="ui-input mt-1" type="search" name="busqueda_colaborador" autocomplete="off" maxlength="100" value="{{ old('busqueda_colaborador') }}">
                    </label>
                    <label class="ui-label">Colaborador
                        <select name="colaborador_codigo" data-colaborador-anterior="{{ old('colaborador_codigo') }}" class="ui-select mt-1" required>
                            <option value="">Escribe al menos dos caracteres</option>
                        </select>
                    </label>
                    <p class="text-sm sm:col-span-2" role="status" data-colaborador-estado></p>
                    @include('flujo.consolidado-producto', ['obligatorio' => true, 'recuperar' => filled(old('colaborador_codigo'))])
                    <label class="ui-label sm:col-span-2">Justificación
                        <textarea name="nota" class="ui-input mt-1" required rows="2" maxlength="4000">{{ old('colaborador_codigo') ? old('nota') : '' }}</textarea>
                    </label>
                    <div class="sm:col-span-2"><button class="ui-btn-primary">Guardar incorporación</button></div>
                </form>
            </details>
        @endif
        @if($editar)
            <datalist id="catalogo-productos">
                @foreach($productos as $producto)<option value="{{ $producto->codigo }}">{{ $producto->nombre }}</option>@endforeach
            </datalist>
        @endif
    @endif

    @if($acciones->diff(['ajustar'])->isNotEmpty())
        <x-sistema.panel titulo="Acciones del consolidado">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($acciones->diff(['ajustar']) as $accion)
                    @continue($accion === 'preparar_detalle' && $consolidado->detalle_individual)
                    <form action="{{ route('flujo.consolidados.transicion', [$consolidado, $accion]) }}" method="POST"
                        class="flujo-card" @if(in_array($accion, ['aprobar', 'reasignar', 'cerrar', 'enviar'])) data-confirmar="¿{{ $etiquetas[$accion] }}?" @endif>
                        @csrf
                        <input type="hidden" name="version" value="{{ $version }}">
                        @if(in_array($accion, ['devolver', 'retirar', 'reabrir', 'reasignar']))
                            <label class="ui-label">Motivo
                                <textarea name="nota" required maxlength="4000" rows="2" class="ui-input mt-1"></textarea>
                            </label>
                        @endif
                        @if($candidatos->isNotEmpty() && ($accion === 'reasignar' || ($accion === 'enviar' && $consolidado->estado === 'borrador')))
                            <label class="ui-label">Coordinador responsable
                                <select class="ui-input mt-1" name="coordinador_id" required>
                                    <option value="">Selecciona un coordinador</option>
                                    @foreach($candidatos as $candidato)
                                        <option value="{{ $candidato->id }}" @selected((int) old('coordinador_id', $candidatos->count() === 1 ? $candidato->id : 0) === $candidato->id)>
                                            {{ $candidato->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        @if($accion === 'cerrar')
                            <p class="mb-2 text-sm">{{ $puedeCerrarCompra ? 'La cobertura está completa. Las entregas se registran por separado.' : 'Completa la cobertura y concilia las facturas para cerrar las compras.' }}</p>
                        @endif
                        <button class="{{ $accion === 'aprobar' ? 'ui-btn-primary' : 'ui-btn-secondary' }}" @disabled($accion === 'cerrar' && !$puedeCerrarCompra)>{{ $etiquetas[$accion] }}</button>
                    </form>
                @endforeach
            </div>
        </x-sistema.panel>
    @endif
    @if($seguimiento)
        <x-sistema.panel titulo="Seguimiento de compras">
            @php($prefijo = \App\Services\AccesoInventarioOperativo::prefijo(auth()->user()))
            <a class="ui-btn-secondary" href="{{ route('flujo.compras.index', ['estado' => $consolidado->estado, 'consolidados' => [$consolidado->id]]) }}">Ver seguimiento de compra</a>
            @include('flujo.facturas-consolidado')
            @foreach($constancias as $constancia)
                <p class="py-1 text-sm">Constancia de Gerencia · {{ $constancia->fecha->format('d/m/Y') }} · {{ $constancia->medio }}</p>
            @endforeach
        </x-sistema.panel>
    @endif
    <details class="flujo-lectura" @unless($consolidado->detalle_individual) open @endunless>
        <summary>Solicitudes originales</summary>
        <ul class="divide-y divide-slate-200 p-4 text-sm">
            @foreach($consolidado->inspecciones as $inspeccion)
                <li class="py-2">
                    <a class="flujo-enlace" href="{{ route('flujo.inspecciones.show', $inspeccion) }}">{{ $inspeccion->colaborador?->nombre }}</a>
                    · {{ $inspeccion->supervisor?->name }} · {{ $inspeccion->region?->nombre }} · {{ $inspeccion->proceso_nombre ?? 'Histórica sin proceso' }} · {{ $inspeccion->fecha->format('d/m/Y') }}
                    @foreach($inspeccion->lineas as $linea)<p>{{ $linea->etiqueta }} · {{ $linea->cantidad }}</p>@endforeach
                </li>
            @endforeach
        </ul>
    </details>
    <details class="flujo-lectura">
        <summary>Historial</summary>
        <ul class="divide-y divide-slate-200 p-4 text-sm">
            @foreach($consolidado->eventos->sortByDesc('id') as $evento)
                <li class="py-3">
                    <p class="font-semibold">{{ $etiquetas[$evento->accion] ?? $evento->accion }} · {{ $evento->user?->name }} · {{ $evento->created_at->format('d/m/Y H:i:s') }}</p>
                    @if($evento->nota)<p class="whitespace-pre-wrap break-words">{{ $evento->nota }}</p>@endif
                    @foreach($evento->cambios['productos'] ?? [] as $cambio)
                        @if(isset($cambio['incorporado']))
                            <p>Colaborador incorporado · {{ $cambio['colaborador'] }}</p>
                        @else
                            <p>{{ $cambio['colaborador'] ?? '' }} · {{ $cambio['producto'] }} · {{ $cambio['antes'] }} → {{ $cambio['nuevo'] }}</p>
                        @endif
                    @endforeach
                    @if(is_array($evento->cambios['coordinador'] ?? null))
                        <p>Responsable {{ $evento->cambios['coordinador']['antes'] }} → {{ $evento->cambios['coordinador']['nuevo'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </details>
</x-sistema.pagina>
