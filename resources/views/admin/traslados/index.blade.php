@extends('layouts.admin')

@section('content')
@php
  $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';
@endphp
<div class="w-full px-4 py-8 md:px-6">
  <div class="max-w-6xl mx-auto">

    <div class="ui-page-header">
      <div>
        <p class="text-xs font-bold uppercase tracking-[0.25em] text-blue-100">Movimientos entre bodegas</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-white">Solicitudes de traslado</h1>
        <p class="mt-1 text-sm text-blue-100">
          @if($user->isEncargado())
            Bandeja de solicitudes para tu bodega (destino).
          @else
            Solicitudes creadas por ti.
          @endif
        </p>
      </div>

      <div class="flex items-center gap-2">
  <a href="{{ route('dashboard') }}"
     class="ui-btn-secondary">
    ← Volver
  </a>

        @if(!$user->isEncargado())
          <a href="{{ route($routePrefix . '.operaciones.traslados.create') }}"
             class="ui-btn-success">
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

    <div class="mt-6 ui-card p-5">
      <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-3">
          <label class="text-sm font-semibold text-slate-700">Estado</label>
          <select name="estado" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todos</option>
            <option value="PENDIENTE" {{ $estado === 'PENDIENTE' ? 'selected' : '' }}>PENDIENTE</option>
            <option value="APROBADO" {{ $estado === 'APROBADO' ? 'selected' : '' }}>APROBADO</option>
            <option value="RECHAZADO" {{ $estado === 'RECHAZADO' ? 'selected' : '' }}>RECHAZADO</option>
          </select>
        </div>

        <div class="md:col-span-4">
          <label class="text-sm font-semibold text-slate-700">Origen</label>
          <select name="origen" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todas</option>
            @foreach($bodegas as $b)
              <option value="{{ $b->id }}" {{ (string) $origen === (string) $b->id ? 'selected' : '' }}>{{ $b->nombre }}</option>
            @endforeach
          </select>
        </div>

        <div class="md:col-span-4">
          <label class="text-sm font-semibold text-slate-700">Destino</label>
          <select name="destino" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Todas</option>
            @foreach($bodegas as $b)
              <option value="{{ $b->id }}" {{ (string) $destino === (string) $b->id ? 'selected' : '' }}>{{ $b->nombre }}</option>
            @endforeach
          </select>
        </div>

        <div class="md:col-span-1">
          <button class="w-full ui-btn-download">
            Filtrar
          </button>
        </div>

        <div class="md:col-span-12 flex justify-end pt-2">
          <a href="{{ route($routePrefix . '.operaciones.traslados.index') }}"
             class="text-xs font-semibold text-blue-700 hover:underline">Limpiar filtros</a>
        </div>
      </form>
    </div>

    <div class="mt-6 ui-table">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
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
                    $estadoItem = $op->estado;
                    if ($estadoItem === 'PENDIENTE') {
                        $badge = 'bg-amber-50 text-amber-700';
                    } elseif ($estadoItem === 'APROBADO') {
                        $badge = 'bg-green-50 text-green-700';
                    } elseif ($estadoItem === 'RECHAZADO') {
                        $badge = 'bg-red-50 text-red-700';
                    } else {
                        $badge = 'bg-slate-100 text-slate-700';
                    }
                  @endphp
                  <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $badge }}">
                    {{ $op->estado }}
                  </span>
                </td>
                <td class="px-5 py-4 text-slate-700">
                  <span class="font-semibold">{{ optional($op->bodegaOrigen)->nombre ?? '—' }}</span>
                  <span class="text-slate-400">→</span>
                  <span class="font-semibold">{{ optional($op->bodegaDestino)->nombre ?? '—' }}</span>
                </td>
                <td class="px-5 py-4 text-slate-700">
                  <div class="font-semibold">{{ $op->created_at->format('d/m/Y H:i') }}</div>
                  <div class="text-xs text-slate-500">Por: {{ optional($op->creador)->name ?? '—' }}</div>
                </td>
                <td class="px-5 py-4 text-right">
                  <a href="{{ route($routePrefix . '.operaciones.traslados.show', $op) }}"
                     class="ui-btn-edit">
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
