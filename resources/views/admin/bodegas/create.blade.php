@extends('layouts.admin')


@section('title', 'Nueva Bodega')

@section('content')
<div class="w-full max-w-xl bg-white/90 rounded-2xl shadow-2xl p-8">
    <x-internal-navigation :back-url="route('admin.bodegas.index')" />

    <h1 class="text-2xl font-bold text-slate-800 mb-1">Crear bodega</h1>
    <p class="text-slate-500 text-sm mb-6">Agrega una nueva bodega al sistema.</p>

    <form method="POST" action="{{ route('admin.bodegas.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="text-sm font-semibold text-slate-700">Nombre</label>
            <input name="nombre" value="{{ old('nombre') }}"
                   class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                   required>
            @error('nombre') <div class="text-rose-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="text-sm font-semibold text-slate-700">Ubicación</label>
            <input name="ubicacion" value="{{ old('ubicacion') }}"
                   class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400">
            @error('ubicacion') <div class="text-rose-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="text-sm font-semibold text-slate-700">Tipo</label>
            <select name="tipo"
                    class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                    required>
                <option value="Principal" @selected(old('tipo') === 'Principal')>Principal</option>
                <option value="Regional" @selected(old('tipo') === 'Regional')>Regional</option>
            </select>
            @error('tipo') <div class="text-rose-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="flex gap-2 justify-end pt-4">
            <a href="{{ route('admin.bodegas.index') }}"
               class="px-4 py-2 rounded-xl bg-slate-700 text-white font-semibold shadow-sm transition hover:bg-slate-800">
                Cancelar
            </a>
            <button class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                Guardar
            </button>
        </div>
    </form>
</div>
@endsection
