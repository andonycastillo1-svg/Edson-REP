@extends('layouts.admin')

@section('title', 'RRHH - Alertas de descuento')

@section('content')
@php
    $totalAlertas = method_exists($alertas, 'total') ? $alertas->total() : $alertas->count();
@endphp

<div class="w-full max-w-6xl mx-auto px-4 py-6">

    {{-- Encabezado --}}
    <div class="mb-4 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-xl border border-amber-200 bg-amber-50 flex items-center justify-center text-xl">
                    ⚠️
                </div>

                <div>
                    <h1 class="text-xl font-bold text-slate-900 leading-tight">
                        Alertas de descuento
                    </h1>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Reemplazos antes de cumplir vida útil y descuentos potenciales.
                    </p>
                </div>
            </div>

        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-5 py-2">
            <p class="text-xs text-slate-600">
                Total encontrado: <span class="font-semibold">{{ $totalAlertas }}</span>
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('info'))
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-700">
            {{ session('info') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Filtros --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
            <h2 class="text-sm font-bold text-slate-900">Filtros de alertas</h2>
            <p class="text-xs text-slate-500">
                Busca por colaborador, producto, rango de fechas o estado.
            </p>
        </div>

        <form method="GET" action="{{ route('rrhh.alertas.index') }}" class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">
                        Colaborador o producto
                    </label>
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Ejemplo: Edwin, guantes, 1014..."
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">
                        Desde
                    </label>
                    <input
                        type="date"
                        name="desde"
                        value="{{ request('desde') }}"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">
                        Hasta
                    </label>
                    <input
                        type="date"
                        name="hasta"
                        value="{{ request('hasta') }}"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">
                        Estado
                    </label>
                    <select
                        name="estado"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                        <option value="" @selected(request('estado') === null || request('estado') === '')>Todos</option>
                        <option value="pendiente" @selected(request('estado') === 'pendiente')>Pendiente</option>
                        <option value="finalizado" @selected(request('estado') === 'finalizado')>Finalizado</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="flex flex-wrap gap-2">
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700 transition">
                        Filtrar
                    </button>

                    <a href="{{ route('rrhh.alertas.index') }}"
                       class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Limpiar
                    </a>
                </div>

                <a href="{{ route('rrhh.alertas.export', request()->query()) }}"
                   class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                    Descargar Excel
                </a>
            </div>
        </form>
    </div>

    {{-- Tabla --}}
    <div class="mt-4 rounded-xl border border-amber-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-3 bg-amber-50 border-b border-amber-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700 text-sm">
                        ⚠️
                    </span>

                    <div>
                        <h2 class="text-sm font-bold text-amber-900 leading-tight">
                            Alertas de reemplazo con descuento potencial
                        </h2>
                        <p class="mt-0.5 text-xs text-amber-700">
                            Casos donde el artículo fue reemplazado antes de cumplir su vida útil.
                        </p>
                    </div>
                </div>

                <span class="inline-flex items-center justify-center rounded-full bg-amber-200 px-3 py-1 text-xs font-bold text-amber-900">
                    {{ $totalAlertas }}
                </span>
            </div>
        </div>

        <div class="p-4">
            @if($alertas->isEmpty())
                <div class="rounded-lg border border-amber-100 bg-amber-50 px-4 py-3">
                    <p class="text-sm text-amber-800">
                        No hay alertas de descuento para los filtros seleccionados.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                                <th class="px-3 py-2 font-bold">Colaborador</th>
                                <th class="px-3 py-2 font-bold">Producto</th>
                                <th class="px-3 py-2 font-bold">Asignación</th>
                                <th class="px-3 py-2 font-bold">Daño/Reemplazo</th>
                                <th class="px-3 py-2 font-bold">Vida útil</th>
                                <th class="px-3 py-2 font-bold">Restante</th>
                                <th class="px-3 py-2 font-bold">Descuento</th>
                                <th class="px-3 py-2 font-bold">Estado</th>
                                <th class="px-3 py-2 font-bold text-right">Acción</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($alertas as $a)
                                <tr class="border-b border-slate-100 text-slate-700 hover:bg-amber-50/50 transition">
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="font-semibold">{{ $a->colaborador_codigo }}</span>
                                        <span class="text-slate-400">-</span>
                                        {{ $a->colaborador_nombre ?? 'Sin nombre' }}
                                    </td>

                                    <td class="px-3 py-2">
                                        {{ $a->producto_descripcion ?: ($a->producto_nombre ?: $a->producto_codigo) }}
                                    </td>

                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ optional($a->fecha_asignacion_anterior)->format('d/m/Y') }}
                                    </td>

                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ optional($a->fecha_dano_reemplazo)->format('d/m/Y') }}
                                    </td>

                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ $a->vida_util_meses }} meses
                                    </td>

                                    <td class="px-3 py-2 whitespace-nowrap font-semibold text-amber-800">
                                        {{ $a->meses_restantes_reales }} meses
                                    </td>

                                    <td class="px-3 py-2 whitespace-nowrap font-semibold">
                                        @if(!$a->descuento_aplicable)
                                            <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">
                                                No aplica
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-rose-50 px-2 py-1 text-xs text-rose-700">
                                                Q {{ number_format($a->descuento_calculado, 2) }}
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-3 py-2 whitespace-nowrap">
                                        @if(($a->estado ?? 'pendiente') === 'finalizado')
                                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">
                                                Finalizado
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-200">
                                                Pendiente
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-3 py-2 whitespace-nowrap text-right">
                                        @if(($a->estado ?? 'pendiente') === 'pendiente')
                                            <form method="POST"
                                                  action="{{ route('rrhh.alertas.finalizar', $a) }}"
                                                  onsubmit="return confirm('¿Confirmas marcar esta alerta de descuento como finalizada?');">
                                                @csrf
                                                <input type="hidden" name="confirmar" value="1">
                                                <button type="submit"
                                                        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition">
                                                    Finalizar
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs font-semibold text-slate-400">Gestionada</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(method_exists($alertas, 'links'))
                    <div class="mt-4">
                        {{ $alertas->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

</div>
@endsection