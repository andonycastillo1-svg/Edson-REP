@php($routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin')

<form action="{{ route($routePrefix . '.compras.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
@csrf

    {{-- DATOS PRINCIPALES --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Datos de la compra</h3>
                <p class="text-sm text-slate-500">Información general de la factura.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Fecha de compra</label>
                <input type="date" name="fecha_compra" value="{{ old('fecha_compra') }}"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required>
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">No. Factura</label>
                <input name="no_factura" value="{{ old('no_factura') }}"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required>
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Forma de pago</label>
                <select name="forma_pago"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required>
                    <option value="">Seleccione...</option>
                    <option value="Al contado" {{ old('forma_pago')=='Al contado' ? 'selected' : '' }}>Al contado</option>
                    <option value="Crédito 30 días" {{ old('forma_pago')=='Crédito 30 días' ? 'selected' : '' }}>Crédito 30 días</option>
                    <option value="Crédito 60 días" {{ old('forma_pago')=='Crédito 60 días' ? 'selected' : '' }}>Crédito 60 días</option>
                    <option value="Crédito 90 días" {{ old('forma_pago')=='Crédito 90 días' ? 'selected' : '' }}>Crédito 90 días</option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Proveedor</label>
                <select id="proveedor_tipo" name="proveedor_tipo"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required>
                    <option value="existente" {{ old('proveedor_tipo','existente')=='existente' ? 'selected' : '' }}>Existente</option>
                    <option value="nuevo" {{ old('proveedor_tipo')=='nuevo' ? 'selected' : '' }}>Nuevo</option>
                </select>
            </div>

            <div id="wrap_existente" class="md:col-span-2">
                <label class="mb-1 block text-sm font-semibold text-slate-700">Seleccionar proveedor</label>
                <select name="proveedor_id" id="proveedor_id"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                    <option value="">Seleccione...</option>
                    @foreach($proveedores as $p)
                        <option value="{{ $p->id }}" {{ old('proveedor_id') == $p->id ? 'selected' : '' }}>
                            {{ $p->nombre }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">
                    Para agregar proveedor nuevo, selecciona “Nuevo”.
                </p>
            </div>

            <div id="wrap_nuevo" class="hidden md:col-span-2">
                <label class="mb-1 block text-sm font-semibold text-slate-700">Nombre del proveedor</label>
                <input type="text" name="proveedor_nombre" id="proveedor_nombre"
                    value="{{ old('proveedor_nombre') }}"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    placeholder="Ej: LIBRERIA CENTRAL">
            </div>
        </div>
    </div>

    {{-- DATOS COMPLEMENTARIOS --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Datos complementarios</h3>
                <p class="text-sm text-slate-500">Información interna y documentos de respaldo.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Proyecto</label>
                <input name="proyecto" value="{{ old('proyecto') }}"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    placeholder="Opcional">
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Solicitado por</label>
                <input name="solicitado_por" value="{{ old('solicitado_por') }}"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    placeholder="Opcional">
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Autorizado por</label>
                <input name="autorizado_por" value="{{ old('autorizado_por') }}"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    placeholder="Opcional">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">PDF(s) Factura</label>
                <input type="file" name="pdfs[]" multiple accept="application/pdf"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                <p class="mt-1 text-xs text-slate-500">Puedes subir varios PDFs.</p>
            </div>
        </div>
    </div>

    {{-- DETALLE DE PRODUCTOS --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-col gap-3 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900">Detalle de productos</h3>
                <p class="text-sm text-slate-500">Agrega los productos incluidos en la factura.</p>
            </div>

            <button type="button" id="btnAddLinea"
                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
                + Agregar línea
            </button>
        </div>

        <datalist id="listaCategorias">
            @foreach(($categorias ?? collect()) as $cat)
                <option value="{{ $cat }}"></option>
            @endforeach

            <option value="Herramienta"></option>
            <option value="Equipo"></option>
            <option value="Refacciones"></option>
            <option value="Repuesto"></option>
            <option value="Insumo"></option>
            <option value="Vehículo"></option>
            <option value="Otros"></option>
        </datalist>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-bold uppercase tracking-wide text-slate-600">
                        <th class="px-3 py-3">Tipo</th>
                        <th class="px-3 py-3">Producto</th>
                        <th class="px-3 py-3">Cantidad</th>
                        <th class="px-3 py-3">Precio U.</th>
                        <th class="px-3 py-3 text-right">Valor</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody id="detalleBody" class="divide-y divide-slate-100 bg-white"></tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-end">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-3 text-right">
                <div class="text-sm font-medium text-slate-500">Total factura</div>
                <div class="text-2xl font-black text-slate-900" id="totalFactura">0.00</div>
            </div>
        </div>
    </div>

    {{-- ACCIONES --}}
    <div class="flex justify-end border-t border-slate-200 pt-4">
        <button class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
            Guardar compra
        </button>
    </div>

</form>

<template id="tplLinea">
    <tr class="align-top transition hover:bg-slate-50">
        <td class="w-36 px-3 py-3">
            <select name="producto_tipo[]"
                class="productoTipo w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                required>
                <option value="existente">Existente</option>
                <option value="nuevo">Nuevo</option>
            </select>
        </td>

        <td class="px-3 py-3">
            <div class="wrapExistente">
                <select name="producto_codigo[]"
                    class="productoCodigo w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                    <option value="">Seleccione...</option>
                    @foreach($productos as $pr)
                        <option value="{{ $pr->codigo }}">{{ $pr->nombre }} ({{ $pr->codigo }})</option>
                    @endforeach
                </select>
            </div>

            <div class="wrapNuevo hidden space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <input type="text" name="producto_nombre[]"
                        class="productoNombre w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        placeholder="Nombre producto nuevo">

                    <input type="text" name="producto_codigo_nuevo[]"
                        class="productoCodigoNuevo w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        placeholder="Código nuevo">

                    <input type="hidden" name="producto_unidad[]" class="productoUnidad" value="UND">

                    <input type="number" min="1" step="1" name="producto_vida_util_meses[]"
                        class="productoVidaUtil w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        placeholder="Vida útil (meses)">
                </div>

                <div>
                    <input type="text"
                        name="producto_categoria[]"
                        class="productoCategoria w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        list="listaCategorias"
                        placeholder="Categoría: selecciona o escribe una nueva">
                    <p class="mt-1 text-xs text-slate-500">
                        Puedes elegir una categoría existente o escribir una nueva.
                    </p>
                </div>

                <input type="text" name="producto_descripcion[]"
                    class="productoDesc w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    placeholder="Descripción (opcional)">
            </div>
        </td>

        <td class="w-28 px-3 py-3">
            <input type="number" min="1" value="1" name="cantidad[]"
                class="cantidad w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                required>
        </td>

        <td class="w-32 px-3 py-3">
            <input type="number" min="0" step="0.01" value="0.00" name="precio_unitario[]"
                class="precio w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                required>
        </td>

        <td class="w-24 px-3 py-3 text-right font-bold text-slate-900 valorLinea">0.00</td>

        <td class="w-12 px-3 py-3 text-center">
            <button type="button"
                class="btnDel inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 bg-white text-sm font-bold text-rose-600 transition hover:bg-rose-50">
                ×
            </button>
        </td>
    </tr>
</template>

<script>
(function () {
    const tipoProv = document.getElementById('proveedor_tipo');
    const wrapExistenteProv = document.getElementById('wrap_existente');
    const wrapNuevoProv = document.getElementById('wrap_nuevo');

    function toggleProveedor() {
        const isNuevo = tipoProv.value === 'nuevo';
        wrapExistenteProv.classList.toggle('hidden', isNuevo);
        wrapNuevoProv.classList.toggle('hidden', !isNuevo);
    }

    if (tipoProv) {
        tipoProv.addEventListener('change', toggleProveedor);
        toggleProveedor();
    }

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

    function toggleProductoLinea(tr) {
        const sel = tr.querySelector('.productoTipo');
        const wrapEx = tr.querySelector('.wrapExistente');
        const wrapNu = tr.querySelector('.wrapNuevo');

        const isNuevo = sel.value === 'nuevo';

        wrapEx.classList.toggle('hidden', isNuevo);
        wrapNu.classList.toggle('hidden', !isNuevo);

        const codigoExistente = tr.querySelector('.productoCodigo');
        const nombreNuevo = tr.querySelector('.productoNombre');
        const codigoNuevo = tr.querySelector('.productoCodigoNuevo');
        const categoriaNuevo = tr.querySelector('.productoCategoria');
        const vidaUtilNuevo = tr.querySelector('.productoVidaUtil');

        if (isNuevo) {
            codigoExistente.removeAttribute('required');
            nombreNuevo.setAttribute('required', 'required');
            codigoNuevo.setAttribute('required', 'required');
            categoriaNuevo.setAttribute('required', 'required');
            vidaUtilNuevo.setAttribute('required', 'required');
        } else {
            codigoExistente.setAttribute('required', 'required');
            nombreNuevo.removeAttribute('required');
            codigoNuevo.removeAttribute('required');
            categoriaNuevo.removeAttribute('required');
            vidaUtilNuevo.removeAttribute('required');
        }
    }

    window.addLinea = function () {
        const clone = tpl.content.cloneNode(true);
        const tr = clone.querySelector('tr');

        tr.querySelector('.productoTipo').addEventListener('change', () => toggleProductoLinea(tr));

        tr.addEventListener('input', (e) => {
            if (e.target.classList.contains('cantidad') || e.target.classList.contains('precio')) {
                recalcular();
            }
        });

        tr.querySelector('.btnDel').addEventListener('click', () => {
            tr.remove();
            recalcular();
        });

        body.appendChild(tr);
        toggleProductoLinea(tr);
        recalcular();
    }

    if (btnAdd) {
        btnAdd.addEventListener('click', () => window.addLinea());
    }

    window.addLinea();
})();
</script>