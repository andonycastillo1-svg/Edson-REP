@extends('layouts.app')

@section('no_nav')
@endsection

@section('content')
<div class="min-h-screen bg-slate-50">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-900">Mis asignaciones</h1>
          <p class="text-sm text-slate-600">Agrupadas por colaborador, incluyendo asignaciones de fechas distintas.</p>
        </div>

        <div class="flex gap-2">
          <a href="{{ route($routePrefix . '.asignaciones.create') }}"
             class="rounded-xl bg-blue-600 px-4 py-2 text-white text-sm font-semibold hover:bg-blue-700">
            + Nueva asignación
          </a>
          <a href="{{ route('dashboard') }}"
             class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            ← Volver
          </a>
        </div>
      </div>

      <div class="px-6 py-4">
        @if(session('success'))
          <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm">
            {{ session('success') }}
          </div>
        @endif
        @if(session('error'))
          <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm">
            {{ session('error') }}
          </div>
        @endif

        @forelse($asignacionesPorColaborador as $grupo)
          <details class="mb-4 rounded-xl border border-slate-200" @if($loop->first) open @endif>
            <summary class="cursor-pointer list-none px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
              <div>
                <h2 class="font-semibold text-slate-800">{{ $grupo['colaborador_nombre'] }}</h2>
                <p class="text-xs text-slate-500">Código: {{ $grupo['colaborador_codigo'] }} · Asignaciones: {{ $grupo['asignaciones']->count() }}</p>
              </div>
              <span class="text-xs font-semibold rounded-full bg-blue-100 text-blue-700 px-3 py-1">Activas: {{ $grupo['total_activo'] }}</span>
            </summary>

            <div class="p-4">
              <div class="flex flex-wrap gap-2 mb-3 justify-end">
                <a href="{{ route($routePrefix . '.asignaciones.pdf', $grupo['colaborador_codigo']) }}"
                   class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                  Ver PDF / Imprimir
                </a>

                <form method="POST" action="{{ route($routePrefix . '.asignaciones.devolver_todo_colaborador', $grupo['colaborador_codigo']) }}" class="flex items-center gap-2">
                  @csrf
                  <input type="text" name="detalle_devolucion" placeholder="Motivo devolución total (opcional)"
                    class="text-xs w-64 border border-slate-200 rounded-lg p-2">
                  <button type="submit" class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                    onclick="return confirm('¿Devolver todo lo activo de este colaborador?')">
                    Devolver todo
                  </button>
                </form>
              </div>

              @php
                $bulkFormId = 'bulk-return-' . $grupo['colaborador_codigo'];
              @endphp
              <div class="overflow-x-auto rounded-xl border border-slate-200">
                  <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                      <tr>
                        <th class="px-3 py-2 text-left">Sel.</th>
                        <th class="px-3 py-2 text-left">Fecha</th>
                        <th class="px-3 py-2 text-left">Producto</th>
                        <th class="px-3 py-2 text-left">Bodega</th>
                        <th class="px-3 py-2 text-left">Estado</th>
                        <th class="px-3 py-2 text-right">Cant. activa</th>
                        <th class="px-3 py-2 text-left">Cantidad a devolver</th>
                        <th class="px-3 py-2 text-left">Documento firmado</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      @foreach($grupo['asignaciones'] as $a)
                        <tr>
                          <td class="px-3 py-2">
                            @if(($a->estado ?? 'Activa') === 'Activa' && (int) $a->cantidad_asignada > 0)
                              <input type="checkbox" class="selector" data-target="devolucion_{{ $a->id }}">
                            @endif
                          </td>
                          <td class="px-3 py-2">{{ $a->fecha ? date('d/m/Y', strtotime($a->fecha)) : '—' }}</td>
                          <td class="px-3 py-2">
                            <div>{{ optional($a->producto)->nombre ?? $a->producto_codigo }}</div>
                            <div class="text-xs text-slate-500">COD: {{ $a->producto_codigo }}</div>
                          </td>
                          <td class="px-3 py-2">{{ optional($a->bodega)->nombre ?? '—' }}</td>
                          <td class="px-3 py-2">{{ $a->estado ?? 'Activa' }}</td>
                          <td class="px-3 py-2 text-right font-semibold">{{ $a->cantidad_asignada }}</td>
                          <td class="px-3 py-2">
                            @if(($a->estado ?? 'Activa') === 'Activa' && (int) $a->cantidad_asignada > 0)
                              <input type="number"
                                     id="devolucion_{{ $a->id }}"
                                     name="devoluciones[{{ $a->id }}]"
                                     form="{{ $bulkFormId }}"
                                     min="1"
                                     max="{{ $a->cantidad_asignada }}"
                                     value="1"
                                     disabled
                                     class="text-xs w-24 border border-slate-200 rounded-lg p-1.5">
                            @else
                              <span class="text-xs text-slate-400">No aplica</span>
                            @endif
                          </td>
                          <td class="px-3 py-2">
                            <div class="flex flex-col md:flex-row md:items-center gap-2">
                              <form method="POST" action="{{ route($routePrefix . '.asignaciones.upload_pdf_firmado', $a) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                @csrf
                                <input type="file" name="pdf_firmado" accept=".pdf,.jpg,.jpeg,.png" required class="text-xs w-[170px] border border-slate-200 rounded-lg p-1">
                                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Subir firmado</button>
                              </form>

                              @if($a->pdf_firmado)
                                <a href="{{ asset('storage/' . $a->pdf_firmado) }}" target="_blank" class="text-xs text-emerald-700 hover:underline">Ver archivo</a>
                              @endif
                            </div>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>

                <form id="{{ $bulkFormId }}" method="POST" action="{{ route($routePrefix . '.asignaciones.devolver_lote') }}" class="flex flex-wrap gap-2 justify-end items-center mt-3">
                  @csrf
                  <input type="text" name="detalle_devolucion" form="{{ $bulkFormId }}" placeholder="Detalle de devolución múltiple (opcional)"
                    class="text-xs w-72 border border-slate-200 rounded-lg p-2">
                  <button type="submit" class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                    Devolver seleccionados
                  </button>
                </form>
            </div>
          </details>
        @empty
          <div class="rounded-xl border border-slate-200 p-6 text-center text-slate-500">Aún no tienes asignaciones registradas.</div>
        @endforelse

        <div class="mt-8 rounded-xl border border-slate-200">
          <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
            <h2 class="font-semibold text-slate-800">Historial de movimientos</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 text-slate-600">
                <tr>
                  <th class="px-4 py-2 text-left">Fecha</th>
                  <th class="px-4 py-2 text-left">Tipo</th>
                  <th class="px-4 py-2 text-left">Colaborador</th>
                  <th class="px-4 py-2 text-left">Cantidad</th>
                  <th class="px-4 py-2 text-left">Detalle</th>
                  <th class="px-4 py-2 text-left">Usuario</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                @forelse($movimientos as $m)
                  <tr>
                    <td class="px-4 py-2">{{ $m->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2">{{ $m->tipo }}</td>
                    <td class="px-4 py-2">{{ optional(optional($m->asignacion)->colaborador)->nombre ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $m->cantidad }}</td>
                    <td class="px-4 py-2">{{ $m->detalle ?? '—' }}</td>
                    <td class="px-4 py-2">{{ optional($m->user)->name ?? '—' }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-slate-500">Sin movimientos registrados.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.querySelectorAll('.selector').forEach((checkbox) => {
    checkbox.addEventListener('change', function () {
      const targetId = this.dataset.target;
      const input = document.getElementById(targetId);
      if (!input) return;
      input.disabled = !this.checked;
      if (!this.checked) input.value = 1;
    });
  });
</script>
@endsection
