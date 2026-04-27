@extends('layouts.admin')

@section('title', 'Admin - Inicio')

@section('content')
<div class="ui-panel w-full max-w-5xl p-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div class="flex items-center gap-4">
            <x-logo-image class="h-14 w-14 rounded-xl object-contain bg-white border border-slate-200 p-1" />
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">Bienvenido, {{ auth()->user()->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">Panel administrativo · selecciona una opción</p>
            </div>
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
               class="ui-card group p-4 hover:border-blue-300 hover:bg-blue-50/60">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-xl">{{ $item['icon'] }}</span>
                        <div>
                            <div class="font-semibold text-slate-800">{{ $item['label'] }}</div>
                            <div class="text-xs text-slate-500">{{ $item['desc'] }}</div>
                        </div>
                    </div>
                    <span class="text-slate-400 group-hover:text-blue-600">›</span>
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-8 pt-6 border-t text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="text-sm text-red-600 hover:underline">Cerrar sesión</button>
        </form>
    </div>
</div>
@endsection
