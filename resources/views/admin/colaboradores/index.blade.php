@extends('layouts.admin')

@section('title', 'Colaboradores')

@section('content')
@php
    $prefix = (int) auth()->user()->role_id === 4 ? 'rrhh' : 'admin';

    $tabs = [
        'activos' => [
            'items' => $activos,
            'label' => 'Activos',
            'estado' => 'Activo',
            'badge' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'accion' => 'Inactivar',
            'accionClass' => '!border-rose-600 !bg-rose-600 !text-white hover:!bg-rose-700',
            'confirm' => '¿Deseas inactivar este colaborador?',
        ],
        'inactivos' => [
            'items' => $inactivos,
            'label' => 'Inactivos',
            'estado' => 'Inactivo',
            'badge' => 'border-slate-200 bg-slate-100 text-slate-700',
            'accion' => 'Activar',
            'accionClass' => '!border-emerald-600 !bg-emerald-600 !text-white hover:!bg-emerald-700',
            'confirm' => '¿Deseas activar este colaborador?',
        ],
    ];
@endphp

<div
    x-data="colaboradoresPage('{{ $prefix }}')"
    class="mx-auto w-full max-w-7xl"
>
    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">

        {{-- Encabezado --}}
        <header class="flex flex-col gap-5 px-5 py-5 lg:flex-row lg:items-center lg:justify-between sm:px-6">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-blue-600">
                    Gestión de personal
                </p>

                <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">
                    Colaboradores
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Administra expedientes, asignaciones y estados del personal.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="inline-flex rounded-xl border border-slate-200 bg-slate-100 p-1">
                    @foreach($tabs as $key => $config)
                        <button
                            type="button"
                            @click="tab='{{ $key }}'"
                            class="rounded-lg px-4 py-2 text-sm font-extrabold transition"
                            :class="tab === '{{ $key }}'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-slate-600 hover:bg-white'"
                        >
                            {{ $config['label'] }}
                            <span class="ml-1 text-xs">
                                ({{ $config['items']->total() }})
                            </span>
                        </button>
                    @endforeach
                </div>

                <a
                    href="{{ route($prefix.'.colaboradores.create') }}"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-extrabold text-white hover:bg-blue-700"
                >
                    + Nuevo colaborador
                </a>
            </div>
        </header>

        {{-- Búsqueda --}}
        <form
            method="GET"
            class="grid grid-cols-1 gap-3 border-t border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto]"
        >
            <input
                name="q"
                value="{{ request('q') }}"
                placeholder="Buscar por código, nombre o puesto..."
                class="w-full rounded-xl border-slate-300 px-4 py-2.5 text-sm"
            >

            <button
                type="submit"
                class="rounded-xl !border-blue-600 !bg-blue-600 px-5 py-2.5 text-sm font-extrabold !text-white hover:!bg-blue-700"
            >
                Buscar
            </button>

            @if(request('q'))
                <a
                    href="{{ route($prefix.'.colaboradores.index') }}"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-extrabold text-slate-700 hover:bg-slate-50"
                >
                    Limpiar
                </a>
            @endif
        </form>

        {{-- Selección masiva --}}
        <div class="flex flex-col gap-4 border-t border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <span
                    x-text="selectedCount()"
                    class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl bg-blue-600 px-2 text-sm font-extrabold text-white"
                ></span>

                <div>
                    <p class="text-sm font-extrabold text-slate-900">
                        Selección para descarga
                    </p>
                    <p class="text-xs text-slate-500">
                        Selecciona colaboradores para generar el Excel consolidado.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach($tabs as $key => $config)
                    <button
                        type="button"
                        x-show="tab === '{{ $key }}'"
                        @if($key === 'inactivos') x-cloak @endif
                        @click="seleccionarTodosVisibles(@js($config['items']->pluck('codigo')->values()))"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50"
                    >
                        Seleccionar visibles
                    </button>
                @endforeach

                <button
                    type="button"
                    @click="limpiarSeleccion()"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-extrabold text-rose-700 hover:bg-rose-100"
                >
                    Limpiar selección
                </button>

                <form
                    method="POST"
                    action="{{ route($prefix.'.colaboradores.fichas_tecnicas_masivas') }}"
                    x-ref="formFichasSeleccionadas"
                    @submit.prevent="descargarSeleccionados($refs.formFichasSeleccionadas)"
                >
                    @csrf

                    <template x-for="codigo in selectedCodigos" :key="codigo">
                        <input type="hidden" name="codigos[]" :value="codigo">
                    </template>

                    <button
                        type="submit"
                        :disabled="selectedCount() === 0"
                        class="rounded-xl !border-emerald-600 !bg-emerald-600 px-4 py-2 text-xs font-extrabold !text-white hover:!bg-emerald-700 disabled:opacity-40"
                    >
                        Descargar fichas
                    </button>
                </form>
            </div>
        </div>

        {{-- Tablas --}}
        @foreach($tabs as $key => $config)
            <div
                x-show="tab === '{{ $key }}'"
                @if($key === 'inactivos') x-cloak @endif
                class="border-t border-slate-200"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[950px] table-fixed text-sm">
                        <colgroup>
                            <col class="w-14">
                            <col class="w-28">
                            <col>
                            <col class="w-56">
                            <col class="w-28">
                            <col class="w-72">
                        </colgroup>

                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Sel.</th>
                                <th class="px-4 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Código</th>
                                <th class="px-4 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Colaborador</th>
                                <th class="px-4 py-3 text-left text-xs font-extrabold uppercase text-slate-500">Puesto</th>
                                <th class="px-4 py-3 text-center text-xs font-extrabold uppercase text-slate-500">Estado</th>
                                <th class="px-4 py-3 text-right text-xs font-extrabold uppercase text-slate-500">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @forelse($config['items'] as $colaborador)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            class="colaborador-selector h-4 w-4"
                                            :checked="isSelected('{{ $colaborador->codigo }}')"
                                            @change="toggleSeleccion('{{ $colaborador->codigo }}')"
                                        >
                                    </td>

                                    <td class="px-4 py-3">
                                        <span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-extrabold text-slate-700">
                                            {{ $colaborador->codigo }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3">
                                        <p class="font-extrabold text-slate-950">
                                            {{ $colaborador->nombre }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Expediente administrativo disponible
                                        </p>
                                    </td>

                                    <td class="px-4 py-3 text-slate-700">
                                        {{ $colaborador->puesto }}
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-extrabold {{ $config['badge'] }}">
                                            {{ $config['estado'] }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                @click="openDetalle('{{ $colaborador->codigo }}')"
                                                class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-extrabold text-white hover:bg-blue-700"
                                            >
                                                Expediente
                                            </button>

                                            <a
                                                href="{{ route($prefix.'.colaboradores.edit', $colaborador) }}"
                                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-slate-50"
                                            >
                                                Editar
                                            </a>

                                            <form
                                                method="POST"
                                                action="{{ route($prefix.'.colaboradores.estado', $colaborador) }}"
                                                onsubmit="return confirm('{{ $config['confirm'] }}')"
                                            >
                                                @csrf
                                                @method('PATCH')

                                                <button
                                                    type="submit"
                                                    class="rounded-lg border px-3 py-2 text-xs font-extrabold {{ $config['accionClass'] }}"
                                                >
                                                    {{ $config['accion'] }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-14 text-center text-sm text-slate-500">
                                        No hay colaboradores {{ $key }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(method_exists($config['items'], 'links'))
                    <footer class="border-t border-slate-200 px-5 py-4">
                        {{ $config['items']->withQueryString()->links() }}
                    </footer>
                @endif
            </div>
        @endforeach

    </section>

    @include('admin.colaboradores.Modals.expediente-modal')
    @include('admin.colaboradores.Modals.rrhh-revision-modal')
</div>

@include('admin.colaboradores.Modals.expediente-script')
@endsection