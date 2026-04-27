@extends('layouts.admin')

@section('title', 'Editar Bodega')

@section('content')
<div class="ui-panel w-full max-w-xl p-8">

    <h1 class="text-2xl font-bold text-slate-800 mb-1">Editar bodega</h1>
    <p class="text-slate-500 text-sm mb-6">Actualiza la información de la bodega.</p>

    <form method="POST" action="{{ route('admin.bodegas.update', $bodega->id) }}" class="ui-form space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="text-sm font-semibold text-slate-700">Nombre</label>
            <input name="nombre" value="{{ old('nombre', $bodega->nombre) }}"
                   class="mt-1 w-full px-4 py-2"
                   required>
            @error('nombre') <div class="text-rose-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="text-sm font-semibold text-slate-700">Ubicación</label>
            <input name="ubicacion" value="{{ old('ubicacion', $bodega->ubicacion) }}"
                   class="mt-1 w-full px-4 py-2">
            @error('ubicacion') <div class="text-rose-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="text-sm font-semibold text-slate-700">Tipo</label>
            <select name="tipo"
                    class="mt-1 w-full px-4 py-2"
                    required>
                <option value="Principal" @selected(old('tipo', $bodega->tipo) === 'Principal')>Principal</option>
                <option value="Regional" @selected(old('tipo', $bodega->tipo) === 'Regional')>Regional</option>
            </select>
            @error('tipo') <div class="text-rose-600 text-sm mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="flex gap-2 justify-end pt-4">
            <a href="{{ route('admin.bodegas.index') }}"
               class="ui-btn-secondary">
                Cancelar
            </a>
            <button class="ui-btn-success">
                Guardar cambios
            </button>
        </div>
    </form>
</div>
@endsection
