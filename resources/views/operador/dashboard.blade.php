@extends('layouts.operador')

@section('title', 'Operador - Inicio')

@section('content')
<div class="w-full max-w-5xl mx-auto px-4 py-6">

    {{-- Encabezado compacto --}}
    <div class="mb-4 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-xl border border-slate-200 bg-white flex items-center justify-center">
                    <img src="{{ asset('img/logo1.png') }}" alt="Logo" class="h-9 w-9 object-contain">
                </div>

                <div>
                    <h1 class="text-xl font-bold text-slate-900 leading-tight">
                        Panel operador
                    </h1>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Bienvenido, <span class="font-semibold text-slate-700">{{ auth()->user()->name }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-5 py-2">
            <p class="text-xs text-slate-600">
                Administra inventario, compras, asignaciones y traslados entre bodegas.
            </p>
        </div>
    </div>

    {{-- Módulos compactos --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

        <a href="{{ route('operador.bodegas.index') }}"
           class="group rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-blue-300 hover:bg-blue-50/40 transition">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                            <path d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-4.5v-6h-7v6H4a1 1 0 01-1-1v-9.5z"/>
                        </svg>
                    </span>

                    <div>
                        <h2 class="text-sm font-bold text-slate-900 leading-tight">Bodegas</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Inventario por bodega</p>
                    </div>
                </div>

                <span class="text-slate-300 group-hover:text-blue-600 transition text-lg">›</span>
            </div>
        </a>

        <a href="{{ route('operador.compras.index') }}"
           class="group rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-blue-300 hover:bg-blue-50/40 transition">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 text-sm">
                        🧾
                    </span>

                    <div>
                        <h2 class="text-sm font-bold text-slate-900 leading-tight">Compras</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Registro de compras</p>
                    </div>
                </div>

                <span class="text-slate-300 group-hover:text-blue-600 transition text-lg">›</span>
            </div>
        </a>

        <a href="{{ route('operador.asignaciones.create') }}"
           class="group rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-blue-300 hover:bg-blue-50/40 transition">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-700 text-sm">
                        📦
                    </span>

                    <div>
                        <h2 class="text-sm font-bold text-slate-900 leading-tight">Asignaciones</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Entrega de equipo</p>
                    </div>
                </div>

                <span class="text-slate-300 group-hover:text-blue-600 transition text-lg">›</span>
            </div>
        </a>

        <a href="{{ route('operador.operaciones.traslados.index') }}"
           class="group rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-blue-300 hover:bg-blue-50/40 transition">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 text-sm">
                        🔁
                    </span>

                    <div>
                        <h2 class="text-sm font-bold text-slate-900 leading-tight">Traslados</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Solicitudes entre bodegas</p>
                    </div>
                </div>

                <span class="text-slate-300 group-hover:text-blue-600 transition text-lg">›</span>
            </div>
        </a>

    </div>

    {{-- Sesión compacta --}}
    <div class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-xs text-slate-500">
                Sesión activa como operador.
            </p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>

</div>
@endsection