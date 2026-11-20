@extends('layouts.app')


@section('content')
@php
  $routePrefix = auth()->user()->rolOperativo() == 2 ? 'operador' : 'admin';
@endphp

@php
  $inventarioOptions = $inventarios->map(function ($i) {
      return [
          'producto_codigo' => $i->producto_codigo,
          'bodega_id' => $i->bodega_id,
          'stock_tipo' => $i->stock_tipo,
          'label' => (optional($i->producto)->descripcion ?: optional($i->producto)->nombre) . ' - ' . $i->producto_codigo . ' (' . optional($i->bodega)->nombre . ') - Stock: ' . $i->cantidad,
      ];
  });
@endphp

<div class="min-h-screen bg-[#EAF4FF]">
  <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <x-internal-navigation :back-url="route($routePrefix . '.asignaciones.index')" />

    <div class="mb-6 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-xl shadow-slate-200/60">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
            Nueva asignación
          </h1>

          <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Registra la salida de bodega. Puedes confirmar la recepción y cargar la hoja firmada después.
          </p>
        </div>

        @rutaDisponible($routePrefix . '.asignaciones.index')
        <a href="{{ route($routePrefix . '.asignaciones.index') }}"
          class="inline-flex items-center justify-center rounded-2xl border border-blue-200 bg-blue-50 px-5 py-3 text-sm font-extrabold text-blue-700 shadow-sm transition hover:bg-blue-100 hover:shadow-md">
          Ver mis asignaciones
        </a>
        @endrutaDisponible
      </div>
    </div>

    <form method="POST"
      action="{{ route($routePrefix . '.asignaciones.store') }}"
      enctype="multipart/form-data"
      class="ui-form space-y-6">
      @csrf
      <input type="hidden" name="solicitud_id" value="{{ old('solicitud_id', (string) \Illuminate\Support\Str::uuid()) }}">

      <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
          <div class="flex items-center gap-3">
            <div>
              <h2 class="text-base font-extrabold text-slate-900">
                Datos generales
              </h2>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
          <div class="md:col-span-2">
            <label for="campo-colaborador-codigo" class="mb-1 block text-sm font-bold text-slate-700">
              Colaborador
            </label>
            <select id="campo-colaborador-codigo" name="colaborador_codigo"
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
            <label for="campo-fecha" class="mb-1 block text-sm font-bold text-slate-700">
              Fecha y hora de salida
            </label>
            <input id="campo-fecha" type="datetime-local"
              name="fecha"
              value="{{ old('fecha', now()->format('Y-m-d\TH:i')) }}"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
              required>
          </div>

          <div>
            <label for="campo-aprobado-por" class="mb-1 block text-sm font-bold text-slate-700">
              Aprobado por
            </label>
            <select id="campo-aprobado-por" name="aprobado_por"
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
            <label for="campo-medio-solicitud" class="mb-1 block text-sm font-bold text-slate-700">
              Medio de solicitud
            </label>
            <select id="campo-medio-solicitud" name="medio_solicitud"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
              required>
              <option value="WhatsApp" {{ old('medio_solicitud') === 'WhatsApp' ? 'selected' : '' }}>WhatsApp</option>
              <option value="Correo" {{ old('medio_solicitud') === 'Correo' ? 'selected' : '' }}>Correo</option>
            </select>
          </div>

          <div>
            <label for="campo-imagen" class="mb-1 block text-sm font-bold text-slate-700">
              Fotografía (opcional)
            </label>
            <input id="campo-imagen" type="file"
              name="imagen" accept="image/jpeg,image/png,image/webp"
              class="w-full cursor-pointer rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white hover:bg-slate-100">
            <p class="mt-2 text-xs text-slate-500">
              Formatos JPG, PNG o WebP. Máximo 10 MB.
            </p>
          </div>

          <fieldset class="grid gap-4 rounded-2xl border border-slate-200 p-4 md:col-span-2 sm:grid-cols-2">
            <legend class="px-1 font-bold text-slate-900">Entrega y recepción</legend>
            <label class="text-sm font-semibold">
              Modalidad de entrega
              <select name="modalidad" class="mt-1 block w-full rounded-xl border-slate-300">
                <option value="directa" @selected(old('modalidad', 'directa') === 'directa')>Directa al colaborador</option>
                <option value="tercero" @selected(old('modalidad') === 'tercero')>A través de un tercero</option>
              </select>
            </label>
            <label data-tercero class="text-sm font-semibold">
              Responsable del transporte
              <input class="mt-1 block w-full rounded-xl border-slate-300" name="tercero" value="{{ old('tercero') }}" maxlength="180">
            </label>
            <label class="text-sm font-semibold sm:col-span-2">
              <input type="checkbox" name="confirmar_recepcion" value="1" @checked(old('confirmar_recepcion'))> El colaborador ya confirmó la recepción
            </label>
            <div data-confirmacion class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
              <label class="text-sm font-semibold">
                Medio de confirmación
                <select name="medio_confirmacion" class="mt-1 block w-full rounded-xl border-slate-300">
                  <option value="">Selecciona un medio</option>
                  @foreach(\App\Models\EntregaInventario::MEDIOS as $medio => $nombre)
                    <option value="{{ $medio }}" @selected(old('medio_confirmacion') === $medio)>{{ $nombre }}</option>
                  @endforeach
                </select>
              </label>
              <label class="text-sm font-semibold">
                Fecha y hora de recepción (si se conocen)
                <input type="datetime-local" name="recibida_en" value="{{ old('recibida_en') }}"
                  class="mt-1 block w-full rounded-xl border-slate-300">
              </label>
            </div>
          </fieldset>

          <x-sistema.campos-nativos modulo="asignaciones" />
        </div>
      </section>

      <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <div>
                <h2 class="text-base font-extrabold text-slate-900">
                  Productos a asignar
                </h2>
                <p class="text-sm text-slate-500">
                  Se entrega una unidad por código. Si hay una custodia anterior, resuélvela aquí.
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
                  <label for="campo-producto-codigo" class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">
                    Producto
                  </label>
                  <select id="campo-producto-codigo" data-name="producto_codigo"
                    data-searchable="true"
                    data-search-placeholder="Buscar producto..."
                    class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required></select>
                </div>

                <div class="md:col-span-3">
                  <label for="campo-bodega-id" class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">
                    Bodega
                  </label>
                  <select id="campo-bodega-id" data-name="bodega_id"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required>
                    @foreach($bodegas as $b)
                      <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                    @endforeach
                  </select>
                </div>

                <div class="md:col-span-2">
                  <label for="campo-cantidad-asignada" class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">
                    Cantidad
                  </label>
                  <input id="campo-cantidad-asignada" type="number"
                    readonly
                    data-name="cantidad_asignada"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    value="1"
                    required>
                </div>

                <div class="md:col-span-5">
                  <label class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">
                    Tipo de stock
                    <select data-name="stock_tipo"
                      class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-800" required>
                      <option value="nuevo">Nuevo</option>
                      <option value="usado">Usado</option>
                    </select>
                  </label>
                </div>

                <div class="md:col-span-12" data-custodias aria-live="polite"></div>
              </div>
            </div>
          </template>
        </div>
      </section>

      <x-sistema.campos modulo="asignaciones" />

      <div class="rounded-3xl border border-slate-200 bg-white px-5 py-5 shadow-xl shadow-slate-200/60">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-blue-600/25 transition hover:bg-blue-700 hover:shadow-xl">
            Registrar salida
          </button>
        </div>
      </div>
    </form>

  </div>
</div>

@include('admin.asignaciones.condicion-script')
<template id="custodia-template">
  <fieldset class="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-4">
    <legend class="px-1 text-sm font-bold" data-custodia-titulo></legend>
    @include('admin.asignaciones.condicion')
  </fieldset>
</template>
<script>
  const formulario = document.querySelector('#items-wrapper').closest('form');
  const modalidad = formulario.elements.modalidad;
  const confirmar = formulario.elements.confirmar_recepcion;
  function actualizarEntrega() {
    const tercero = modalidad.value === 'tercero';
    formulario.querySelector('[data-tercero]').hidden = !tercero;
    formulario.elements.tercero.required = tercero;
    formulario.elements.tercero.disabled = !tercero;
    formulario.querySelector('[data-confirmacion]').style.display = confirmar.checked ? '' : 'none';
    formulario.elements.medio_confirmacion.required = confirmar.checked;
    formulario.elements.medio_confirmacion.disabled = !confirmar.checked;
    formulario.elements.recibida_en.disabled = !confirmar.checked;
  }
  modalidad.addEventListener('change', actualizarEntrega);
  confirmar.addEventListener('change', actualizarEntrega);
  actualizarEntrega();

  const inventarioOptions = @json($inventarioOptions);
  const itemsWrapper = document.getElementById('items-wrapper');
  const addItemBtn = document.getElementById('add-item');
  const template = document.getElementById('item-template');
  let siguienteFila = 0;

  const colaboradorSelect = document.querySelector('select[name="colaborador_codigo"]');
  const urlActivas = @json(route($routePrefix . '.asignaciones.activas_producto'));

  function buildProductoOptions(selectElement, bodega, tipo, selected) {
    const opciones = inventarioOptions
      .filter(item => String(item.bodega_id) === bodega && item.stock_tipo === tipo)
      .map(item => new Option(item.label, item.producto_codigo));
    selectElement.replaceChildren(new Option('Selecciona un producto', ''), ...opciones);
    selectElement.value = opciones.some(option => option.value === selected) ? selected : '';
    selectElement.dispatchEvent(new Event('options:updated'));
  }

  function actualizarValidacion() {
    const seleccionados = Array.from(itemsWrapper.querySelectorAll('[data-name="producto_codigo"]'));
    seleccionados.forEach(select => {
      const repetido = select.value && seleccionados.filter(otro => otro.value === select.value).length > 1;
      select.setCustomValidity(repetido ? 'Incluye cada código una sola vez.' : '');
    });
    formulario.querySelector('button[type="submit"]').disabled = Array.from(
      itemsWrapper.querySelectorAll('.item-row')
    ).some(fila => ['consultando', 'bloqueada'].includes(fila.dataset.estado));
  }

  function renumberRows() {
    itemsWrapper.querySelectorAll('.item-row').forEach((row, index) => {
      row.querySelectorAll('[data-name]').forEach(field => field.name = `items[${index}][${field.dataset.name}]`);
      row.querySelectorAll('[data-custodia-id] [data-campo]').forEach(field => {
        const id = field.closest('[data-custodia-id]').dataset.custodiaId;
        field.name = `items[${index}][resoluciones][${id}][${field.dataset.campo}]`;
      });
    });
    actualizarValidacion();
  }

  function addItem(defaults = {}) {
    const clone = template.content.firstElementChild.cloneNode(true);
    siguienteFila += 1;
    clone.querySelectorAll('label[for]').forEach(label => {
      const campo = clone.querySelector('#' + label.htmlFor);
      campo.id += '-' + siguienteFila;
      label.htmlFor = campo.id;
    });
    const producto = clone.querySelector('[data-name="producto_codigo"]');
    const bodega = clone.querySelector('[data-name="bodega_id"]');
    const tipo = clone.querySelector('[data-name="stock_tipo"]');
    const contenedor = clone.querySelector('[data-custodias]');
    if (defaults.bodega_id) bodega.value = defaults.bodega_id;
    if (defaults.stock_tipo) tipo.value = defaults.stock_tipo;

    let consulta;
    async function cargarAnteriores(resoluciones = {}) {
      consulta?.abort();
      contenedor.replaceChildren();
      clone.dataset.estado = '';
      if (!colaboradorSelect.value || !producto.value) {
        actualizarValidacion();
        return;
      }
      clone.dataset.estado = 'consultando';
      contenedor.textContent = 'Consultando custodias activas…';
      actualizarValidacion();
      consulta = new AbortController();
      const parametros = new URLSearchParams({ colaborador_codigo: colaboradorSelect.value, producto_codigo: producto.value });
      try {
        const respuesta = await fetch(urlActivas + '?' + parametros, {
          headers: { Accept: 'application/json' }, signal: consulta.signal,
        });
        if (!respuesta.ok) throw new Error();
        const { data, bloqueada } = await respuesta.json();
        contenedor.replaceChildren();
        clone.dataset.estado = bloqueada ? 'bloqueada' : '';
        if (bloqueada) contenedor.textContent = 'Una custodia anterior requiere atención de una persona autorizada.';
        data.forEach(item => {
          const ficha = document.querySelector('#custodia-template').content.firstElementChild.cloneNode(true);
          ficha.dataset.custodiaId = item.id;
          ficha.querySelector('[data-custodia-titulo]').textContent =
            `Custodia del ${item.fecha} · ${item.bodega ?? 'Bodega sin identificar'} · ${item.cantidad_activa} unidad(es)`;
          ficha.querySelectorAll('[data-campo]').forEach(campo => {
            campo.value = resoluciones[item.id]?.[campo.dataset.campo] ?? '';
          });
          configurarCondicion(ficha.querySelector('[data-condicion]'));
          contenedor.append(ficha);
        });
        renumberRows();
      } catch (error) {
        if (error.name === 'AbortError') return;
        clone.dataset.estado = 'bloqueada';
        const reintentar = document.createElement('button');
        reintentar.type = 'button';
        reintentar.className = 'underline';
        reintentar.textContent = 'Volver a consultar';
        reintentar.addEventListener('click', () => cargarAnteriores(resoluciones));
        contenedor.replaceChildren('La consulta de custodias falló. ', reintentar);
        actualizarValidacion();
      }
    }

    function actualizarProductos() {
      const seleccionado = producto.value;
      buildProductoOptions(producto, bodega.value, tipo.value, seleccionado);
      if (producto.value !== seleccionado) cargarAnteriores();
    }
    buildProductoOptions(producto, bodega.value, tipo.value, defaults.producto_codigo);
    producto.addEventListener('change', () => cargarAnteriores());
    bodega.addEventListener('change', actualizarProductos);
    tipo.addEventListener('change', actualizarProductos);
    const actualizarColaborador = () => cargarAnteriores();
    colaboradorSelect.addEventListener('change', actualizarColaborador);
    clone.querySelector('.remove-item').addEventListener('click', () => {
      consulta?.abort();
      colaboradorSelect.removeEventListener('change', actualizarColaborador);
      clone.remove();
      if (!itemsWrapper.children.length) addItem();
      renumberRows();
    });

    itemsWrapper.appendChild(clone);
    renumberRows();
    if (window.enhanceSearchableSelect) window.enhanceSearchableSelect(producto);
    cargarAnteriores(defaults.resoluciones);
  }

  addItemBtn.addEventListener('click', () => addItem());
  const oldItems = Object.values(@json(old('items', [])));
  if (oldItems.length) oldItems.forEach(addItem);
  else addItem();
</script>
@endsection
