@extends('layouts.app')

@section('no_nav')
@endsection

@section('content')
@php($routePrefix = auth()->user()->role_id == 4 ? 'rrhh' : 'admin')
<div class="min-h-screen bg-transparent">
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Nuevo colaborador</h1>
        <p class="text-sm text-slate-600">Registra un nuevo colaborador.</p>
      </div>
          <a href="{{ route($routePrefix . '.colaboradores.index') }}" class="ui-btn-secondary">
        ← Volver
      </a>
    </div>

    <form method="POST" action="{{ route($routePrefix . '.colaboradores.store') }}"
          class="ui-form mt-6 rounded-3xl border border-white/70 bg-white/95 p-6 shadow-xl shadow-sky-900/10">
      @csrf

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Código --}}
        <div>
          <label class="text-sm font-medium text-slate-700">Código</label>
          <input name="codigo" value="{{ old('codigo') }}"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500"
                 required />
          @error('codigo')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
          @enderror
        </div>

        {{-- Estado --}}
        <div>
          <label class="text-sm font-medium text-slate-700">Estado</label>
          <select name="estado"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500"
                  required>
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
          </select>
          @error('estado')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
          @enderror
        </div>

        {{-- Nombre --}}
        <div class="sm:col-span-2">
          <label class="text-sm font-medium text-slate-700">Nombre</label>
          <input name="nombre" value="{{ old('nombre') }}"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500"
                 required />
          @error('nombre')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
          @enderror
        </div>

        {{-- Puesto --}}
        <div class="sm:col-span-2">
          <label class="text-sm font-medium text-slate-700">Puesto</label>
          <input name="puesto" value="{{ old('puesto') }}"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500"
                 required />
          @error('puesto')
            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
          @enderror
        </div>

      </div>

      <div class="mt-6 flex items-center justify-end gap-2">
        <a href="{{ route($routePrefix . '.colaboradores.index') }}"
           class="ui-btn-secondary">
          Cancelar
        </a>

        <button type="submit"
                class="ui-btn-success">
          Guardar
        </button>
      </div>
    </form>

  </div>
</div>
@endsection
