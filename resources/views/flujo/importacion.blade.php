<x-sistema.pagina :titulo="isset($importacion) && $importacion->confirmada_en ? 'Importación registrada' : 'Vista previa de asignaciones'" :volver="route('flujo.importar.index')">
    @include('flujo.accesos')
    @php
        $errores = $errores ?? [];
        $prefijo = \App\Sistema\CatalogoPermisos::prefijo(auth()->user()->rolOperativo());
    @endphp
    @if(isset($importacion) && $importacion->confirmada_en)
        <x-sistema.panel titulo="Hojas de asignación">
            @rutaDisponible($prefijo.'.asignaciones.pdf')
                <a class="ui-btn-primary mb-3" href="{{ route('flujo.importar.hojas', $importacion) }}">Descargar hojas imprimibles (ZIP)</a>
            @endrutaDisponible
            <ul class="divide-y divide-slate-200 text-sm">
                @foreach($importacion->entregas as $entrega)
                    <li class="flex flex-wrap items-center gap-3 py-3">
                        <span class="font-semibold">{{ $entrega['codigo'] }} · {{ $entrega['productos'] }} productos</span>
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
                <p class="mb-3 text-sm">Al confirmar se registra una asignación por colaborador y se descuenta el inventario.</p>
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
                <thead class="bg-slate-50"><tr><th class="p-3">Fila</th><th class="p-3">Colaborador</th><th class="p-3">Producto</th><th class="p-3">Cantidad</th><th class="p-3">Fecha</th><th class="p-3">Motivo</th>@if($errores)<th class="p-3">Corrección necesaria</th>@endif</tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($filas as $numero => $fila)
                        <tr>
                            <td class="p-3">{{ $numero }}</td><td class="p-3">{{ $fila['colaborador_codigo'] }}</td><td class="p-3">{{ $fila['producto_codigo'] }}</td>
                            <td class="p-3">{{ $fila['cantidad'] }}</td><td class="p-3">{{ $fila['fecha'] }}</td><td class="p-3">{{ $fila['motivo_asignacion'] }}</td>
                            @if($errores)<td class="p-3 text-rose-800">{{ implode(' ', $errores[$numero] ?? []) }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-sistema.panel>
</x-sistema.pagina>
