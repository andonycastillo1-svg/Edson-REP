@extends('layouts.app')

@section('content')
@php($routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin')
<div class="min-h-[calc(100vh-64px)] flex items-center justify-center p-6"
     style="background: linear-gradient(180deg, #36a2ff 0%, #2b7dff 100%);">

  <div class="w-full max-w-5xl bg-white/90 backdrop-blur rounded-2xl shadow-xl border border-white/40">
    <div class="p-6 md:p-8">

      <div class="flex items-start justify-between gap-4 mb-6">
        <div>
          <h2 class="text-2xl font-bold">Registrar compra</h2>
          <p class="text-sm text-gray-600">
            Al guardar, se crea la compra + entradas automáticas al inventario (bodega principal).
          </p>
        </div>
        <a href="{{ route($routePrefix . '.compras.index') }}"
           class="px-4 py-2 rounded-lg border bg-white hover:bg-gray-50 text-sm">
          ← Volver
        </a>
      </div>

      @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
          {{ session('error') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
          <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form action="{{ route($routePrefix . '.compras.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- ENCABEZADO --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de compra</label>
            <input type="date" name="fecha_compra" value="{{ old('fecha_compra') }}"
                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200"
                   required>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">No. Factura</label>
            <input name="no_factura" value="{{ old('no_factura') }}"
                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200"
                   placeholder="A-12345" required>
          </div>

          {{-- Forma de pago --}}
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Forma de pago</label>
            <select name="forma_pago"
                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200" required>
              <option value="">Seleccione...</option>
              <option value="Al contado" {{ old('forma_pago')=='Al contado' ? 'selected' : '' }}>Al contado</option>
              <option value="Crédito 30 días" {{ old('forma_pago')=='Crédito 30 días' ? 'selected' : '' }}>Crédito 30 días</option>
              <option value="Crédito 60 días" {{ old('forma_pago')=='Crédito 60 días' ? 'selected' : '' }}>Crédito 60 días</option>
              <option value="Crédito 90 días" {{ old('forma_pago')=='Crédito 90 días' ? 'selected' : '' }}>Crédito 90 días</option>
            </select>
          </div>

          {{-- Proveedor tipo --}}
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor</label>
            <select id="proveedor_tipo" name="proveedor_tipo"
                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200">
              <option value="existente" {{ old('proveedor_tipo','existente')=='existente' ? 'selected' : '' }}>Existente</option>
              <option value="nuevo" {{ old('proveedor_tipo')=='nuevo' ? 'selected' : '' }}>Nuevo</option>
            </select>
          </div>

          {{-- Proveedor existente --}}
          <div id="wrap_proveedor_existente" class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Seleccionar proveedor</label>
            <select name="proveedor_id"
                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200">
              <option value="">Seleccione...</option>
              @foreach($proveedores as $p)
                <option value="{{ $p->id }}" {{ old('proveedor_id') == $p->id ? 'selected' : '' }}>
                  {{ $p->nombre }}
                </option>
              @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">* Para agregar proveedores nuevos, usa “Proveedor: Nuevo”.</p>
          </div>

          {{-- Proveedor nuevo --}}
          <div id="wrap_proveedor_nuevo" class="hidden md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del proveedor</label>
            <input type="text" name="proveedor_nombre" value="{{ old('proveedor_nombre') }}"
                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200"
                   placeholder="Escriba el proveedor nuevo">
            <p class="text-xs text-gray-500 mt-1">Se creará automáticamente al guardar.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Proyecto</label>
            <input name="proyecto" value="{{ old('proyecto') }}"
                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200"
                   placeholder="Opcional">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Solicitado por</label>
            <input name="solicitado_por" value="{{ old('solicitado_por') }}"
                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200"
                   placeholder="Opcional">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Autorizado por</label>
            <input name="autorizado_por" value="{{ old('autorizado_por') }}"
                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200"
                   placeholder="Opcional">
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">A utilizarse</label>
            <input name="a_utilizarse" value="{{ old('a_utilizarse') }}"
                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200"
                   placeholder="Opcional">
          </div>

          {{-- PDFs --}}
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">PDF(s) Factura</label>
            <input type="file" name="pdfs[]" multiple accept="application/pdf"
                   class="w-full rounded-lg border-gray-300 bg-white p-2">
            <p class="text-xs text-gray-500 mt-1">Puedes subir varios PDFs (factura, respaldo, etc.).</p>
          </div>

        </div>

        {{-- DETALLE DE PRODUCTOS --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 md:p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-800">Detalle de productos</h3>
            <button type="button" id="btnAddLinea"
                    class="px-3 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-sm shadow">
              + Agregar línea
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-gray-600">
                  <th class="py-2 pr-3">Producto</th>
                  <th class="py-2 pr-3 w-28">Cantidad</th>
                  <th class="py-2 pr-3 w-36">Precio U.</th>
                  <th class="py-2 pr-3 w-32 text-right">Valor</th>
                  <th class="py-2 w-16 text-center">Quitar</th>
                </tr>
              </thead>
              <tbody id="detalleBody">
                {{-- Se llenará con JS --}}
              </tbody>
            </table>
          </div>

          <div class="flex justify-end mt-4">
            <div class="text-right">
              <div class="text-sm text-gray-600">Total factura</div>
              <div class="text-2xl font-bold" id="totalFactura">0.00</div>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <a href="{{ route($routePrefix . '.compras.index') }}"
             class="px-4 py-2 rounded-lg border bg-white hover:bg-gray-50">
            Cancelar
          </a>
          <button class="px-5 py-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 shadow">
            Guardar compra
          </button>
        </div>

      </form>

    </div>
  </div>
</div>

{{-- TEMPLATE DE LÍNEA --}}
<template id="tplLinea">
  <tr class="border-t">
    <td class="py-2 pr-3">
      <input type="hidden" name="producto_tipo[]" value="existente">
      <select name="producto_codigo[]" data-searchable="true" data-search-placeholder="Buscar producto por nombre o código..."
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200" required>
        <option value="">Seleccione...</option>
        @foreach($productos as $pr)
          <option value="{{ $pr->codigo }}">{{ $pr->descripcion ?: $pr->nombre }} - {{ $pr->codigo }}</option>
        @endforeach
      </select>
    </td>
    <td class="py-2 pr-3">
      <input type="number" min="1" value="1" name="cantidad[]"
             class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200 cantidad" required>
    </td>
    <td class="py-2 pr-3">
      <input type="number" min="0" step="0.01" value="0.00" name="precio_unitario[]"
             class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-200 precio" required>
    </td>
    <td class="py-2 pr-3 text-right font-semibold valorLinea">0.00</td>
    <td class="py-2 text-center">
      <button type="button" class="px-2 py-1 rounded-lg border hover:bg-red-50 text-red-600 btnDel">x</button>
    </td>
  </tr>
</template>

<script>
(function () {
  // Proveedor: existente / nuevo
  const tipo = document.getElementById('proveedor_tipo');
  const wrapExistente = document.getElementById('wrap_proveedor_existente');
  const wrapNuevo = document.getElementById('wrap_proveedor_nuevo');

  function toggleProveedor() {
    const isNuevo = tipo.value === 'nuevo';
    wrapExistente.classList.toggle('hidden', isNuevo);
    wrapNuevo.classList.toggle('hidden', !isNuevo);
  }
  tipo.addEventListener('change', toggleProveedor);
  toggleProveedor();

  // Detalle líneas
  const body = document.getElementById('detalleBody');
  const tpl = document.getElementById('tplLinea');
  const totalEl = document.getElementById('totalFactura');
  const btnAdd = document.getElementById('btnAddLinea');

  function money(n) {
    return (Math.round(n * 100) / 100).toFixed(2);
  }

  function recalcular() {
    let total = 0;
    body.querySelectorAll('tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.cantidad')?.value || 0);
      const prc = parseFloat(tr.querySelector('.precio')?.value || 0);
      const val = qty * prc;
      tr.querySelector('.valorLinea').textContent = money(val);
      total += val;
    });
    totalEl.textContent = money(total);
  }

  function addLinea() {
    const clone = tpl.content.cloneNode(true);
    const tr = clone.querySelector('tr');

    tr.addEventListener('input', (e) => {
      if (e.target.classList.contains('cantidad') || e.target.classList.contains('precio')) {
        recalcular();
      }
    });

    tr.querySelector('.btnDel').addEventListener('click', () => {
      tr.remove();
      recalcular();
    });

    body.appendChild(clone);
    const productoSelect = tr.querySelector('select[name="producto_codigo[]"]');
    if (productoSelect && window.enhanceSearchableSelect) window.enhanceSearchableSelect(productoSelect);
    recalcular();
  }

  btnAdd.addEventListener('click', addLinea);

  // Arranca con 1 línea
  addLinea();
})();
</script>
@endsection
