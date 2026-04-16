@extends('layouts.operador')

@section('title', 'Operador - Inicio')

@section('content')
<div class="w-full max-w-lg rounded-3xl border border-white/60 bg-white p-8 shadow-[0_24px_70px_rgba(15,23,42,0.28)] md:p-9">
    <div class="flex items-center gap-4 mb-8">
        <img src="{{ asset('img/logo1.png') }}" alt="Logo"
             class="h-14 w-14 rounded-xl object-contain bg-white border border-slate-200 p-1">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Bienvenido, {{ auth()->user()->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">Panel de operador</p>
        </div>
    </div>

    @php
        $menu = [
            ['route' => 'operador.bodegas.index', 'label' => 'Bodegas', 'icon' => '🏬', 'desc' => 'Inventario por bodega'],
            ['route' => 'operador.compras.index', 'label' => 'Compras', 'icon' => '🧾', 'desc' => 'Registro de compras'],
            ['route' => 'operador.asignaciones.create', 'label' => 'Asignaciones', 'icon' => '📦', 'desc' => 'Entrega de equipo'],
            ['route' => 'operador.operaciones.traslados.index', 'label' => 'Traslados', 'icon' => '🔁', 'desc' => 'Solicitudes entre bodegas'],
        ];
    @endphp

    <div class="space-y-3">
        @foreach($menu as $item)
            <a href="{{ route($item['route']) }}" class="group rounded-2xl border border-slate-200 bg-slate-50/90 p-4 hover:border-blue-300 hover:bg-white transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-xl text-blue-700">{{ $item['icon'] }}</span>
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
