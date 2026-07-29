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
          'stock_tipo' => $i->stock_tipo ?? 'nuevo',
          'cantidad' => (int) $i->cantidad,
          'vida_restante_meses' => $i->vida_util_restante_meses,
          'label' => (optional($i->producto)->descripcion ?: optional($i->producto)->nombre) . ' - ' . $i->producto_codigo . ' (' . optional($i->bodega)->nombre . ') - ' . (($i->stock_tipo ?? 'nuevo') === 'usado' ? 'Usado reutilizable' : 'Nuevo') . ' - Stock: ' . $i->cantidad . (!is_null($i->vida_util_restante_meses) ? ' - Vida restante: ' . $i->vida_util_restante_meses . ' meses' : ''),
      ];
  });
@endphp

<div class="min-h-screen bg-[#EAF4FF]">
  <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <x-internal-navigation :back-url="route($routePrefix . '.asignaciones.index')" />

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
            <select id="colaborador_codigo" name="colaborador_codigo"
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
                <div class="md:col-span-5">
                  <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">
                    Producto
                  </label>
                  <select data-name="producto_codigo"
                    data-searchable="true"
                    data-search-placeholder="Buscar producto..."
                    class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required></select>
                </div>

                <div class="md:col-span-2">
                  <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Condición</label>
                  <select data-name="stock_tipo" class="stock-tipo w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm" required>
                    <option value="nuevo">Nuevo</option>
                    <option value="usado">Usado</option>
                  </select>
                  <p class="stock-warning mt-2 text-xs font-semibold text-amber-700"></p>
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

                <div class="replacement-panel hidden md:col-span-12 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                  <div class="replacement-status text-sm font-extrabold text-amber-900"></div>
                  <div class="replacement-summary mt-2 grid gap-1 text-xs text-amber-800 sm:grid-cols-2 lg:grid-cols-4"></div>

                  <div class="replacement-fields mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                      <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-600">Asignación anterior</label>
                      <select data-name="asignacion_anterior_id" class="previous-assignment w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm"></select>
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-600">Tipo de entrega</label>
                      <select data-name="modo_entrega" class="delivery-mode w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm">
                        <option value="reposicion">Reemplaza el anterior</option>
                        <option value="adicional">Entrega adicional</option>
                      </select>
                    </div>
                    <div class="requested-by-field">
                      <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-600">Solicitado por</label>
                      <input data-name="solicitado_por" maxlength="150" class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="reason-field lg:col-span-2">
                      <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-600">Motivo</label>
                      <select data-name="motivo_reposicion" class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm">
                        <option value="">Selecciona un motivo</option>
                        <option value="desgaste_prematuro">Desgaste prematuro</option>
                        <option value="dano_accidental">Daño accidental</option>
                        <option value="mal_uso">Mal uso</option>
                        <option value="perdida">Pérdida</option>
                        <option value="cambio_especificacion">Cambio de talla o especificación</option>
                        <option value="otro">Otro</option>
                      </select>
                    </div>
                    <div class="justification-field lg:col-span-2">
                      <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-600">Justificación</label>
                      <textarea data-name="justificacion_reposicion" maxlength="1000" rows="2" class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm"></textarea>
                    </div>
                  </div>
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

  </div>
</div>

<script>
  const inventarioOptions = @json($inventarioOptions);
  const itemsWrapper = document.getElementById('items-wrapper');
  const addItemBtn = document.getElementById('add-item');
  const template = document.getElementById('item-template');
  const colaboradorSelect = document.getElementById('colaborador_codigo');
  const activeAssignmentsUrl = @json(route($routePrefix . '.asignaciones.activas_producto'));

  function durationLabel(seconds) {
    if (seconds === null || typeof seconds === 'undefined') return 'No aplica';
    const days = Math.floor(Number(seconds) / 86400);
    const months = Math.floor(days / 30);
    return `${months} mes(es), ${days % 30} día(s)`;
  }

  function escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = String(value ?? '');
    return element.innerHTML;
  }

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
    const stockTipoInput = clone.querySelector('[data-name="stock_tipo"]');
    const stockWarning = clone.querySelector('.stock-warning');
    const reemplazoInput = clone.querySelector('[data-name="es_reemplazo"]');
    const fechaDanioInput = clone.querySelector('[data-name="fecha_dano"]');

    buildProductoOptions(productoSelect);

    if (window.enhanceSearchableSelect) {
      window.enhanceSearchableSelect(productoSelect);
    }

    if (defaults.producto_codigo) productoSelect.value = defaults.producto_codigo;
    if (defaults.bodega_id) bodegaSelect.value = defaults.bodega_id;
    if (defaults.cantidad_asignada) cantidadInput.value = defaults.cantidad_asignada;
    if (defaults.stock_tipo) stockTipoInput.value = defaults.stock_tipo;
    if (typeof defaults.es_reemplazo !== 'undefined') reemplazoInput.checked = defaults.es_reemplazo == 1 || defaults.es_reemplazo === true;
    if (defaults.fecha_dano) fechaDanioInput.value = defaults.fecha_dano;

    const updateStockMessage = () => {
      const selected = inventarioOptions.filter((item) => item.producto_codigo === productoSelect.value && String(item.bodega_id) === String(bodegaSelect.value));
      const usado = selected.find((item) => item.stock_tipo === 'usado');
      const actual = selected.find((item) => item.stock_tipo === stockTipoInput.value);
      cantidadInput.max = actual ? actual.cantidad : 0;
      if (stockTipoInput.value === 'nuevo' && usado && usado.cantidad > 0) {
        stockWarning.textContent = `Advertencia: existen ${usado.cantidad} unidades usadas disponibles.`;
      } else if (stockTipoInput.value === 'usado' && actual) {
        stockWarning.textContent = actual.vida_restante_meses === null
          ? 'Producto sin vida útil configurada.'
          : (actual.vida_restante_meses <= 0 ? 'Vida útil agotada; su asignación está permitida.' : `La siguiente unidad tiene aproximadamente ${actual.vida_restante_meses} meses restantes.`);
      } else {
        stockWarning.textContent = 'No hay existencias de esta condición en la bodega seleccionada.';
      }
    };
    [productoSelect, bodegaSelect, stockTipoInput].forEach((element) => element.addEventListener('change', updateStockMessage));
    updateStockMessage();

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
