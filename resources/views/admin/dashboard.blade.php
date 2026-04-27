@extends('layouts.admin')

@section('title', 'Admin - Inicio')

@section('content')
<div class="ui-panel w-full max-w-5xl p-6 md:p-8">
    <div class="mb-8 rounded-3xl bg-gradient-to-r from-sky-700 via-blue-700 to-indigo-800 p-6 text-white shadow-lg">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-4">
            <x-logo-image class="h-20 w-20 rounded-2xl object-contain bg-white/95 border border-white/40 p-2 shadow-md" />
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-sky-100">Inventario de bodegas</p>
                <h1 class="mt-1 text-2xl font-bold md:text-3xl">Bienvenido, {{ auth()->user()->name }}</h1>
                <p class="mt-1 text-sm text-blue-100">Panel administrativo · selecciona una opción</p>
            </div>
        </div>
        <span class="inline-flex rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/25">Administrador</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @php
            $menu = [
                ['route' => 'admin.usuarios.index', 'label' => 'Usuarios', 'icon' => '👤', 'desc' => 'Gestiona accesos y roles'],
                ['route' => 'admin.bodegas.index', 'label' => 'Bodegas', 'icon' => '🏬', 'desc' => 'Consulta y administra bodegas'],
                ['route' => 'admin.compras.index', 'label' => 'Compras', 'icon' => '🧾', 'desc' => 'Registra y revisa compras'],
                ['route' => 'admin.colaboradores.index', 'label' => 'Colaboradores', 'icon' => '🧑‍🤝‍🧑', 'desc' => 'Personal y detalle de asignaciones'],
                ['route' => 'admin.asignaciones.create', 'label' => 'Asignaciones', 'icon' => '📦', 'desc' => 'Entrega de inventario'],
                ['route' => 'admin.vehiculos.index', 'label' => 'Vehículos', 'icon' => '🚚', 'desc' => 'Control de flota'],
            ];
        @endphp

        @foreach($menu as $item)
            <a href="{{ route($item['route']) }}"
               class="ui-card group p-5 hover:border-blue-300 hover:bg-blue-50/70">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-100 to-sky-50 text-2xl ring-1 ring-blue-100">{{ $item['icon'] }}</span>
                        <div>
                            <div class="font-bold text-slate-900">{{ $item['label'] }}</div>
                            <div class="text-sm text-slate-600">{{ $item['desc'] }}</div>
                        </div>
                    </div>
                    <span class="text-2xl text-slate-300 group-hover:text-blue-600">›</span>
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-8 pt-6 border-t text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="ui-btn-danger">Cerrar sesión</button>
        </form>
    </div>
</div>
@endsection
