@extends((int) auth()->user()->role_id === 2 ? 'layouts.operador' : 'layouts.admin')

@section('title', 'Nueva asignación')

@section('content')
@php
    $prefix = (int) auth()->user()->role_id === 2 ? 'operador' : 'admin';

    $inputClass = 'w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-blue-200';
    $labelClass = 'mb-1 block text-xs font-bold text-slate-600';

    $inventarioOptions = $inventarios->map(fn ($i) => [
        'producto_codigo' => $i->producto_codigo,
        'bodega_id' => $i->bodega_id,
        'stock_tipo' => $i->stock_tipo ?? 'nuevo',
        'cantidad' => (int) $i->cantidad,
        'vida_restante_meses' => $i->vida_util_restante_meses,
        'label' => (optional($i->producto)->descripcion ?: optional($i->producto)->nombre)
            .' - '.$i->producto_codigo
            .' ('.optional($i->bodega)->nombre.')'
            .' - '.(($i->stock_tipo ?? 'nuevo') === 'usado' ? 'Usado reutilizable' : 'Nuevo')
            .' - Stock: '.$i->cantidad
            .(!is_null($i->vida_util_restante_meses)
                ? ' - Vida restante: '.$i->vida_util_restante_meses.' meses'
                : ''),
    ]);
@endphp

<div class="mx-auto w-full max-w-6xl">

    @if($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-bold">Corrige los siguientes errores:</p>

            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ route($prefix.'.asignaciones.store') }}"
        enctype="multipart/form-data"
        class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
    >
        @csrf

        {{-- Encabezado --}}
        <header class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
<<<<<<< HEAD
                <p class="text-xs font-bold uppercase tracking-[0.15em] text-blue-600">
                    Inventario / Asignaciones
                </p>

                <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">
                    Nueva asignación
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Registra la entrega de productos a un colaborador.
=======
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
>>>>>>> cf5628ec7affd0ee1a2a7529bfb3c956fd09ef6a
                </p>
            </div>

            <a
                href="{{ route($prefix.'.asignaciones.index') }}"
                class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
            >
                Ver asignaciones
            </a>
        </header>

        {{-- Datos generales --}}
        <section class="border-t border-slate-200">
            <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-3 sm:px-6">
                <h2 class="text-sm font-extrabold text-slate-900">
                    Datos generales
                </h2>
            </div>

            <div class="grid grid-cols-1 gap-4 p-5 sm:p-6 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="{{ $labelClass }}">Colaborador</label>

                    <select
                        name="colaborador_codigo"
                        data-searchable="true"
                        data-search-placeholder="Buscar colaborador..."
                        class="{{ $inputClass }}"
                        required
                    >
                        @foreach($colaboradores as $colaborador)
                            <option
                                value="{{ $colaborador->codigo }}"
                                @selected(old('colaborador_codigo') === $colaborador->codigo)
                            >
                                {{ $colaborador->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="fecha" class="{{ $labelClass }}">Fecha</label>

                    <input
                        id="fecha"
                        type="date"
                        name="fecha"
                        value="{{ old('fecha', date('Y-m-d')) }}"
                        class="{{ $inputClass }}"
                        required
                    >
                </div>

<<<<<<< HEAD
                <div>
                    <label for="aprobado_por" class="{{ $labelClass }}">
                        Aprobado por
                    </label>

                    <select
                        id="aprobado_por"
                        name="aprobado_por"
                        class="{{ $inputClass }}"
                        required
                    >
                        @foreach($aprobadores as $aprobador)
                            <option
                                value="{{ $aprobador }}"
                                @selected(old('aprobado_por') === $aprobador)
                            >
                                {{ $aprobador }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="medio_solicitud" class="{{ $labelClass }}">
                        Medio de solicitud
                    </label>

                    <select
                        id="medio_solicitud"
                        name="medio_solicitud"
                        class="{{ $inputClass }}"
                        required
                    >
                        <option value="WhatsApp" @selected(old('medio_solicitud') === 'WhatsApp')>
                            WhatsApp
                        </option>

                        <option value="Correo" @selected(old('medio_solicitud') === 'Correo')>
                            Correo
                        </option>
                    </select>
                </div>

                <div>
                    <label for="imagen" class="{{ $labelClass }}">
                        Evidencia opcional
                    </label>

                    <input
                        id="imagen"
                        type="file"
                        name="imagen"
                        accept="image/*"
                        class="block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-600
                               file:mr-3 file:border-0 file:border-r file:border-slate-200
                               file:bg-slate-50 file:px-4 file:py-2.5 file:text-sm
                               file:font-bold file:text-slate-700 hover:file:bg-slate-100"
                    >
                </div>

                <div class="md:col-span-2">
                    <label for="observaciones" class="{{ $labelClass }}">
                        Observaciones
                    </label>

                    <textarea
                        id="observaciones"
                        name="observaciones"
                        rows="2"
                        placeholder="Escribe una observación si aplica..."
                        class="{{ $inputClass }}"
                    >{{ old('observaciones') }}</textarea>
=======
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
>>>>>>> cf5628ec7affd0ee1a2a7529bfb3c956fd09ef6a
                </div>
            </div>
        </section>

        {{-- Productos --}}
        <section class="border-t border-slate-200">
            <div class="flex flex-col gap-3 bg-slate-50/70 px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900">
                        Productos a asignar
                    </h2>

                    <p class="mt-0.5 text-xs text-slate-500">
                        Selecciona producto, condición, bodega y cantidad.
                    </p>
                </div>

                <button
                    type="button"
                    id="add-item"
                    class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-white px-4 py-2 text-xs font-extrabold text-blue-700 hover:bg-blue-50"
                >
                    + Agregar producto
                </button>
            </div>

            <div id="items-wrapper" class="divide-y divide-slate-200"></div>
        </section>

        {{-- Acciones --}}
        <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <p class="text-xs text-slate-500">
                Revisa la información antes de guardar.
            </p>

            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                <a
                    href="{{ route($prefix.'.asignaciones.index') }}"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl !border-blue-600 !bg-blue-600 px-5 py-2.5 text-sm font-extrabold !text-white hover:!bg-blue-700"
                >
                    Guardar asignación
                </button>
            </div>
        </footer>
    </form>
</div>

{{-- Plantilla de producto --}}
<template id="item-template">
    <div class="item-row px-5 py-4 sm:px-6">
        <div class="mb-3 flex items-center justify-between">
            <p class="item-title text-sm font-bold text-slate-800">
                Producto
            </p>

            <button
                type="button"
                class="remove-item inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100"
            >
                Quitar
            </button>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
            <div class="md:col-span-5">
                <label class="{{ $labelClass }}">Producto</label>

                <select
                    data-name="producto_codigo"
                    data-searchable="true"
                    data-search-placeholder="Buscar producto..."
                    class="{{ $inputClass }}"
                    required
                ></select>
            </div>

            <div class="md:col-span-2">
                <label class="{{ $labelClass }}">Condición</label>

                <select
                    data-name="stock_tipo"
                    class="stock-tipo {{ $inputClass }}"
                    required
                >
                    <option value="nuevo">Nuevo</option>
                    <option value="usado">Usado</option>
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="{{ $labelClass }}">Bodega</label>

                <select
                    data-name="bodega_id"
                    class="{{ $inputClass }}"
                    required
                >
                    @foreach($bodegas as $bodega)
                        <option value="{{ $bodega->id }}">
                            {{ $bodega->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="{{ $labelClass }}">Cantidad</label>

                <input
                    type="number"
                    min="1"
                    value="1"
                    data-name="cantidad_asignada"
                    class="{{ $inputClass }}"
                    required
                >
            </div>

            <div class="md:col-span-5">
                <p class="stock-warning min-h-5 text-xs font-semibold text-amber-700"></p>
            </div>

            <div class="md:col-span-3">
                <label class="flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700">
                    <input
                        type="checkbox"
                        value="1"
                        data-name="es_reemplazo"
                        class="reemplazo-dano-checkbox"
                    >
                    Reemplazo por daño
                </label>
            </div>

            <div class="md:col-span-4">
                <input
                    type="date"
                    data-name="fecha_dano"
                    title="Fecha de daño o reemplazo"
                    class="{{ $inputClass }}"
                >
            </div>
        </div>
    </div>
</template>

<script>
<<<<<<< HEAD
(() => {
    const inventory = @json($inventarioOptions);
    const wrapper = document.getElementById('items-wrapper');
    const template = document.getElementById('item-template');
    const addButton = document.getElementById('add-item');
=======
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
>>>>>>> cf5628ec7affd0ee1a2a7529bfb3c956fd09ef6a

    const buildProducts = select => {
        select.innerHTML = '';

        inventory.forEach(item => {
            const option = document.createElement('option');
            option.value = item.producto_codigo;
            option.textContent = item.label;
            select.appendChild(option);
        });
    };

    const reindex = () => {
        wrapper.querySelectorAll('.item-row').forEach((row, index) => {
            row.querySelector('.item-title').textContent = `Producto ${index + 1}`;

            row.querySelectorAll('[data-name]').forEach(field => {
                field.name = `items[${index}][${field.dataset.name}]`;

                if (field.type === 'checkbox') {
                    field.value = 1;
                }
            });
        });
    };

    const addItem = (defaults = {}) => {
        const row = template.content.cloneNode(true).querySelector('.item-row');
        const product = row.querySelector('[data-name="producto_codigo"]');
        const warehouse = row.querySelector('[data-name="bodega_id"]');
        const quantity = row.querySelector('[data-name="cantidad_asignada"]');
        const condition = row.querySelector('[data-name="stock_tipo"]');
        const warning = row.querySelector('.stock-warning');
        const replacement = row.querySelector('[data-name="es_reemplazo"]');
        const damageDate = row.querySelector('[data-name="fecha_dano"]');

        buildProducts(product);

        if (defaults.producto_codigo) product.value = defaults.producto_codigo;
        if (defaults.bodega_id) warehouse.value = defaults.bodega_id;
        if (defaults.cantidad_asignada) quantity.value = defaults.cantidad_asignada;
        if (defaults.stock_tipo) condition.value = defaults.stock_tipo;

        if (typeof defaults.es_reemplazo !== 'undefined') {
            replacement.checked =
                defaults.es_reemplazo == 1 ||
                defaults.es_reemplazo === true;
        }

        if (defaults.fecha_dano) {
            damageDate.value = defaults.fecha_dano;
        }

        const updateStock = () => {
            const selected = inventory.filter(item =>
                item.producto_codigo === product.value &&
                String(item.bodega_id) === String(warehouse.value)
            );

            const used = selected.find(item => item.stock_tipo === 'usado');
            const current = selected.find(item => item.stock_tipo === condition.value);

            quantity.max = current ? current.cantidad : 0;

            if (condition.value === 'nuevo' && used && used.cantidad > 0) {
                warning.textContent =
                    `Hay ${used.cantidad} unidades usadas disponibles.`;
            } else if (condition.value === 'usado' && current) {
                warning.textContent = current.vida_restante_meses === null
                    ? 'Producto sin vida útil configurada.'
                    : current.vida_restante_meses <= 0
                        ? 'Vida útil agotada; la asignación está permitida.'
                        : `Vida restante aproximada: ${current.vida_restante_meses} meses.`;
            } else {
                warning.textContent =
                    'No hay existencias de esta condición en la bodega seleccionada.';
            }
        };

        [product, warehouse, condition].forEach(field => {
            field.addEventListener('change', updateStock);
        });

        row.querySelector('.remove-item').addEventListener('click', () => {
            row.remove();

            if (!wrapper.querySelector('.item-row')) {
                addItem();
            }

            reindex();
        });

        wrapper.appendChild(row);

        if (window.enhanceSearchableSelect) {
            window.enhanceSearchableSelect(product);
        }

        updateStock();
        reindex();
    };

    addButton.addEventListener('click', () => addItem());

    const oldItems = @json(old('items', []));

    if (oldItems.length) {
        oldItems.forEach(addItem);
    } else {
        addItem();
    }
})();
</script>
@endsection