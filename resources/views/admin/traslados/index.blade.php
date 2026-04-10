@extends('layouts.admin')

@section('content')
@php($routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin')
<div class="min-h-[calc(100vh-120px)] px-6 py-10">
  <div class="max-w-6xl mx-auto">

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900">Solicitudes de traslado</h1>
        <p class="text-sm text-slate-500">
          @if($user->isEncargado())
            Bandeja de solicitudes para tu bodega (destino).
          @else
            Solicitudes creadas por ti.
          @endif
        </p>
      </div>

      <div class="flex items-center gap-2">
  <a href="{{ route($routePrefix . '.bodegas.index') }}"
     class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
    ← Volver
  </a>

        @if(!$user->isEncargado())
          <a href="{{ route($routePrefix . '.operaciones.traslados.create') }}"
             class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-white text-sm font-semibold hover:bg-blue-700">
            + Nueva solicitud
          </a>
        @endif
      </div>
    </div>

    @if(session('ok'))
      <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-green-800">
        {{ session('ok') }}
      </div>
    @endif
    @if(session('error'))
      <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
        {{ session('error') }}
      </div>
    @endif

    <div class="mt-6 rounded-2xl bg-white shadow-sm border border-slate-200 p-5">
      <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-3">
          <label class="text-sm font-semibold text-slate-700">Estado</label>
          <select name="estado" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos</option>
            <option value="PENDIENTE" @selected($estado==='PENDIENTE')>PENDIENTE</option>
            <option value="APROBADO" @selected($estado==='APROBADO')>APROBADO</option>
            <option value="RECHAZADO" @selected($estado==='RECHAZADO')>RECHAZADO</option>
          </select>
        </div>

        <div class="md:col-span-4">
          <label class="text-sm font-semibold text-slate-700">Origen</label>
          <select name="origen" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todas</option>
            @foreach($bodegas as $b)
              <option value="{{ $b->id }}" @selected((string)$origen === (string)$b->id)>{{ $b->nombre }}</option>
            @endforeach
          </select>
        </div>

        <div class="md:col-span-4">
          <label class="text-sm font-semibold text-slate-700">Destino</label>
          <select name="destino" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todas</option>
            @foreach($bodegas as $b)
              <option value="{{ $b->id }}" @selected((string)$destino === (string)$b->id)>{{ $b->nombre }}</option>
            @endforeach
          </select>
        </div>

        <div class="md:col-span-1">
          <button class="w-full rounded-xl bg-slate-900 px-4 py-2 text-white text-sm font-semibold hover:bg-slate-800">
            Filtrar
          </button>
        </div>

        <div class="md:col-span-12 flex justify-end pt-2">
          <a href="{{ route($routePrefix . '.operaciones.traslados.index') }}"
             class="text-xs font-semibold text-blue-700 hover:underline">Limpiar filtros</a>
        </div>
      </form>
    </div>

    <div class="mt-6 rounded-2xl bg-white shadow-sm border border-slate-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr>
              <th class="text-left px-5 py-3 font-semibold">#</th>
              <th class="text-left px-5 py-3 font-semibold">Estado</th>
              <th class="text-left px-5 py-3 font-semibold">Origen → Destino</th>
              <th class="text-left px-5 py-3 font-semibold">Creado</th>
              <th class="text-right px-5 py-3 font-semibold">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @forelse($operaciones as $op)
              <tr class="hover:bg-slate-50">
                <td class="px-5 py-4 font-semibold text-slate-900">#{{ $op->id }}</td>
                <td class="px-5 py-4">
                  @php
                    $estado = $op->estado;
                    $badge = match($estado) {
                      'PENDIENTE' => 'bg-amber-50 text-amber-700',
                      'APROBADO'  => 'bg-green-50 text-green-700',
                      'RECHAZADO' => 'bg-red-50 text-red-700',
                      default     => 'bg-slate-100 text-slate-700',
                    };
                  @endphp
                  <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $badge }}">
                    {{ $op->estado }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-700">
                  <span class="font-semibold">{{ $op->bodegaOrigen?->nombre ?? '—' }}</span>
                  <span class="text-slate-400">→</span>
                  <span class="font-semibold">{{ $op->bodegaDestino?->nombre ?? '—' }}</span>
                </td>
                <td class="px-5 py-4 text-slate-700">
                  <div class="font-semibold">{{ $op->created_at->format('d/m/Y H:i') }}</div>
                  <div class="text-xs text-slate-500">Por: {{ $op->creador?->name ?? '—' }}</div>
                </td>
                <td class="px-5 py-4 text-right">
                  <a href="{{ route($routePrefix . '.operaciones.traslados.show', $op) }}"
                     class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-white text-sm font-semibold hover:bg-blue-700">
                    Ver
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="px-5 py-16 text-center text-slate-500">
                  No hay solicitudes.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-5">
      {{ $operaciones->links() }}
    </div>

  </div>
</div>
@endsection
