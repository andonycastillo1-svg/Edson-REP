@extends((int) auth()->user()->role_id === 1 ? 'layouts.admin' : 'layouts.operador')

@section('title', 'Bodegas')

@section('content')
@php
    $user = auth()->user();
    $esAdmin = (int) $user->role_id === 1;
    $esOperador = (int) $user->role_id === 2;
    $bodegaOperadorId = (int) $user->bodega_id;
@endphp

<div class="mx-auto w-full max-w-6xl">

    {{-- Encabezado --}}
    <div class="mb-4 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-950">Bodegas</h1>
            <p class="mt-1 text-sm text-slate-500">
                Consulta inventario, traslados y administración de bodegas.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-extrabold text-slate-700">
                Total: {{ $bodegas->count() }}
            </span>

            <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-extrabold text-emerald-700">
                Principales: {{ $bodegas->where('tipo', 'Principal')->count() }}
            </span>

            <span class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-extrabold text-blue-700">
                Regionales: {{ $bodegas->where('tipo', 'Regional')->count() }}
            </span>

            @if($esAdmin)
                <a href="{{ route('admin.bodegas.create') }}" class="btn-new">
                    + Nueva bodega
                </a>
            @endif
        </div>
    </div>

    {{-- Bodegas --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @forelse($bodegas as $bodega)
            @php
                $esMiBodega = (int) $bodega->id === $bodegaOperadorId;
                $esPrincipal = $bodega->tipo === 'Principal';

                $rutaInventario = $esOperador
                    ? route('operador.bodegas.show', $bodega->id)
                    : route('admin.bodegas.show', $bodega->id);
            @endphp

            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-start justify-between gap-4 p-5">
                    <div class="flex min-w-0 gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-extrabold text-slate-900">
                            {{ strtoupper(mb_substr($bodega->nombre ?? 'B', 0, 1)) }}
                        </span>

                        <div class="min-w-0">
                            <h2 class="font-extrabold text-slate-950">
                                {{ $bodega->nombre ?? 'Bodega #'.$bodega->id }}
                            </h2>

                            <p class="mt-1 text-sm text-slate-500">
                                {{ $bodega->ubicacion ?? 'Sin ubicación registrada' }}
                            </p>

                            <p class="mt-1 text-xs font-semibold text-slate-600">
                                ID: {{ $bodega->id }}
                            </p>

                            @if($esOperador)
                                <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-extrabold
                                    {{ $esMiBodega
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-slate-100 text-slate-600' }}">
                                    {{ $esMiBodega ? 'Tu bodega asignada' : 'Solo consulta' }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <span class="shrink-0 rounded-full border px-3 py-1 text-xs font-extrabold
                        {{ $esPrincipal
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : 'border-amber-200 bg-amber-50 text-blue-700' }}">
                        {{ $bodega->tipo ?? '—' }}
                    </span>
                </div>

                <div class="flex flex-wrap gap-2 border-t border-slate-200 bg-slate-50 p-3">
                    <a href="{{ $rutaInventario }}" class="action-btn btn-inventario">
                        Inventario
                    </a>

                    @if($esAdmin)
                        <a href="{{ route('admin.operaciones.traslados.create', ['origen' => $bodega->id]) }}"
                           class="action-btn btn-trasladar">
                            Trasladar
                        </a>

                        <a href="{{ route('admin.operaciones.traslados.index') }}"
                           class="action-btn btn-aprobacion">
                            Aprobación
                        </a>

                        <a href="{{ route('admin.bodegas.edit', $bodega->id) }}"
                           class="action-btn btn-editar">
                            Editar
                        </a>

                        <form method="POST"
                              action="{{ route('admin.bodegas.destroy', $bodega->id) }}"
                              onsubmit="return confirm('¿Eliminar esta bodega?')">
                            @csrf
                            @method('DELETE')

                            <button type="submit" class="action-btn btn-eliminar">
                                Eliminar
                            </button>
                        </form>
                    @elseif($esMiBodega)
                        <a href="{{ route('operador.operaciones.traslados.create', ['origen' => $bodega->id]) }}"
                           class="action-btn btn-trasladar">
                            Trasladar
                        </a>
                    @endif
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-8 text-center">
                <h2 class="font-extrabold text-amber-900">No hay bodegas registradas</h2>
                <p class="mt-1 text-sm text-amber-700">Crea una nueva bodega para comenzar.</p>
            </div>
        @endforelse
    </div>

</div>
@endsection