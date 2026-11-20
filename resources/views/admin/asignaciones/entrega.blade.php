@extends('layouts.app')
@section('title', 'Detalle de entrega')
@section('content')
<div class="mx-auto max-w-5xl space-y-5 px-4 py-8">
    <x-internal-navigation :back-url="route($routePrefix.'.asignaciones.index')" />
    <header class="rounded-2xl border border-slate-200 bg-white p-6">
        <h1 class="text-2xl font-extrabold text-slate-900">Entrega a {{ $asignaciones->first()->colaborador?->nombre ?? $asignaciones->first()->colaborador_codigo }}</h1>
        @if($entrega)
            <p class="mt-1 text-slate-600">Salida del {{ $entrega->salida_en->format('d/m/Y H:i') }} · {{ $entrega->modalidad === 'tercero' ? ($entrega->tercero ? 'Transporta '.$entrega->tercero : 'Salida por tercero') : 'Entrega directa' }}</p>
        @endif
        <div class="mt-4 flex flex-wrap gap-3">
            @rutaDisponible($routePrefix.'.asignaciones.pdf')
                <a class="ui-btn-download" href="{{ route($routePrefix.'.asignaciones.pdf', $grupo) }}" target="_blank" rel="noopener">Hoja de asignación</a>
            @endrutaDisponible
            @if(session('grupo_devolucion'))
                @rutaDisponible($routePrefix.'.asignaciones.hoja_devolucion')
                    <a class="ui-btn-download" href="{{ route($routePrefix.'.asignaciones.hoja_devolucion', session('grupo_devolucion')) }}">Hoja de devolución física</a>
                @endrutaDisponible
            @endif
        </div>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="mb-3 text-lg font-bold">Custodia de los productos</h2>
        <ul class="divide-y divide-slate-200">
            @foreach($asignaciones as $fila)
                @php($segundos = $vigencias[$fila->id])
                <li class="flex flex-wrap justify-between gap-2 py-3">
                    <div><strong>{{ $fila->producto?->nombre ?? $fila->producto_codigo }}</strong> · {{ $fila->producto_codigo }}
                        <p class="text-sm text-slate-600">{{ $fila->bodega?->nombre ?? 'Bodega sin identificar' }}
                            @if(in_array($fila->stock_tipo, ['nuevo', 'usado'], true))
                                · {{ $fila->stock_tipo === 'usado' ? 'Inventario usado' : 'Inventario nuevo' }}
                            @endif
                        </p>
                        @foreach($fila->incidencias as $incidencia)
                            @rutaDisponible($routePrefix.'.asignaciones.incidencia')
                                <a class="block text-sm underline" href="{{ route($routePrefix.'.asignaciones.incidencia', $incidencia) }}">{{ $incidencia->motivo_etiqueta }}</a>
                            @endrutaDisponible
                        @endforeach
                    </div>
                    <div class="text-sm">
                        @if($fila->estado === 'Activa')
                            {{ $fila->custodia_etiqueta }} · {{ $fila->cantidad_asignada }} unidad(es)
                            <p>Vida útil {{ $segundos === null ? 'desconocida' : ($segundos > 0 ? 'vigente' : 'vencida') }}</p>
                        @else
                            Cerrada · {{ $fila->estado }}
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-bold">Recepción</h2>
        @if($entrega?->confirmada_en)
            <p>Confirmada por {{ $entrega->confirmador?->name ?? 'Usuario histórico' }} el {{ $entrega->confirmada_en->format('d/m/Y H:i') }} · {{ \App\Models\EntregaInventario::MEDIOS[$entrega->medio_confirmacion] ?? $entrega->medio_confirmacion }}</p>
            <p>Recepción real {{ $entrega->recibida_en?->format('d/m/Y H:i') ?? 'sin fecha registrada' }}</p>
        @elseif($entrega)
            <p>Salida registrada. La recepción se puede documentar si se conoce.</p>
        @else
            <p>Recepción sin datos históricos</p>
        @endif

        @if($puedeConfirmar && $entrega->recibida_en === null)
            <details class="mt-3">
            <summary class="cursor-pointer font-semibold">Agregar datos de recepción (opcional)</summary>
            <form method="POST" action="{{ route($routePrefix.'.asignaciones.confirmar_recepcion', $grupo) }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                @unless($entrega->confirmada_en)
                    <label class="text-sm font-semibold">Medio de confirmación
                        <select name="medio_confirmacion" class="mt-1 block w-full rounded-xl border-slate-300" required>
                            <option value="">Selecciona un medio</option>
                            @foreach(\App\Models\EntregaInventario::MEDIOS as $medio => $nombre)
                                <option value="{{ $medio }}" @selected(old('medio_confirmacion') === $medio)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                @endunless
                <label class="text-sm font-semibold">Fecha real {{ $entrega->confirmada_en ? '' : '(si se conoce)' }}
                    <input type="datetime-local" name="recibida_en" value="{{ old('recibida_en') }}"
                        min="{{ $entrega->salida_en->format('Y-m-d\TH:i') }}" class="mt-1 block rounded-xl border-slate-300" @required($entrega->confirmada_en)>
                </label>
                <button class="ui-btn-primary">{{ $entrega->confirmada_en ? 'Registrar fecha real' : 'Confirmar recepción' }}</button>
            </form>
            </details>
        @endif
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-bold">Documentación</h2>
        <p>{{ $archivos->contains('tipo_documento', 'asignacion_firmada') ? 'Hoja firmada cargada' : 'Hoja firmada faltante' }}</p>
        @if($entrega)
            @rutaDisponible($routePrefix.'.asignaciones.documento_entrega')
                <form method="POST" enctype="multipart/form-data" action="{{ route($routePrefix.'.asignaciones.documento_entrega', $grupo) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <label class="text-sm font-semibold">Hoja firmada (PDF)
                        <input name="pdf_firmado" type="file" accept="application/pdf" class="mt-1 block w-full text-sm">
                    </label>
                    <label class="text-sm font-semibold">Fotografía (opcional)
                        <input name="foto" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm">
                    </label>
                    <div class="sm:col-span-2"><button class="ui-btn-primary">Guardar respaldo</button></div>
                </form>
            @endrutaDisponible
        @endif
        @rutaDisponible($routePrefix.'.asignaciones.ver_pdf_firmado')
            @foreach(['asignacion_firmada' => 'Versiones de la hoja firmada', 'evidencia_entrega' => 'Fotografías'] as $tipo => $titulo)
                @php($documentos = $archivos->where('tipo_documento', $tipo)->sortBy('id')->values())
                @if($documentos->isNotEmpty())
                    <h3 class="mt-4 font-bold">{{ $titulo }}</h3>
                    <ul class="text-sm">
                        @foreach($documentos as $archivo)
                            <li class="mt-2">
                                <a class="underline" href="{{ route($routePrefix.'.asignaciones.ver_pdf_firmado', $archivo) }}">{{ $tipo === 'asignacion_firmada' ? 'Versión '.($loop->index + 1).' · ' : '' }}{{ $archivo->nombre_original }}</a>
                                · {{ $archivo->created_at?->format('d/m/Y H:i') }} · {{ $archivo->subidoPor?->name ?? 'Usuario histórico' }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        @endrutaDisponible
    </section>
</div>
@endsection
