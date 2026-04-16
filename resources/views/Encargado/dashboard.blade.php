@extends('layouts.admin')

@section('title', 'Encargado - Inicio')

@section('content')
<div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-xl">
    <div class="border-b border-slate-200 px-6 py-5">
        <div class="flex items-center gap-4">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M12 2a5 5 0 015 5v2.38a7 7 0 11-10 0V7a5 5 0 015-5zm-3 9.5a5 5 0 106 0V7a3 3 0 10-6 0v4.5z"/></svg>
            </span>
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Bienvenido, {{ auth()->user()->name }}</h1>
                <p class="text-sm text-slate-500">Panel de encargado · validación y control de traslados</p>
            </div>
        </div>
    </div>

    <div class="p-6 grid gap-3">
        <a href="{{ route('admin.bodegas.index') }}" class="group rounded-xl border border-slate-200 bg-slate-50 p-4 hover:border-indigo-300 hover:bg-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">🏬</span>
                    <div>
                        <div class="font-semibold text-slate-800">Bodegas</div>
                        <div class="text-xs text-slate-500">Inventario por sede</div>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-indigo-600">›</span>
            </div>
        </a>

        <a href="{{ route('admin.operaciones.traslados.index') }}" class="group rounded-xl border border-slate-200 bg-slate-50 p-4 hover:border-indigo-300 hover:bg-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">🔁</span>
                    <div>
                        <div class="font-semibold text-slate-800">Traslados</div>
                        <div class="text-xs text-slate-500">Aprobaciones pendientes</div>
                    </div>
                </div>
                <span class="text-slate-400 group-hover:text-indigo-600">›</span>
            </div>
        </a>

        <div class="mt-3 border-t pt-5 text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm font-medium text-rose-600 hover:underline">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
@endsection
