@extends('layouts.app')

@section('no_nav')
@endsection

@section('content')
@php
  $routePrefix = auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
@endphp

<div x-data="colaboradoresPage('{{ $routePrefix }}')" class="min-h-screen bg-slate-50">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div class="ui-panel">

      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between px-6 py-5 border-b border-slate-200">
        <div>
          <h1 class="text-xl font-semibold text-slate-900">Colaboradores</h1>
          <p class="text-sm text-slate-600">Gestión de personal y asignaciones.</p>
        </div>

        <div class="flex items-center gap-3">
          <a href="{{ route($routePrefix . '.colaboradores.create') }}"
             class="ui-btn-primary">
            <span class="text-base leading-none">+</span>
            Nuevo colaborador
          </a>
          <a href="{{ route($routePrefix . '.dashboard') }}"
             class="ui-btn-primary">
            ← Volver al menú
          </a>
        </div>
      </div>

      <div class="px-6 py-4 border-b border-slate-200">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <form method="GET" class="w-full md:max-w-md">
            <div class="relative">
              <input name="q" value="{{ request('q') }}"
                     class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 pr-10 text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                     placeholder="Buscar por código, nombre o puesto..." />
            </div>
          </form>

          <div class="text-sm text-slate-600">
            Activos: <span class="font-semibold text-slate-900">{{ $activos->total() }}</span> ·
            Inactivos: <span class="font-semibold text-slate-900">{{ $inactivos->total() }}</span>
          </div>
        </div>

        <div class="mt-4 inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
          <button class="rounded-lg px-4 py-2 text-sm font-semibold"
                  :class="tab==='activos' ? 'bg-white shadow text-slate-900' : 'text-slate-600 hover:text-slate-900'"
                  @click="tab='activos'">
            Activos
          </button>
          <button class="rounded-lg px-4 py-2 text-sm font-semibold"
                  :class="tab==='inactivos' ? 'bg-white shadow text-slate-900' : 'text-slate-600 hover:text-slate-900'"
                  @click="tab='inactivos'">
            Inactivos
          </button>
        </div>

        @if (session('success'))
          <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm">
            {{ session('success') }}
          </div>
        @endif
      </div>

      <div class="overflow-hidden">

        {{-- ACTIVOS --}}
        <div x-show="tab==='activos'">
          <table class="ui-table text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="px-6 py-3 text-left font-semibold">Código</th>
                <th class="px-6 py-3 text-left font-semibold">Nombre</th>
                <th class="px-6 py-3 text-left font-semibold">Puesto</th>
                <th class="px-6 py-3 text-left font-semibold">Estado</th>
                <th class="px-6 py-3 text-right font-semibold">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              @forelse($activos as $c)
                <tr class="hover:bg-slate-50">
                  <td class="px-6 py-4 font-semibold text-slate-900">{{ $c->codigo }}</td>
                  <td class="px-6 py-4 text-slate-900">{{ $c->nombre }}</td>
                  <td class="px-6 py-4 text-slate-600">{{ $c->puesto }}</td>
                  <td class="px-6 py-4">
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                      Activo
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex justify-end gap-2">

                      <button type="button"
                              class="rounded-xl bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"
                              @click="openDetalle('{{ $c->codigo }}')">
                        Detalle
                      </button>

                      <a href="{{ route($routePrefix . '.colaboradores.edit', $c) }}"
                         class="ui-btn-secondary text-xs px-3 py-2">
                        Editar
                      </a>

                      <form method="POST" action="{{ route($routePrefix . '.colaboradores.estado', $c) }}"
                            onsubmit="return confirm('¿Deseas inactivar este colaborador?')">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="ui-btn-danger text-xs px-3 py-2">
                          Inactivar
                        </button>
                      </form>

                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="px-6 py-10 text-center text-slate-600">No hay colaboradores activos.</td>
                </tr>
              @endforelse
            </tbody>
          </table>

          <div class="px-6 py-4 border-t border-slate-200">
            {{ $activos->links() }}
          </div>
        </div>

        {{-- INACTIVOS --}}
        <div x-show="tab==='inactivos'" x-cloak>
          <table class="ui-table text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="px-6 py-3 text-left font-semibold">Código</th>
                <th class="px-6 py-3 text-left font-semibold">Nombre</th>
                <th class="px-6 py-3 text-left font-semibold">Puesto</th>
                <th class="px-6 py-3 text-left font-semibold">Estado</th>
                <th class="px-6 py-3 text-right font-semibold">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              @forelse($inactivos as $c)
                <tr class="hover:bg-slate-50">
                  <td class="px-6 py-4 font-semibold text-slate-900">{{ $c->codigo }}</td>
                  <td class="px-6 py-4 text-slate-900">{{ $c->nombre }}</td>
                  <td class="px-6 py-4 text-slate-600">{{ $c->puesto }}</td>
                  <td class="px-6 py-4">
                    <span class="inline-flex items-center rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">
                      Inactivo
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex justify-end gap-2">

                      <button type="button"
                              class="rounded-xl bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"
                              @click="openDetalle('{{ $c->codigo }}')">
                        Detalle
                      </button>

                      <a href="{{ route($routePrefix . '.colaboradores.edit', $c) }}"
                         class="ui-btn-secondary text-xs px-3 py-2">
                        Editar
                      </a>

                      <form method="POST" action="{{ route($routePrefix . '.colaboradores.estado', $c) }}"
                            onsubmit="return confirm('¿Deseas activar este colaborador?')">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="ui-btn text-xs px-3 py-2 bg-emerald-600 text-white hover:bg-emerald-700">
                          Activar
                        </button>
                      </form>

                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="px-6 py-10 text-center text-slate-600">No hay colaboradores inactivos.</td>
                </tr>
              @endforelse
            </tbody>
          </table>

          <div class="px-6 py-4 border-t border-slate-200">
            {{ $inactivos->links() }}
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- MODAL DETALLE --}}
  <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="closeModal()"></div>

    <div class="relative w-full max-w-3xl rounded-2xl bg-white shadow-xl">

      <div class="flex items-start justify-between border-b border-slate-200 p-5">
        <div>
          <div class="text-sm text-slate-500">Detalle</div>
          <div class="text-lg font-semibold text-slate-900" x-text="detalle.nombre"></div>
          <div class="text-sm text-slate-600">
            <span x-text="detalle.codigo"></span>
          </div>
        </div>
        <button @click="closeModal()">✕</button>
      </div>

      <div class="p-5">

        <template x-if="asignaciones.length === 0">
          <div class="text-center text-slate-500">No tiene asignaciones</div>
        </template>

        <template x-if="asignaciones.length > 0">
          <div>
            <table class="ui-table text-sm">
              <thead class="bg-slate-100">
                <tr>
                  <th class="p-2 text-left">Producto</th>
                  <th class="p-2 text-left">Bodega</th>
                  <th class="p-2 text-left">Cantidad</th>
                  <th class="p-2 text-left">Costo</th>
                  <th class="p-2 text-left">Total</th>
                  <th class="p-2 text-left">F. Asignación</th>
                  <th class="p-2 text-left">F. Vencimiento</th>
                  <th class="p-2 text-left">Estado vida útil</th>
                </tr>
              </thead>
              <tbody>
                <template x-for="item in asignaciones">
                  <tr class="border-t">
                    <td class="p-2">
                      <div x-text="item.producto"></div>
                      <div class="text-xs text-slate-500" x-text="'COD: ' + (item.producto_codigo ?? '—')"></div>
                    </td>
                    <td class="p-2" x-text="item.bodega"></td>
                    <td class="p-2" x-text="item.cantidad"></td>
                    <td class="p-2" x-text="'Q ' + Number(item.costo_unitario).toFixed(2)"></td>
                    <td class="p-2 font-semibold" x-text="'Q ' + Number(item.total).toFixed(2)"></td>
                    <td class="p-2" x-text="item.fecha_asignacion ?? '—'"></td>
                    <td class="p-2" x-text="item.fecha_vencimiento ?? '—'"></td>
                    <td class="p-2">
                      <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                            :class="item.estado_vida_util === 'Vencido'
                              ? 'bg-red-100 text-red-700'
                              : (item.estado_vida_util === 'Vigente'
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-slate-100 text-slate-700')"
                            x-text="item.estado_vida_util"></span>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>

            <div class="mt-4 text-right font-semibold">
              Total: Q <span x-text="Number(totalGeneral).toFixed(2)"></span>
            </div>
          </div>
        </template>

      </div>

    </div>
  </div>

<script>
function colaboradoresPage(routePrefix) {
  return {
    tab: 'activos',
    modalOpen: false,
    detalle: {},
    asignaciones: [],
    totalGeneral: 0,
    routePrefix: routePrefix,

    async openDetalle(codigo) {
      this.modalOpen = true;

      const res = await fetch(`/${this.routePrefix}/colaboradores/${codigo}/detalle`);
      const data = await res.json();

      this.detalle = data.colaborador;
      this.asignaciones = data.asignaciones;
      this.totalGeneral = data.total_general;
    },

    closeModal() {
      this.modalOpen = false;
    }
  }
}
</script>
@endsection
