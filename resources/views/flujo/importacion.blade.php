<x-sistema.pagina :titulo="isset($importacion) && $importacion->confirmada_en ? 'Importación registrada' : 'Vista previa de asignaciones'" :volver="route('flujo.importar.index')">
    @php
        $errores = $errores ?? [];
        $prefijo = \App\Sistema\CatalogoPermisos::prefijo(auth()->user()->rolOperativo());
    @endphp
    @php($origen = $importacion->consolidado ?? $consolidado ?? null)
    @if($origen)
        <p class="ui-alert ui-alert-info">Requerimiento interno · {{ $origen->nombre }} · #{{ $origen->id }}</p>
    @elseif(collect($filas)->pluck('consolidado_id')->filter()->isNotEmpty())
        <p class="ui-alert ui-alert-info">Cada fila conserva el consolidado de origen.</p>
    @elseif(isset($importacion) && !$importacion->confirmada_en)
        <p class="ui-alert-warning">Asignación independiente. Para aplicar estas entregas a un consolidado, vuelve a cargar el archivo con el requerimiento seleccionado.</p>
    @endif
    @if(isset($importacion) && $importacion->confirmada_en)
        <x-sistema.panel titulo="Hojas de asignación">
            @rutaDisponible($prefijo.'.asignaciones.pdf')
                <a class="ui-btn-primary mb-3" href="{{ route('flujo.importar.hojas', $importacion) }}">Descargar hojas imprimibles (ZIP)</a>
            @endrutaDisponible
            <ul class="divide-y divide-slate-200 text-sm">
                @foreach($importacion->entregas as $entrega)
                    <li class="flex flex-wrap items-center gap-3 py-3">
                        <span class="font-semibold">{{ $personas->get($entrega['codigo'], $entrega['codigo']) }} — {{ $entrega['codigo'] }} · {{ $entrega['productos'] }} productos</span>
                        @rutaDisponible($prefijo.'.asignaciones.pdf')
                            <a class="ui-btn-secondary" target="_blank" rel="noopener" href="{{ route($prefijo.'.asignaciones.pdf', $entrega['grupo']) }}">Imprimir hoja</a>
                        @endrutaDisponible
                        @rutaDisponible($prefijo.'.asignaciones.entrega')
                            <a class="underline" href="{{ route($prefijo.'.asignaciones.entrega', $entrega['grupo']) }}">Ver respaldo</a>
                        @endrutaDisponible
                    </li>
                @endforeach
            </ul>
        </x-sistema.panel>
        @if($consolidadosConciliacion->isNotEmpty())
            <x-sistema.panel titulo="Conciliar entregas registradas">
                <p class="mb-3 text-sm">Usa este paso si las hojas de la operación y el requerimiento confirman qué consolidado corresponde al lote. Verifica los códigos, productos y fechas antes de confirmar. El inventario conserva su saldo.</p>
                <form class="space-y-3" method="POST" action="{{ route('flujo.importar.conciliar', $importacion) }}"
                      data-confirmar="¿Vincular al consolidado las entregas verificadas de este lote?">
                    @csrf
                    <label class="ui-label">Consolidado verificado
                        <select name="consolidado_id" class="ui-input mt-1" required>
                            <option value="">Selecciona el consolidado</option>
                            @foreach($consolidadosConciliacion as $destino)
                                <option value="{{ $destino->id }}" @selected((int) old('consolidado_id', $importacion->consolidado_id) === $destino->id)>{{ $destino->nombre }} · #{{ $destino->id }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ui-label">Evidencia revisada
                        <textarea name="evidencia" class="ui-input mt-1" rows="2" minlength="10" maxlength="2000" required>{{ old('evidencia') }}</textarea>
                    </label>
                    @error('conciliacion')<p class="ui-alert-error" role="alert">{{ $message }}</p>@enderror
                    <button class="ui-btn-secondary">Conciliar entregas</button>
                </form>
            </x-sistema.panel>
        @endif
    @else
        <x-sistema.panel>
            <div class="flujo-cabecera-panel">
                <h2 class="text-lg font-bold">{{ $errores ? 'Cambios necesarios' : 'Archivo listo para importar' }}</h2>
                <span class="ui-badge ui-badge-info">Salida de {{ $bodega->nombre }}</span>
            </div>
            @if($errores)
                <p class="ui-alert-error" role="alert">Corrige el Excel y cárgalo de nuevo. El archivo se importa completo cuando todas las filas son válidas.</p>
                @foreach($errores[1] ?? [] as $mensaje)<p class="py-2 text-sm">{{ $mensaje }}</p>@endforeach
                <a class="ui-btn-secondary mt-3" href="{{ route('flujo.importar.index') }}">Cargar archivo corregido</a>
            @else
                <dl class="flujo-dato mb-4">
                    <div><dt>Filas válidas</dt><dd>{{ count($filas) }}</dd></div>
                    <div><dt>Colaboradores</dt><dd>{{ collect($filas)->pluck('colaborador_codigo')->unique()->count() }}</dd></div>
                </dl>
                <p class="mb-3 text-sm">Al confirmar se registra una hoja por colaborador y consolidado y se descuenta el inventario.</p>
                <form method="POST" action="{{ route('flujo.importar.confirmar', $importacion) }}"
                      data-confirmar="¿Registrar estas asignaciones y descontar las cantidades del inventario de central?">
                    @csrf
                    <button class="ui-btn-primary">Confirmar importación</button>
                </form>
            @endif
        </x-sistema.panel>
    @endif
    <x-sistema.panel titulo="Filas de la hoja Asignaciones">
        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Filas de asignación">
            <table class="ui-table min-w-[580px]">
                <thead class="bg-slate-50"><tr><th class="p-3">Fila</th><th class="p-3">Colaborador</th><th class="p-3">Producto</th><th class="p-3">Consolidado</th><th class="p-3">Cantidad</th><th class="p-3">Fecha</th><th class="p-3">Medio de solicitud</th><th class="p-3">Motivo</th>@if($errores)<th class="p-3">Corrección necesaria</th>@endif</tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($filas as $numero => $fila)
                        <tr>
                            <td class="p-3">{{ $numero }}</td><td class="p-3">{{ $personas->get($fila['colaborador_codigo'], $fila['colaborador_codigo']) }} — {{ $fila['colaborador_codigo'] }}</td><td class="p-3">{{ $fila['producto_codigo'] }}</td><td class="p-3">{{ $fila['consolidado_id'] ?? $origen?->id ?? '—' }}</td>
                            <td class="p-3">{{ $fila['cantidad'] }}</td><td class="p-3">{{ $fila['fecha'] }}</td><td class="p-3">{{ $fila['medio_solicitud'] ?? '—' }}</td><td class="p-3">{{ $fila['motivo_asignacion'] }}</td>
                            @if($errores)<td class="p-3 text-rose-800">{{ implode(' ', $errores[$numero] ?? []) }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-sistema.panel>
</x-sistema.pagina>
