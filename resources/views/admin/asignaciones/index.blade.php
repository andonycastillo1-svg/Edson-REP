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
          <p class="text-sm text-slate-600">Historial de asignaciones realizadas por ti.</p>
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

        <div class="overflow-x-auto rounded-xl border border-slate-200">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                <th class="px-4 py-3 text-left font-semibold">Colaborador</th>
                <th class="px-4 py-3 text-left font-semibold">Producto</th>
                <th class="px-4 py-3 text-left font-semibold">Bodega</th>
                <th class="px-4 py-3 text-right font-semibold">Cantidad</th>
                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                <th class="px-4 py-3 text-right font-semibold">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              @forelse($asignaciones as $a)
                <tr class="hover:bg-slate-50">
                  <td class="px-4 py-3">{{ $a->fecha ? date('d/m/Y', strtotime($a->fecha)) : '—' }}</td>
                  <td class="px-4 py-3">
                    {{ $a->colaborador->nombre ?? '—' }}
                    <div class="text-xs text-slate-500">{{ $a->colaborador_codigo }}</div>
                  </td>
                  <td class="px-4 py-3">
                    <div>{{ optional($a->producto)->nombre ?? $a->producto_codigo }}</div>
                    <div class="text-xs text-slate-500">COD: {{ $a->producto_codigo }}</div>
                  </td>
                  <td class="px-4 py-3">{{ optional($a->bodega)->nombre ?? '—' }}</td>
                  <td class="px-4 py-3 text-right font-semibold">{{ $a->cantidad_asignada }}</td>
                  <td class="px-4 py-3">{{ $a->estado ?? 'Activa' }}</td>
                  <td class="px-4 py-3">
                    <div class="flex flex-col md:flex-row gap-2 justify-end">
                      <a href="{{ route($routePrefix . '.asignaciones.pdf', $a->colaborador_codigo) }}"
                         class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                        Ver PDF / Imprimir
                      </a>

                      <form method="POST"
                            action="{{ route($routePrefix . '.asignaciones.upload_pdf_firmado', $a) }}"
                            enctype="multipart/form-data"
                            class="flex items-center gap-2">
                        @csrf
                        <input type="file"
                               name="pdf_firmado"
                               accept=".pdf,.jpg,.jpeg,.png"
                               required
                               class="text-xs w-[180px] border border-slate-200 rounded-lg p-1">
                        <button type="submit"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                          Subir firmado
                        </button>
                      </form>

                      @if(($a->estado ?? 'Activa') === 'Activa')
                        <form method="POST"
                              action="{{ route($routePrefix . '.asignaciones.devolver', $a) }}"
                              class="flex items-center gap-2">
                          @csrf
                          <input type="number"
                                 name="cantidad_devuelta"
                                 min="1"
                                 max="{{ $a->cantidad_asignada }}"
                                 value="1"
                                 class="text-xs w-20 border border-slate-200 rounded-lg p-1"
                                 required>
                          <input type="text"
                                 name="detalle_devolucion"
                                 placeholder="Motivo (opcional)"
                                 class="text-xs w-[150px] border border-slate-200 rounded-lg p-1">
                          <button type="submit"
                                  class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                            Devolver
                          </button>
                        </form>
                      @endif
                    </div>

                    @if($a->pdf_firmado)
                      <div class="mt-2 text-right">
                        <a href="{{ asset('storage/' . $a->pdf_firmado) }}"
                           target="_blank"
                           class="text-xs text-emerald-700 hover:underline">
                          Ver documento firmado
                        </a>
                      </div>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                    Aún no tienes asignaciones registradas.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="mt-4">
          {{ $asignaciones->links() }}
        </div>

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
@endsection
