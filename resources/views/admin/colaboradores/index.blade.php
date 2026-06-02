@extends('layouts.app')

@section('no_nav')
@endsection

@section('content')
@php
  $routePrefix = auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
@endphp

<div x-data="colaboradoresPage('{{ $routePrefix }}')" class="min-h-screen bg-slate-50">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">

    {{-- ENCABEZADO --}}
    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">
            Recursos Humanos
          </div>

          <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">
            Colaboradores
          </h1>

          <p class="mt-1 text-sm text-slate-500">
            Gestión de personal, expedientes administrativos, asignaciones y revisiones de RRHH.
          </p>
        </div>
      </div>

      {{-- BUSCADOR --}}
      <div class="bg-slate-50 px-6 py-5">
        <form method="GET">
          <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
            Buscar colaborador
          </label>

          <div class="flex flex-col gap-3 md:flex-row">
            <input name="q"
                   value="{{ request('q') }}"
                   class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Buscar por código, nombre o puesto..." />

            <button type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
              Buscar
            </button>

            @if(request('q'))
              <a href="{{ route($routePrefix . '.colaboradores.index') }}"
                 class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-100">
                Limpiar
              </a>
            @endif
          </div>
        </form>

        @if (session('success'))
          <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
          </div>
        @endif

        @if (session('error'))
          <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
            {{ session('error') }}
          </div>
        @endif
      </div>
    </div>

    {{-- DIRECTORIO --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

      {{-- CABECERA DIRECTORIO --}}
      <div class="border-b border-slate-200 bg-white px-6 py-5">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
          <div>
            <h2 class="text-lg font-bold text-slate-950">
              Directorio de colaboradores
            </h2>

            <p class="mt-1 text-sm text-slate-500">
              Consulta expedientes, selecciona colaboradores y descarga sus fichas técnicas.
            </p>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
            <div class="inline-flex w-fit rounded-xl border border-slate-200 bg-slate-100 p-1">
              <button type="button"
                      class="rounded-lg px-4 py-2 text-sm font-bold transition"
                      :class="tab==='activos' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                      @click="tab='activos'">
                Activos
                <span class="ml-1 text-xs" :class="tab==='activos' ? 'text-blue-100' : 'text-slate-400'">
                  ({{ $activos->total() }})
                </span>
              </button>

              <button type="button"
                      class="rounded-lg px-4 py-2 text-sm font-bold transition"
                      :class="tab==='inactivos' ? 'bg-slate-700 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                      @click="tab='inactivos'">
                Inactivos
                <span class="ml-1 text-xs" :class="tab==='inactivos' ? 'text-slate-200' : 'text-slate-400'">
                  ({{ $inactivos->total() }})
                </span>
              </button>
            </div>

            <a href="{{ route($routePrefix . '.colaboradores.create') }}"
               class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
              + Nuevo colaborador
            </a>
          </div>
        </div>
      </div>

      {{-- ACCIONES MASIVAS --}}
      <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-sm font-black text-white shadow-sm">
              <span x-text="selectedCount()"></span>
            </div>

            <div>
              <div class="text-sm font-bold text-slate-900">
                Selección para descarga
              </div>

              <p class="text-xs text-slate-500">
                Marca los colaboradores que deseas incluir en el CSV consolidado.
              </p>
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <button type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-100"
                    x-show="tab === 'activos'"
                    @click="seleccionarTodosVisibles(@js($activos->pluck('codigo')->values()))">
              Seleccionar visibles
            </button>

            <button type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-100"
                    x-show="tab === 'inactivos'"
                    x-cloak
                    @click="seleccionarTodosVisibles(@js($inactivos->pluck('codigo')->values()))">
              Seleccionar visibles
            </button>

            <button type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-4 py-2.5 text-xs font-bold text-red-700 shadow-sm transition hover:bg-red-50"
                    @click="limpiarSeleccion()">
              Limpiar selección
            </button>

            <form method="POST"
                  action="{{ route($routePrefix . '.colaboradores.fichas_tecnicas_masivas') }}"
                  x-ref="formFichasSeleccionadas"
                  @submit.prevent="descargarSeleccionados($refs.formFichasSeleccionadas)">
              @csrf

              <template x-for="codigo in selectedCodigos" :key="'codigo-selected-' + codigo">
                <input type="hidden" name="codigos[]" :value="codigo">
              </template>

              <button type="submit"
                      class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700"
                      :class="selectedCount() === 0 ? 'opacity-50 cursor-not-allowed' : ''">
                Descargar fichas seleccionadas
              </button>
            </form>
          </div>
        </div>
      </div>

      {{-- TABLA ACTIVOS --}}
      <div x-show="tab==='activos'">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 bg-white text-xs uppercase tracking-wide text-slate-500">
                <th class="px-5 py-3 text-left font-bold w-12">Sel.</th>
                <th class="px-5 py-3 text-left font-bold">Código</th>
                <th class="px-5 py-3 text-left font-bold">Colaborador</th>
                <th class="px-5 py-3 text-left font-bold">Puesto</th>
                <th class="px-5 py-3 text-center font-bold">Estado</th>
                <th class="px-5 py-3 text-right font-bold">Acciones</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
              @forelse($activos as $c)
                <tr class="transition hover:bg-blue-50/40">
                  <td class="px-5 py-4 align-middle">
                    <input type="checkbox"
                           class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                           :checked="isSelected('{{ $c->codigo }}')"
                           @change="toggleSeleccion('{{ $c->codigo }}')">
                  </td>

                  <td class="px-5 py-4 align-middle">
                    <span class="inline-flex rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                      {{ $c->codigo }}
                    </span>
                  </td>

                  <td class="px-5 py-4 align-middle">
                    <div class="font-bold text-slate-950">
                      {{ $c->nombre }}
                    </div>

                    <div class="mt-1 text-xs text-slate-500">
                      Expediente administrativo disponible
                    </div>
                  </td>

                  <td class="px-5 py-4 align-middle text-slate-700">
                    {{ $c->puesto }}
                  </td>

                  <td class="px-5 py-4 text-center align-middle">
                    <span class="inline-flex items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 px-4 py-1.5 text-xs font-bold text-emerald-700">
                      Activo
                    </span>
                  </td>

                  <td class="px-5 py-4 align-middle">
                    <div class="flex flex-wrap justify-end gap-2">
                      <button type="button"
                              class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700"
                              @click="openDetalle('{{ $c->codigo }}')">
                        Expediente
                      </button>

                      <a href="{{ route($routePrefix . '.colaboradores.edit', $c) }}"
                         class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                        Editar
                      </a>

                      <form method="POST"
                            action="{{ route($routePrefix . '.colaboradores.estado', $c) }}"
                            onsubmit="return confirm('¿Deseas inactivar este colaborador?')">
                        @csrf
                        @method('PATCH')

                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-xs font-bold text-red-700 transition hover:bg-red-100">
                          Inactivar
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="px-6 py-14 text-center">
                    <div class="text-sm font-bold text-slate-900">
                      No hay colaboradores activos.
                    </div>

                    <div class="mt-1 text-sm text-slate-500">
                      Cuando registres colaboradores activos aparecerán aquí.
                    </div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
          {{ $activos->links() }}
        </div>
      </div>

      {{-- TABLA INACTIVOS --}}
      <div x-show="tab==='inactivos'" x-cloak>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 bg-white text-xs uppercase tracking-wide text-slate-500">
                <th class="px-5 py-3 text-left font-bold w-12">Sel.</th>
                <th class="px-5 py-3 text-left font-bold">Código</th>
                <th class="px-5 py-3 text-left font-bold">Colaborador</th>
                <th class="px-5 py-3 text-left font-bold">Puesto</th>
                <th class="px-5 py-3 text-center font-bold">Estado</th>
                <th class="px-5 py-3 text-right font-bold">Acciones</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
              @forelse($inactivos as $c)
                <tr class="transition hover:bg-slate-50">
                  <td class="px-5 py-4 align-middle">
                    <input type="checkbox"
                           class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                           :checked="isSelected('{{ $c->codigo }}')"
                           @change="toggleSeleccion('{{ $c->codigo }}')">
                  </td>

                  <td class="px-5 py-4 align-middle">
                    <span class="inline-flex rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                      {{ $c->codigo }}
                    </span>
                  </td>

                  <td class="px-5 py-4 align-middle">
                    <div class="font-bold text-slate-950">
                      {{ $c->nombre }}
                    </div>

                    <div class="mt-1 text-xs text-slate-500">
                      Expediente administrativo disponible
                    </div>
                  </td>

                  <td class="px-5 py-4 align-middle text-slate-700">
                    {{ $c->puesto }}
                  </td>

                  <td class="px-5 py-4 text-center align-middle">
                    <span class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-slate-100 px-4 py-1.5 text-xs font-bold text-slate-700">
                      Inactivo
                    </span>
                  </td>

                  <td class="px-5 py-4 align-middle">
                    <div class="flex flex-wrap justify-end gap-2">
                      <button type="button"
                              class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700"
                              @click="openDetalle('{{ $c->codigo }}')">
                        Expediente
                      </button>

                      <a href="{{ route($routePrefix . '.colaboradores.edit', $c) }}"
                         class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                        Editar
                      </a>

                      <form method="POST"
                            action="{{ route($routePrefix . '.colaboradores.estado', $c) }}"
                            onsubmit="return confirm('¿Deseas activar este colaborador?')">
                        @csrf
                        @method('PATCH')

                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100">
                          Activar
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="px-6 py-14 text-center">
                    <div class="text-sm font-bold text-slate-900">
                      No hay colaboradores inactivos.
                    </div>

                    <div class="mt-1 text-sm text-slate-500">
                      Los colaboradores inactivos aparecerán aquí.
                    </div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
          {{ $inactivos->links() }}
        </div>
      </div>
    </div>

    {{-- VOLVER --}}
    <div class="mt-5 flex justify-end">
      <a href="{{ route($routePrefix . '.dashboard') }}"
         class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
        ← Volver al menú
      </a>
    </div>

  </div>

  @include('admin.colaboradores.Modals.expediente-modal')
  @include('admin.colaboradores.Modals.rrhh-revision-modal')
</div>

@include('admin.colaboradores.Modals.expediente-script')
@endsection