@extends('layouts.app')

@section('no_nav')
@endsection

@section('content')
@php
  $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';
@endphp

@php
  $inventarioOptions = $inventarios->map(function ($i) {
      return [
          'producto_codigo' => $i->producto_codigo,
          'bodega_id' => $i->bodega_id,
          'label' => (optional($i->producto)->descripcion ?: optional($i->producto)->nombre) . ' - ' . $i->producto_codigo . ' (' . optional($i->bodega)->nombre . ') - Stock: ' . $i->cantidad,
      ];
  });
@endphp

<div class="min-h-screen bg-[#EAF4FF]">
  <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-xl shadow-slate-200/60">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
            <span class="h-2 w-2 rounded-full bg-blue-600"></span>
            Inventario / Asignaciones
          </div>

          <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
            Nueva asignación
          </h1>

          <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Registra la entrega de productos a un colaborador y genera la hoja de asignación correspondiente.
          </p>
        </div>

        <a href="{{ route($routePrefix . '.asignaciones.index') }}"
          class="inline-flex items-center justify-center rounded-2xl border border-blue-200 bg-blue-50 px-5 py-3 text-sm font-extrabold text-blue-700 shadow-sm transition hover:bg-blue-100 hover:shadow-md">
          Ver mis asignaciones
        </a>
      </div>
    </div>

    @if ($errors->any())
      <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-red-700 shadow-sm">
        <p class="text-sm font-bold">Corrige los siguientes errores:</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if(session('success'))
      <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800 shadow-sm">
        {{ session('success') }}
      </div>
    @endif

    @if(session('error'))
      <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700 shadow-sm">
        {{ session('error') }}
      </div>
    @endif

    <form method="POST"
      action="{{ route($routePrefix . '.asignaciones.store') }}"
      enctype="multipart/form-data"
      class="ui-form space-y-6">
      @csrf

      <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-600 text-sm font-black text-white">
              1
            </div>
            <div>
              <h2 class="text-base font-extrabold text-slate-900">
                Datos generales
              </h2>
              <p class="text-sm text-slate-500">
                Información principal de la asignación.
              </p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-bold text-slate-700">
              Colaborador
            </label>
            <select name="colaborador_codigo"
              data-searchable="true"
              data-search-placeholder="Buscar colaborador..."
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
              required>
              @foreach($colaboradores as $c)
                <option value="{{ $c->codigo }}" {{ old('colaborador_codigo') === $c->codigo ? 'selected' : '' }}>
                  {{ $c->nombre }}
                </option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">
              Fecha
            </label>
            <input type="date"
              name="fecha"
              value="{{ old('fecha', date('Y-m-d')) }}"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
              required>
          </div>

          <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">
              Aprobado por
            </label>
            <select name="aprobado_por"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
              required>
              @foreach($aprobadores as $a)
                <option value="{{ $a }}" {{ old('aprobado_por') === $a ? 'selected' : '' }}>
                  {{ $a }}
                </option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">
              Medio de solicitud
            </label>
            <select name="medio_solicitud"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
              required>
              <option value="WhatsApp" {{ old('medio_solicitud') === 'WhatsApp' ? 'selected' : '' }}>WhatsApp</option>
              <option value="Correo" {{ old('medio_solicitud') === 'Correo' ? 'selected' : '' }}>Correo</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-bold text-slate-700">
              Adjuntar evidencia
            </label>
            <input type="file"
              name="imagen"
              class="w-full cursor-pointer rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white hover:bg-slate-100">
            <p class="mt-2 text-xs text-slate-500">
              Puedes adjuntar imagen como respaldo de la solicitud.
            </p>
          </div>

          <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-bold text-slate-700">
              Observaciones
            </label>
            <textarea name="observaciones"
              rows="4"
              placeholder="Escribe una observación si aplica..."
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100">{{ old('observaciones') }}</textarea>
          </div>
        </div>
      </section>

      <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-600 text-sm font-black text-white">
                2
              </div>
              <div>
                <h2 class="text-base font-extrabold text-slate-900">
                  Productos a asignar
                </h2>
                <p class="text-sm text-slate-500">
                  Detalla producto, bodega, cantidad y reemplazo si aplica.
                </p>
              </div>
            </div>

            <button type="button"
              id="add-item"
              class="inline-flex items-center justify-center rounded-2xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-extrabold text-blue-700 shadow-sm transition hover:bg-blue-100 hover:shadow-md">
              + Agregar producto
            </button>
          </div>
        </div>

        <div class="p-6">
          <div id="items-wrapper" class="space-y-5"></div>

          <template id="item-template">
            <div class="item-row overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
              <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p class="text-sm font-extrabold text-slate-900">
                    Producto de la asignación
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    Selecciona el producto disponible y la bodega correspondiente.
                  </p>
                </div>

                <button type="button"
                  class="remove-item inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-extrabold text-red-700 transition hover:bg-red-100">
                  Quitar
                </button>
              </div>

              <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-12">
                <div class="md:col-span-7">
                  <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">
                    Producto
                  </label>
                  <select data-name="producto_codigo"
                    data-searchable="true"
                    data-search-placeholder="Buscar producto..."
                    class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required></select>
                </div>

                <div class="md:col-span-3">
                  <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">
                    Bodega
                  </label>
                  <select data-name="bodega_id"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required>
                    @foreach($bodegas as $b)
                      <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                    @endforeach
                  </select>
                </div>

                <div class="md:col-span-2">
                  <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">
                    Cantidad
                  </label>
                  <input type="number"
                    min="1"
                    data-name="cantidad_asignada"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    value="1"
                    required>
                </div>

                <div class="md:col-span-5">
                  <div class="h-full rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <label class="inline-flex items-center gap-3 text-sm font-bold text-amber-800">
                      <input type="checkbox"
                        value="1"
                        data-name="es_reemplazo"
                        class="rounded border-amber-300 text-blue-600 focus:ring-blue-500">
                      Reemplazo por daño
                    </label>
                    <p class="mt-1 text-xs text-amber-700">
                      Úsalo solo si reemplaza una asignación activa previa.
                    </p>
                  </div>
                </div>

                <div class="md:col-span-7">
                  <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">
                    Fecha daño/reemplazo
                  </label>
                  <input type="date"
                    data-name="fecha_dano"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                  <p class="mt-2 text-xs text-slate-500">
                    Opcional. Si queda vacío, se usará la fecha de asignación.
                  </p>
                </div>
              </div>
            </div>
          </template>
        </div>
      </section>

      <div class="rounded-3xl border border-slate-200 bg-white px-5 py-5 shadow-xl shadow-slate-200/60">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-xs text-slate-500">
            Revisa los datos antes de guardar. Esta acción registrará la asignación.
          </p>

          <button class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-600/25 transition hover:bg-blue-700 hover:shadow-xl">
            Guardar asignación
          </button>
        </div>
      </div>
    </form>

    <div class="mt-5 flex justify-end">
      <a href="{{ route('dashboard') }}"
        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-extrabold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:shadow-md">
        ← Volver
      </a>
    </div>
  </div>
</div>

<script>
  const inventarioOptions = @json($inventarioOptions);
  const itemsWrapper = document.getElementById('items-wrapper');
  const addItemBtn = document.getElementById('add-item');
  const template = document.getElementById('item-template');

  function buildProductoOptions(selectElement) {
    selectElement.innerHTML = '';
    inventarioOptions.forEach((item) => {
      const option = document.createElement('option');
      option.value = item.producto_codigo;
      option.textContent = item.label;
      selectElement.appendChild(option);
    });
  }

  function renumberRows() {
    const rows = itemsWrapper.querySelectorAll('.item-row');
    rows.forEach((row, index) => {
      row.querySelectorAll('[data-name]').forEach((field) => {
        field.name = `items[${index}][${field.dataset.name}]`;
        if (field.type === 'checkbox') {
          field.value = 1;
        }
      });
    });
  }

  function addItem(defaults = {}) {
    const clone = template.content.firstElementChild.cloneNode(true);
    const productoSelect = clone.querySelector('[data-name="producto_codigo"]');
    const bodegaSelect = clone.querySelector('[data-name="bodega_id"]');
    const cantidadInput = clone.querySelector('[data-name="cantidad_asignada"]');
    const reemplazoInput = clone.querySelector('[data-name="es_reemplazo"]');
    const fechaDanioInput = clone.querySelector('[data-name="fecha_dano"]');

    buildProductoOptions(productoSelect);

    if (window.enhanceSearchableSelect) {
      window.enhanceSearchableSelect(productoSelect);
    }

    if (defaults.producto_codigo) productoSelect.value = defaults.producto_codigo;
    if (defaults.bodega_id) bodegaSelect.value = defaults.bodega_id;
    if (defaults.cantidad_asignada) cantidadInput.value = defaults.cantidad_asignada;
    if (typeof defaults.es_reemplazo !== 'undefined') reemplazoInput.checked = defaults.es_reemplazo == 1 || defaults.es_reemplazo === true;
    if (defaults.fecha_dano) fechaDanioInput.value = defaults.fecha_dano;

    clone.querySelector('.remove-item').addEventListener('click', () => {
      clone.remove();

      if (!itemsWrapper.querySelector('.item-row')) {
        addItem();
      }

      renumberRows();
    });

    itemsWrapper.appendChild(clone);
    renumberRows();
  }

  addItemBtn.addEventListener('click', () => addItem());

  const oldItems = @json(old('items', []));

  if (oldItems.length) {
    oldItems.forEach((item) => addItem(item));
  } else {
    addItem();
  }
</script>
@endsection