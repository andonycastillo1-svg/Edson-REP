@extends('layouts.admin')

@section('title', 'Entrada de inventario')

@section('content')
@php
    $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';
    $showRoute = \Illuminate\Support\Facades\Route::has($routePrefix . '.bodegas.show')
        ? $routePrefix . '.bodegas.show'
        : 'admin.bodegas.show';
    $storeRoute = \Illuminate\Support\Facades\Route::has($routePrefix . '.bodegas.entrada.store')
        ? $routePrefix . '.bodegas.entrada.store'
        : 'admin.bodegas.entrada.store';
@endphp

<div class="w-full max-w-3xl rounded-2xl bg-white/90 p-8 shadow-2xl">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Agregar al inventario</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $bodega->nombre }} · {{ $bodega->ubicacion ?? 'Sin ubicación' }}</p>
        </div>

        <a href="{{ route($showRoute, $bodega->id) }}"
           class="rounded-xl bg-slate-200 px-4 py-2 font-semibold text-slate-700 hover:bg-slate-300">
            Volver
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <div class="mb-1 font-semibold">Corrige los siguientes errores:</div>
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route($storeRoute, $bodega->id) }}" class="space-y-5">
        @csrf

        <div>
            <label for="producto_codigo" class="mb-2 block text-sm font-semibold text-slate-700">Producto</label>
            <select id="producto_codigo" name="producto_codigo" required
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Selecciona un producto</option>
                @foreach($productos as $producto)
                    <option value="{{ $producto->codigo }}" {{ old('producto_codigo') === $producto->codigo ? 'selected' : '' }}>
                        {{ $producto->codigo }} - {{ $producto->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="cantidad" class="mb-2 block text-sm font-semibold text-slate-700">Cantidad</label>
            <input id="cantidad" type="number" name="cantidad" min="1" required value="{{ old('cantidad', 1) }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="flex justify-end gap-3 pt-4">
            <a href="{{ route($showRoute, $bodega->id) }}"
               class="rounded-xl bg-slate-200 px-5 py-3 font-semibold text-slate-700 hover:bg-slate-300">
                Cancelar
            </a>
            <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-700">
                Registrar entrada
            </button>
        </div>
    </form>
</div>
@endsection
