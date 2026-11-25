@extends('layouts.app')


@section('content')
@php($routePrefix = auth()->user()->rolOperativo() == 4 ? 'rrhh' : 'admin')
<div class="min-h-screen bg-slate-50">
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <x-internal-navigation :back-url="route($routePrefix . '.colaboradores.index')" />

    <div class="flex items-center justify-between">
      <div>
        <h1 class="ui-page-title text-slate-900">Editar colaborador</h1>
        <p class="text-sm text-slate-600">Actualiza datos y estado.</p>
      </div>
    </div>

    <form enctype="multipart/form-data" method="POST" action="{{ route($routePrefix . '.colaboradores.update', $colaborador) }}"
          class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      @csrf
      @method('PUT')

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Código --}}
        <div>
          <label for="campo-codigo" class="text-sm font-medium text-slate-700">Código</label>
          <input id="campo-codigo" name="codigo"
                 value="{{ old('codigo', $colaborador->codigo) }}"
                 class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2 focus:outline-none"
                 readonly />
          @error('codigo')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
          @enderror
        </div>

        {{-- Estado --}}
        <div>
          <label for="campo-estado" class="text-sm font-medium text-slate-700">Estado</label>
          <select id="campo-estado" name="estado"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500">
            <option value="Activo" {{ old('estado', $colaborador->estado) === 'Activo' ? 'selected' : '' }}>Activo</option>
            <option value="Inactivo" {{ old('estado', $colaborador->estado) === 'Inactivo' ? 'selected' : '' }}>Inactivo</option>
          </select>
          @error('estado')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
          @enderror
        </div>

        {{-- Nombre --}}
        <div class="sm:col-span-2">
          <label for="campo-nombre" class="text-sm font-medium text-slate-700">Nombre</label>
          <input id="campo-nombre" name="nombre"
                 value="{{ old('nombre', $colaborador->nombre) }}"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500" />
          @error('nombre')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
          @enderror
        </div>

        {{-- Puesto --}}
        <div class="sm:col-span-2">
          <label for="campo-puesto" class="text-sm font-medium text-slate-700">Puesto</label>
          <input id="campo-puesto" name="puesto"
                 value="{{ old('puesto', $colaborador->puesto) }}"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500" />
          @error('puesto')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
          @enderror
        </div>

        <div class="space-y-1">
          <label for="region-id" class="block text-sm font-medium text-slate-700">Región</label>
          <select id="region-id" name="region_id" class="w-full rounded-xl border-slate-300">
            <option value="">Sin región asignada</option>
            @foreach($regiones as $region)
              <option value="{{ $region->id }}" @selected((int) old('region_id', $colaborador->region_id) === $region->id)>{{ $region->nombre }}</option>
            @endforeach
          </select>
        </div>

      </div>

      <x-sistema.campos modulo="colaboradores" :registro="$colaborador->codigo" />

      <div class="mt-6 flex items-center justify-end gap-2">
        <a href="{{ route($routePrefix . '.colaboradores.index') }}"
           class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
          Cancelar
        </a>

        <button type="submit"
                class="ui-btn-primary">
          Guardar cambios
        </button>
      </div>
    </form>

  </div>
</div>
@endsection
