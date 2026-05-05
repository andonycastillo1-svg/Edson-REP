@extends('layouts.admin')

@section('title', 'Coordinador - Inicio')

@section('content')
<div class="w-full max-w-lg bg-white/95 backdrop-blur rounded-3xl shadow-2xl p-8">
    <div class="flex items-center gap-4 mb-8">
        <img src="{{ asset('img/logo1.png') }}" alt="Logo"
             class="h-14 w-14 rounded-xl object-contain bg-white border border-slate-200 p-1">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">Bienvenido, {{ auth()->user()->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">Panel de coordinador</p>
        </div>
    </div>

    <div class="space-y-3">
        <a href="{{ route('admin.bodegas.index') }}"
           class="group rounded-2xl border border-slate-200 bg-white p-4 hover:border-blue-300 hover:bg-blue-50/60 transition">
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-xl">🏬</span>
                    <span>
                        <div class="font-semibold text-slate-800">Mi bodega</div>
                        <div class="text-xs text-slate-500">Consulta rápida de inventario</div>
                    </span>
                </span>
                <span class="text-slate-400 group-hover:text-blue-600">›</span>
            </div>
        </a>
    </div>

    <div class="mt-8 pt-6 border-t text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="text-sm text-red-600 hover:underline">Cerrar sesión</button>
        </form>
    </div>
</div>
@endsection
