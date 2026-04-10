@extends('layouts.app')

@section('no_nav')
@endsection

@section('content')
@php($routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin')
<div class="min-h-screen bg-slate-50">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

      {{-- Header --}}
      <div class="px-6 py-5 border-b border-slate-200 flex justify-between items-center">
        <div>
          <h1 class="text-xl font-semibold text-slate-900">Nueva asignación</h1>
          <p class="text-sm text-slate-600">Asignar productos a colaboradores.</p>
        </div>

        <a href="{{ route('dashboard') }}"
           class="rounded-xl bg-blue-600 px-4 py-2 text-white text-sm font-semibold hover:bg-blue-700">
          ← Volver
        </a>
      </div>

      {{-- Alerts --}}
      <div class="px-6 py-4">
        @if(session('success'))
          <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm">
            {{ session('success') }}
          </div>
        @endif

        @if(session('error'))
          <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
            {{ session('error') }}
          </div>
        @endif
      </div>

      {{-- Form --}}
      <form method="POST" action="{{ route($routePrefix . '.asignaciones.store') }}"
            enctype="multipart/form-data"
            class="px-6 pb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        @csrf

        {{-- Producto --}}
        <div class="md:col-span-2">
          <label class="text-sm font-medium text-slate-700">Producto</label>
          <select name="producto_codigo"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500">
            @foreach($inventarios as $i)
              <option value="{{ $i->producto_codigo }}">
                {{ $i->producto->nombre }} ({{ $i->bodega->nombre }}) - Stock: {{ $i->cantidad }}
              </option>
            @endforeach
          </select>
        </div>

        {{-- Colaborador --}}
        <div>
          <label class="text-sm font-medium text-slate-700">Colaborador</label>
          <select name="colaborador_codigo"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500">
            @foreach($colaboradores as $c)
              <option value="{{ $c->codigo }}">{{ $c->nombre }}</option>
            @endforeach
          </select>
        </div>

        {{-- Bodega --}}
        <div>
          <label class="text-sm font-medium text-slate-700">Bodega</label>
          <select name="bodega_id"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500">
            @foreach($bodegas as $b)
              <option value="{{ $b->id }}">{{ $b->nombre }}</option>
            @endforeach
          </select>
        </div>

        {{-- Cantidad --}}
        <div>
          <label class="text-sm font-medium text-slate-700">Cantidad</label>
          <input type="number" name="cantidad_asignada"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500">
        </div>

        {{-- Fecha --}}
        <div>
          <label class="text-sm font-medium text-slate-700">Fecha</label>
          <input type="date" name="fecha"
                 value="{{ date('Y-m-d') }}"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500">
        </div>

        {{-- Aprobado por --}}
        <div>
          <label class="text-sm font-medium text-slate-700">Aprobado por</label>
          <select name="aprobado_por"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500">
            @foreach($aprobadores as $a)
              <option value="{{ $a }}">{{ $a }}</option>
            @endforeach
          </select>
        </div>

        {{-- Medio --}}
        <div>
          <label class="text-sm font-medium text-slate-700">Medio de solicitud</label>
          <select name="medio_solicitud"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500">
            <option>WhatsApp</option>
            <option>Correo</option>
          </select>
        </div>

        {{-- Imagen --}}
        <div class="md:col-span-2">
          <label class="text-sm font-medium text-slate-700">Adjuntar evidencia</label>
          <input type="file" name="imagen"
                 class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2">
        </div>

        {{-- Observaciones --}}
        <div class="md:col-span-2">
          <label class="text-sm font-medium text-slate-700">Observaciones</label>
          <textarea name="observaciones"
                    class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
        </div>

        {{-- Botón --}}
        <div class="md:col-span-2 flex justify-end">
          <button class="rounded-xl bg-blue-600 px-6 py-2 text-white text-sm font-semibold hover:bg-blue-700">
            Guardar asignación
          </button>
        </div>

      </form>

    </div>
  </div>
</div>
@endsection
