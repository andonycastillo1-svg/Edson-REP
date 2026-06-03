@extends('layouts.admin')

@section('title', 'Usuarios')

@section('content')
@php
    $routePrefix = auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
@endphp

<div class="w-full max-w-7xl mx-auto">

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        {{-- Encabezado --}}
        <div class="border-b border-slate-200 px-6 py-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                            Usuarios
                        </h1>

                        @if(method_exists($usuarios, 'total'))
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                {{ $usuarios->total() }} usuario{{ $usuarios->total() == 1 ? '' : 's' }}
                            </span>
                        @endif
                    </div>

                    <p class="mt-1 text-sm text-slate-500">
                        Administra accesos, roles y permisos del sistema.
                    </p>
                </div>

                <a href="{{ route($routePrefix . '.usuarios.create') }}"
                   class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                    + Nuevo usuario
                </a>
            </div>
        </div>

        {{-- Filtros compactos --}}
        <div class="border-b border-slate-100 bg-slate-50/70 px-6 py-4">
            <form method="GET"
                  action="{{ route($routePrefix . '.usuarios.index') }}"
                  class="flex flex-col gap-3 lg:flex-row lg:items-center">

                <div class="w-full lg:w-[420px]">
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Buscar por nombre o correo..."
                        class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm transition focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>

                <div class="w-full lg:w-64">
                    <select
                        name="role_id"
                        class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm transition focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">Todos los roles</option>
                        @foreach($rolesFiltro as $rol)
                            <option value="{{ $rol->id }}" {{ (string) request('role_id') === (string) $rol->id ? 'selected' : '' }}>
                                {{ $rol->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Filtrar
                    </button>

                    <a href="{{ route($routePrefix . '.usuarios.index') }}"
                       class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Limpiar
                    </a>
                </div>
            </form>

            @if(request('q') || request('role_id'))
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-semibold text-slate-500">Filtros activos:</span>

                    @if(request('q'))
                        <span class="rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-700">
                            Búsqueda: {{ request('q') }}
                        </span>
                    @endif

                    @if(request('role_id'))
                        @php
                            $rolActivo = $rolesFiltro->firstWhere('id', request('role_id'));
                        @endphp
                        <span class="rounded-full bg-indigo-50 px-3 py-1 font-semibold text-indigo-700">
                            Rol: {{ $rolActivo->nombre ?? request('role_id') }}
                        </span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-white">
                    <tr>
                        <th class="w-20 px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            ID
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Usuario
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Correo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Rol
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($usuarios as $u)
                        @php
                            $nombreUsuario = $u->name ?? $u->nombre ?? 'Sin nombre';
                            $rolNombre = $u->role->nombre ?? 'Sin rol';

                            $roleClasses = match(strtolower($rolNombre)) {
                                'administrador', 'admin' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                'rrhh' => 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
                                'coordinador' => 'bg-sky-50 text-sky-700 border-sky-200',
                                'almacenista', 'encargado' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'consultas' => 'bg-amber-50 text-amber-700 border-amber-200',
                                default => 'bg-slate-50 text-slate-700 border-slate-200',
                            };

                            $puedeEditar = auth()->user()->role_id == 1 || (int) $u->created_by === (int) auth()->id();
                        @endphp

                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-slate-500">
                                #{{ $u->id }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-slate-900">
                                    {{ $nombreUsuario }}
                                </div>
                                <div class="mt-0.5 text-xs text-slate-500">
                                    ID interno: {{ $u->id }}
                                </div>
                            </td>

                            <td class="px-6 py-4 text-sm text-slate-700">
                                {{ $u->email ?? '—' }}
                            </td>

                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold {{ $roleClasses }}">
                                    {{ $rolNombre }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    @if($puedeEditar)
                                        <a href="{{ route($routePrefix . '.usuarios.edit', $u->id) }}"
                                           class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                                            Editar
                                        </a>

                                        <form action="{{ route($routePrefix . '.usuarios.destroy', $u->id) }}"
                                              method="POST"
                                              onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 transition hover:bg-rose-100">
                                                Eliminar
                                            </button>
                                        </form>
                                    @else
                                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-500">
                                            Solo lectura
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center">
                                <div class="text-sm font-bold text-slate-900">
                                    No hay usuarios registrados
                                </div>

                                <p class="mt-1 text-sm text-slate-500">
                                    Crea un usuario nuevo o limpia los filtros aplicados.
                                </p>

                                <div class="mt-5">
                                    <a href="{{ route($routePrefix . '.usuarios.create') }}"
                                       class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                                        + Nuevo usuario
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($usuarios, 'links'))
            <div class="border-t border-slate-100 bg-white px-6 py-4">
                {{ $usuarios->links() }}
            </div>
        @endif
    </div>

    <div class="mt-4 flex items-center justify-end gap-4 pb-6">
        <a href="{{ route($routePrefix . '.dashboard') }}"
           class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            ← Volver al menú
        </a>
    </div>

</div>
@endsection