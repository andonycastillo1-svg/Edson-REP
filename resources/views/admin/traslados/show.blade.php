@extends('layouts.admin')

@section('content')
@php
  $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';
@endphp
<div class="px-4 py-8 sm:px-6">
  <div class="max-w-5xl mx-auto">

    <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
      <div>
        <a href="{{ route($routePrefix . '.operaciones.traslados.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">← Volver a traslados</a>
        <h1 class="mt-2 text-3xl font-bold text-slate-950">Solicitud #{{ $operacion->id }}</h1>
        <p class="text-sm text-slate-600">Tipo: {{ $operacion->tipo }}</p>
      </div>

      <div class="flex items-center gap-2">
        <a href="{{ route($routePrefix . '.operaciones.traslados.hoja', $operacion) }}"
           class="ui-btn-download">
          🧾 Hoja
        </a>
      </div>
    </div>

    @if(session('ok'))
      <div class="mb-5 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-green-800">
        {{ session('ok') }}
      </div>
    @endif
    @if(session('error'))
      <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
        {{ session('error') }}
      </div>
    @endif

    <div class="ui-panel overflow-hidden">
      <div class="border-b border-slate-200 bg-gradient-to-r from-blue-900 to-indigo-800 px-6 py-5 text-white">
        <h2 class="text-lg font-bold">Detalle de traslado</h2>
        <p class="mt-1 text-sm text-blue-100">Revisa origen, destino, productos y decisión de aprobación.</p>
      </div>
      <div class="p-6">
      <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-sm text-slate-700">
          <span class="font-semibold">Origen:</span> {{ optional($operacion->bodegaOrigen)->nombre }}
          <span class="text-slate-400 mx-2">→</span>
          <span class="font-semibold">Destino:</span> {{ optional($operacion->bodegaDestino)->nombre }}
        </div>

        @php
          if ($operacion->estado === 'PENDIENTE') {
              $badge = 'bg-amber-50 text-amber-700';
          } elseif ($operacion->estado === 'APROBADO') {
              $badge = 'bg-green-50 text-green-700';
          } elseif ($operacion->estado === 'RECHAZADO') {
              $badge = 'bg-red-50 text-red-700';
          } else {
              $badge = 'bg-slate-100 text-slate-700';
          }
        @endphp
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $badge }}">
          {{ $operacion->estado }}
        </span>
      </div>

      <div class="mt-3 text-xs text-slate-500">
        Creado por: <span class="font-semibold text-slate-700">{{ optional($operacion->creador)->name ?? '—' }}</span>
        • {{ $operacion->created_at->format('d/m/Y H:i') }}
      </div>

      @if($operacion->observacion)
        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
          <span class="font-semibold">Observación:</span> {{ $operacion->observacion }}
        </div>
      @endif

      @if($operacion->estado === 'RECHAZADO' && $operacion->motivo_rechazo)
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          <span class="font-semibold">Motivo de rechazo:</span> {{ $operacion->motivo_rechazo }}
        </div>
      @endif

      <div class="mt-6 overflow-x-auto ui-table">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr>
              <th class="text-left px-4 py-3 font-semibold">Producto</th>
              <th class="text-right px-4 py-3 font-semibold">Cantidad</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @foreach($operacion->detalles as $d)
              <tr>
                <td class="px-4 py-3">
                  <div class="font-semibold text-slate-900">{{ $d->producto_codigo }}</div>
                  <div class="text-xs text-slate-500">{{ optional($d->producto)->nombre ?? '—' }}</div>
                </td>
                <td class="px-4 py-3 text-right font-bold">{{ $d->cantidad }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @if($puedeDecidir)
        <div class="mt-6 flex flex-col md:flex-row gap-3 md:items-center md:justify-end">

          <form method="POST" action="{{ route($routePrefix . '.operaciones.traslados.aprobar', $operacion) }}">
            @csrf
            <button class="ui-btn-success">
              ✅ Aprobar
            </button>
          </form>

          <form method="POST" action="{{ route($routePrefix . '.operaciones.traslados.rechazar', $operacion) }}"
                class="w-full md:max-w-md">
            @csrf
            <div class="flex gap-2">
              <input name="motivo_rechazo" required
                     class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                     placeholder="Motivo de rechazo...">
              <button class="ui-btn-danger">
                ❌ Rechazar
              </button>
            </div>
          </form>

        </div>
      @endif

      </div>
    </div>

  </div>
</div>
@endsection
