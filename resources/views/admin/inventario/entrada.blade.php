@extends('layouts.admin')

@section('title', 'Entrada de inventario')

@section('content')
@php
    $routePrefix = auth()->user()->rolOperativo() == 2 ? 'operador' : 'admin';
    $showRoute = \Illuminate\Support\Facades\Route::has($routePrefix . '.bodegas.show')
        ? $routePrefix . '.bodegas.show'
        : 'admin.bodegas.show';
    $storeRoute = \Illuminate\Support\Facades\Route::has($routePrefix . '.bodegas.entrada.store')
        ? $routePrefix . '.bodegas.entrada.store'
        : 'admin.bodegas.entrada.store';
@endphp

<div class="ui-panel w-full max-w-3xl p-6 md:p-8">
    <x-internal-navigation :back-url="route($showRoute, $bodega->id)" />
    <div class="mb-6 flex flex-col gap-4 border-b border-slate-100 pb-5 md:flex-row md:items-center md:justify-between">
        <div>
            <span class="ui-kicker">Entrada manual</span>
            <h1 class="ui-page-title mt-1   text-slate-900">Agregar al inventario</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $bodega->nombre }} · {{ $bodega->ubicacion ?? 'Sin ubicación' }}</p>
        </div>
    </div>

    <form enctype="multipart/form-data" method="POST" action="{{ route($storeRoute, $bodega->id) }}" class="ui-form space-y-5">
        @csrf

        <div>
            <label for="producto_codigo" class="mb-2 block text-sm font-semibold text-slate-700">Producto</label>
            <select id="producto_codigo" name="producto_codigo" required data-searchable="true" data-search-placeholder="Buscar producto por nombre o código..."
                    class="w-full px-4 py-3">
                <option value="">Selecciona un producto</option>
                @foreach($productos as $producto)
                    <option value="{{ $producto->codigo }}" {{ old('producto_codigo') === $producto->codigo ? 'selected' : '' }}>
                        {{ $producto->descripcion ?: $producto->nombre }} - {{ $producto->codigo }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="cantidad" class="mb-2 block text-sm font-semibold text-slate-700">Cantidad</label>
            <input id="cantidad" type="number" name="cantidad" min="1" required value="{{ old('cantidad', 1) }}"
                   class="w-full px-4 py-3">
        </div>

        <x-sistema.campos modulo="inventario" :obligar="false" />

        <div class="flex flex-col justify-end gap-3 pt-4 sm:flex-row">
            <a href="{{ route($showRoute, $bodega->id) }}"
               class="ui-btn-secondary">
                Cancelar
            </a>
            <button type="submit" class="ui-btn-success">
                Registrar entrada
            </button>
        </div>
    </form>
</div>
@endsection
