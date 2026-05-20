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
          'label' => (optional($i->producto)->nombre ?: 'Producto') . ' [' . $i->producto_codigo . '] (' . optional($i->bodega)->nombre . ') - Stock: ' . $i->cantidad,
          'search' => mb_strtolower((optional($i->producto)->nombre ?: '') . ' ' . $i->producto_codigo),
      ];
  });
@endphp
<div class="min-h-screen bg-slate-50">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div class="ui-panel">
      <div class="px-6 py-5 border-b border-slate-200 flex flex-wrap justify-between items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-900">Nueva asignación</h1>
          <p class="text-sm text-slate-600">Asignar uno o varios productos a un colaborador.</p>
        </div>

        <div class="flex gap-2">
          <a href="{{ route('dashboard') }}"
            class="ui-btn-primary">
            ← Volver
          </a>
          <a href="{{ route($routePrefix . '.asignaciones.index') }}"
            class="ui-btn-secondary">
            Ver mis asignaciones
          </a>
        </div>
      </div>

      <div class="px-6 py-4">
        @if ($errors->any())
          <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
            <p class="font-semibold mb-1">Corrige los siguientes errores:</p>
            <ul class="list-disc pl-5">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

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

      <form method="POST" action="{{ route($routePrefix . '.asignaciones.store') }}"
        enctype="multipart/form-data"
        class="ui-form px-6 pb-6 space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-slate-700">Colaborador</label>
            <select name="colaborador_codigo"
              class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
              @foreach($colaboradores as $c)
                <option value="{{ $c->codigo }}" {{ old('colaborador_codigo') === $c->codigo ? 'selected' : '' }}>{{ $c->nombre }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="text-sm font-medium text-slate-700">Fecha</label>
            <input type="date" name="fecha"
              value="{{ old('fecha', date('Y-m-d')) }}"
              class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
          </div>

          <div>
            <label class="text-sm font-medium text-slate-700">Aprobado por</label>
            <select name="aprobado_por"
              class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
              @foreach($aprobadores as $a)
                <option value="{{ $a }}" {{ old('aprobado_por') === $a ? 'selected' : '' }}>{{ $a }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="text-sm font-medium text-slate-700">Medio de solicitud</label>
            <select name="medio_solicitud"
              class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
              <option value="WhatsApp" {{ old('medio_solicitud') === 'WhatsApp' ? 'selected' : '' }}>WhatsApp</option>
              <option value="Correo" {{ old('medio_solicitud') === 'Correo' ? 'selected' : '' }}>Correo</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="text-sm font-medium text-slate-700">Adjuntar evidencia</label>
            <input type="file" name="imagen" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2">
          </div>

          <div class="md:col-span-2">
            <label class="text-sm font-medium text-slate-700">Observaciones</label>
            <textarea name="observaciones" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2 focus:ring-2 focus:ring-blue-500">{{ old('observaciones') }}</textarea>
          </div>
        </div>

        <div class="rounded-xl border border-slate-200 p-4">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-slate-800">Productos a asignar</h2>
            <button type="button" id="add-item"
              class="rounded-lg border border-blue-300 bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-700 hover:bg-blue-100">
              + Agregar producto
            </button>
          </div>

          <div id="items-wrapper" class="space-y-3"></div>

          <template id="item-template">
            <div class="item-row grid grid-cols-1 md:grid-cols-12 gap-3 border border-slate-200 rounded-xl p-3">
              <div class="md:col-span-7">
                <label class="text-xs font-medium text-slate-600">Producto</label>
                <input type="text" data-role="producto-search" placeholder="Buscar por descripción del producto..."
                  class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <select data-name="producto_codigo" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required></select>
              </div>
              <div class="md:col-span-3">
                <label class="text-xs font-medium text-slate-600">Bodega</label>
                <select data-name="bodega_id" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                  @foreach($bodegas as $b)
                    <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                  @endforeach
                </select>
              </div>
              <div class="md:col-span-2">
                <label class="text-xs font-medium text-slate-600">Cantidad</label>
                <input type="number" min="1" data-name="cantidad_asignada" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" value="1" required>
              </div>
              <div class="md:col-span-6">
                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 mt-2">
                  <input type="checkbox" value="1" data-name="es_reemplazo" class="rounded border-slate-300 text-blue-600">
                  Reemplazo por daño
                </label>
              </div>
              <div class="md:col-span-6">
                <label class="text-xs font-medium text-slate-600">Fecha daño/reemplazo (opcional)</label>
                <input type="date" data-name="fecha_dano" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              </div>
              <div class="md:col-span-12 flex justify-end">
                <button type="button" class="remove-item rounded-lg border border-red-300 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">Quitar</button>
              </div>
            </div>
          </template>
        </div>

        <div class="flex justify-end">
          <button class="ui-btn-primary px-6">
            Guardar asignación
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const inventarioOptions = @json($inventarioOptions);
  const itemsWrapper = document.getElementById('items-wrapper');
  const addItemBtn = document.getElementById('add-item');
  const template = document.getElementById('item-template');

  function buildProductoOptions(selectElement, searchTerm = '') {
    selectElement.innerHTML = '';
    const normalized = searchTerm.trim().toLowerCase();
    const filtered = normalized
      ? inventarioOptions.filter((item) => item.search.includes(normalized))
      : inventarioOptions;

    filtered.forEach((item) => {
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
    const productoSearch = clone.querySelector('[data-role="producto-search"]');
    const bodegaSelect = clone.querySelector('[data-name="bodega_id"]');
    const cantidadInput = clone.querySelector('[data-name="cantidad_asignada"]');
    const reemplazoInput = clone.querySelector('[data-name="es_reemplazo"]');
    const fechaDanioInput = clone.querySelector('[data-name="fecha_dano"]');

    buildProductoOptions(productoSelect);
    productoSearch.addEventListener('input', () => buildProductoOptions(productoSelect, productoSearch.value));

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
