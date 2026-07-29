@extends((int) auth()->user()->role_id === 1 ? 'layouts.admin' : 'layouts.operador')

@section('title', 'Solicitudes de traslado')

@section('content')
@php
    $user = auth()->user();
    $prefix = (int) $user->role_id === 2 ? 'operador' : 'admin';

    $estadoFiltro = request('estado', $estado ?? '');
    $origenFiltro = request('origen', $origen ?? '');
    $destinoFiltro = request('destino', $destino ?? '');

    $total = method_exists($operaciones, 'total')
        ? $operaciones->total()
        : $operaciones->count();
@endphp

<div class="mx-auto w-full max-w-5xl">

    @if(session('ok'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('ok') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">

        {{-- Encabezado --}}
        <header class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">
                    Solicitudes de traslado
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    {{ $user->isEncargado()
                        ? 'Solicitudes destinadas a tu bodega.'
                        : 'Solicitudes creadas y gestionadas por tu usuario.' }}
                </p>
            </div>

            @unless($user->isEncargado())
                <a href="{{ route($prefix.'.operaciones.traslados.create') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-extrabold text-white hover:bg-blue-700">
                    + Nueva solicitud
                </a>
            @endunless
        </header>

        {{-- Filtros --}}
        <form method="GET"
              class="grid grid-cols-1 gap-3 border-t border-slate-200 px-5 py-4 md:grid-cols-3 lg:grid-cols-[150px_1fr_1fr_auto_auto]">

            <div>
                <label class="mb-1 block text-xs font-extrabold text-slate-600">
                    Estado
                </label>

                <select name="estado" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    <option value="PENDIENTE" @selected($estadoFiltro === 'PENDIENTE')>Pendiente</option>
                    <option value="APROBADO" @selected($estadoFiltro === 'APROBADO')>Aprobado</option>
                    <option value="RECHAZADO" @selected($estadoFiltro === 'RECHAZADO')>Rechazado</option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-extrabold text-slate-600">
                    Origen
                </label>

                <select name="origen" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>

                    @foreach($bodegas as $bodega)
                        <option value="{{ $bodega->id }}"
                            @selected((string) $origenFiltro === (string) $bodega->id)>
                            {{ $bodega->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-extrabold text-slate-600">
                    Destino
                </label>

                <select name="destino" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>

                    @foreach($bodegas as $bodega)
                        <option value="{{ $bodega->id }}"
                            @selected((string) $destinoFiltro === (string) $bodega->id)>
                            {{ $bodega->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit"
                        class="w-full rounded-xl !border-blue-600 !bg-blue-600 px-4 py-2 text-sm font-extrabold !text-white hover:!bg-blue-700">
                    Filtrar
                </button>
            </div>

            <div class="flex items-end">
                <a href="{{ route($prefix.'.operaciones.traslados.index') }}"
                   class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-extrabold text-slate-700 hover:bg-slate-50">
                    Limpiar
                </a>
            </div>
        </form>

        {{-- Título del listado --}}
        <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3">
            <h2 class="text-sm font-extrabold text-slate-950">
                Listado de solicitudes
            </h2>

            <span class="text-xs font-bold text-slate-500">
                {{ $total }} {{ $total === 1 ? 'solicitud' : 'solicitudes' }}
            </span>
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto border-t border-slate-200">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-16 px-3 py-3 text-left text-xs font-extrabold uppercase text-slate-500">#</th>
                        <th class="w-36 px-3 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Estado</th>
                        <th class="w-[280px] px-3 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Origen → Destino</th>
                        <th class="w-[230px] px-3 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Creado</th>
                        <th class="w-20 px-3 py-3 text-right text-xs font-extrabold uppercase text-slate-500">Acción</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($operaciones as $op)
                        @php
                            $badge = match($op->estado) {
                                'PENDIENTE' => 'border-amber-200 bg-amber-50 text-amber-700',
                                'APROBADO' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                'RECHAZADO' => 'border-rose-200 bg-rose-50 text-rose-700',
                                default => 'border-slate-200 bg-slate-50 text-slate-600',
                            };
                        @endphp

                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-3 font-extrabold text-slate-950">
                                #{{ $op->id }}
                            </td>

                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-extrabold {{ $badge }}">
                                    {{ ucfirst(strtolower($op->estado)) }}
                                </span>
                            </td>

                            <td class="px-3 py-3 font-extrabold text-slate-950">
                                {{ optional($op->bodegaOrigen)->nombre ?? '—' }}
                                <span class="mx-1 text-slate-400">→</span>
                                {{ optional($op->bodegaDestino)->nombre ?? '—' }}
                            </td>

                            <td class="px-3 py-3">
                                <p class="font-extrabold text-slate-950">
                                    {{ $op->created_at->format('d/m/Y H:i') }}
                                </p>

                                <p class="mt-0.5 text-xs text-slate-500">
                                    Por: {{ optional($op->creador)->name ?? '—' }}
                                </p>
                            </td>

                            <td class="px-3 py-3 text-right">
                                <a href="{{ route($prefix.'.operaciones.traslados.show', $op) }}"
                                   class="inline-flex rounded-lg bg-blue-600 px-3 py-2 text-xs font-extrabold text-white hover:bg-blue-700">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center text-sm text-slate-500">
                                No hay solicitudes registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if(method_exists($operaciones, 'hasPages') && $operaciones->hasPages())
            <footer class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">
                    Mostrando
                    <strong class="text-slate-900">{{ $operaciones->firstItem() }}</strong>
                    a
                    <strong class="text-slate-900">{{ $operaciones->lastItem() }}</strong>
                    de
                    <strong class="text-slate-900">{{ $operaciones->total() }}</strong>
                </p>

                {{ $operaciones->withQueryString()->onEachSide(1)->links() }}
            </footer>
        @endif

    </div>
</div>
@endsection