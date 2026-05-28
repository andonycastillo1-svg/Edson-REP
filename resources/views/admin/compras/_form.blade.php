@php($routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin')

<form action="{{ route($routePrefix . '.compras.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
@csrf

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">Fecha de compra</label>
        <input type="date" name="fecha_compra" value="{{ old('fecha_compra') }}"
               class="w-full border rounded-lg p-2" required>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">No. Factura</label>
        <input name="no_factura" value="{{ old('no_factura') }}" class="w-full border rounded-lg p-2" required>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Forma de pago</label>
        <select name="forma_pago" class="w-full border rounded-lg p-2" required>
            <option value="">Seleccione...</option>
            <option value="Al contado" {{ old('forma_pago')=='Al contado' ? 'selected' : '' }}>Al contado</option>
            <option value="Crédito 30 días" {{ old('forma_pago')=='Crédito 30 días' ? 'selected' : '' }}>Crédito 30 días</option>
            <option value="Crédito 60 días" {{ old('forma_pago')=='Crédito 60 días' ? 'selected' : '' }}>Crédito 60 días</option>
            <option value="Crédito 90 días" {{ old('forma_pago')=='Crédito 90 días' ? 'selected' : '' }}>Crédito 90 días</option>
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Proveedor</label>
        <select id="proveedor_tipo" name="proveedor_tipo" class="w-full border rounded-lg p-2" required>
            <option value="existente" {{ old('proveedor_tipo','existente')=='existente' ? 'selected' : '' }}>Existente</option>
            <option value="nuevo" {{ old('proveedor_tipo')=='nuevo' ? 'selected' : '' }}>Nuevo</option>
        </select>
    </div>

    <div id="wrap_existente" class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Seleccionar proveedor</label>
        <select name="proveedor_id" id="proveedor_id" class="w-full border rounded-lg p-2">
            <option value="">Seleccione...</option>
            @foreach($proveedores as $p)
                <option value="{{ $p->id }}" {{ old('proveedor_id') == $p->id ? 'selected' : '' }}>
                    {{ $p->nombre }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">* Para agregar proveedor nuevo, elige “Proveedor: Nuevo”.</p>
    </div>

    <div id="wrap_nuevo" class="hidden md:col-span-2">
        <label class="block text-sm font-medium mb-1">Nombre del proveedor</label>
        <input type="text" name="proveedor_nombre" id="proveedor_nombre"
               value="{{ old('proveedor_nombre') }}"
               class="w-full border rounded-lg p-2"
               placeholder="Ej: LIBRERIA CENTRAL">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Proyecto</label>
        <input name="proyecto" value="{{ old('proyecto') }}" class="w-full border rounded-lg p-2" placeholder="Opcional">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Solicitado por</label>
        <input name="solicitado_por" value="{{ old('solicitado_por') }}" class="w-full border rounded-lg p-2" placeholder="Opcional">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Autorizado por</label>
        <input name="autorizado_por" value="{{ old('autorizado_por') }}" class="w-full border rounded-lg p-2" placeholder="Opcional">
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">A utilizarse</label>
        <input name="a_utilizarse" value="{{ old('a_utilizarse') }}" class="w-full border rounded-lg p-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">PDF(s) Factura</label>
        <input type="file" name="pdfs[]" multiple accept="application/pdf" class="w-full border rounded-lg p-2">
        <p class="text-xs text-gray-500 mt-1">Puedes subir varios PDFs.</p>
    </div>
</div>

<div class="border rounded-xl p-4 bg-gray-50">
    <div class="flex justify-between mb-3">
        <h3 class="font-semibold">Detalle de productos</h3>
        <button type="button" id="btnAddLinea"
                class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm">
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

    <table class="w-full text-sm">
        <thead>
            <tr>
                <th class="text-left">Tipo de registro</th>
                <th class="text-left">Producto</th>
                <th class="text-left">Cantidad</th>
                <th class="text-left">Precio U.</th>
                <th class="text-right">Valor</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="detalleBody"></tbody>
    </table>

    <div class="text-right mt-4">
        <div class="text-sm text-gray-600">Total factura</div>
        <div class="text-2xl font-bold" id="totalFactura">0.00</div>
    </div>
</div>

<div class="text-right">
    <button class="bg-blue-600 text-white px-5 py-2 rounded-lg">
        Guardar compra
    </button>
</div>

</form>

<template id="tplLinea">
    <tr class="border-t align-top">
        <td class="py-2 pr-2 w-40">
            <select name="producto_tipo[]" class="w-full border rounded-lg p-2 productoTipo" required>
                <option value="existente">Existente</option>
                <option value="nuevo">Nuevo</option>
            </select>
        </td>

        <td class="py-2 pr-2">
            <div class="wrapExistente">
                <select name="producto_codigo[]" class="w-full border rounded-lg p-2 productoCodigo">
                    <option value="">Seleccione...</option>
                    @foreach($productos as $pr)
                        <option value="{{ $pr->codigo }}">{{ $pr->nombre }} ({{ $pr->codigo }})</option>
                    @endforeach
                </select>
            </div>

            <div class="wrapNuevo hidden space-y-2">
                <input type="text" name="producto_nombre[]" class="w-full border rounded-lg p-2 productoNombre"
                       placeholder="Nombre producto nuevo">

                <input type="text" name="producto_codigo_nuevo[]" class="w-full border rounded-lg p-2 productoCodigoNuevo"
                       placeholder="Código nuevo">

                <input type="text" name="producto_unidad[]" class="w-full border rounded-lg p-2 productoUnidad"
                       placeholder="Unidad (ej: UND)">

                <div>
                    <input type="text"
                           name="producto_categoria[]"
                           class="w-full border rounded-lg p-2 productoCategoria"
                           list="listaCategorias"
                           placeholder="Categoría: selecciona o escribe una nueva">
                    <p class="text-xs text-gray-500 mt-1">
                        Puedes elegir una categoría existente o escribir una nueva.
                    </p>
                </div>

                <input type="number" min="1" step="1" name="producto_vida_util_meses[]"
                       class="w-full border rounded-lg p-2 productoVidaUtil"
                       placeholder="Vida útil (meses)">

                <input type="text" name="producto_descripcion[]" class="w-full border rounded-lg p-2 productoDesc"
                       placeholder="Descripción (opcional)">
            </div>
        </td>

        <td class="py-2 pr-2 w-28">
            <input type="number" min="1" value="1" name="cantidad[]"
                   class="w-full border rounded-lg p-2 cantidad" required>
        </td>

        <td class="py-2 pr-2 w-36">
            <input type="number" min="0" step="0.01" value="0.00" name="precio_unitario[]"
                   class="w-full border rounded-lg p-2 precio" required>
        </td>

        <td class="py-2 pr-2 text-right font-semibold valorLinea w-24">0.00</td>

        <td class="py-2 text-center w-12">
            <button type="button" class="px-2 py-1 rounded-lg border hover:bg-red-50 text-red-600 btnDel">x</button>
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