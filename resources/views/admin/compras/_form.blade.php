@php($routePrefix = auth()->user()->rolOperativo() == 2 ? 'operador' : 'admin')

<form data-confirmar="¿Registrar esta factura y aplicar la entrada en la bodega central?" action="{{ route($routePrefix . '.compras.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
@csrf

    {{-- DATOS PRINCIPALES --}}
    <div class="ui-panel p-4 sm:p-5">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Datos de la compra</h3>
                <p class="text-sm text-slate-500">Información general de la factura.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label for="campo-fecha-compra" class="mb-1 block text-sm font-semibold text-slate-700">Fecha de compra</label>
                <input id="campo-fecha-compra" type="date" name="fecha_compra" value="{{ old('fecha_compra') }}"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required>
            </div>

            <div>
                <label for="campo-no-factura" class="mb-1 block text-sm font-semibold text-slate-700">No. Factura</label>
                <input id="campo-no-factura" name="no_factura" value="{{ old('no_factura') }}"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required>
            </div>

            <div>
                <label for="campo-forma-pago" class="mb-1 block text-sm font-semibold text-slate-700">Forma de pago</label>
                <select id="campo-forma-pago" name="forma_pago"
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
                <label for="proveedor_tipo" class="mb-1 block text-sm font-semibold text-slate-700">Proveedor</label>
                <select id="proveedor_tipo" name="proveedor_tipo"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    required>
                    <option value="existente" {{ old('proveedor_tipo','existente')=='existente' ? 'selected' : '' }}>Existente</option>
                    <option value="nuevo" {{ old('proveedor_tipo')=='nuevo' ? 'selected' : '' }}>Nuevo</option>
                </select>
            </div>

            <div id="wrap_existente" class="md:col-span-2">
                <label for="proveedor_id" class="mb-1 block text-sm font-semibold text-slate-700">Seleccionar proveedor</label>
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
                <label for="proveedor_nombre" class="mb-1 block text-sm font-semibold text-slate-700">Nombre del proveedor</label>
                <input type="text" name="proveedor_nombre" id="proveedor_nombre"
                    value="{{ old('proveedor_nombre') }}"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    placeholder="Ej: LIBRERIA CENTRAL">
            </div>
        </div>
    </div>

    {{-- DATOS COMPLEMENTARIOS --}}
    <div class="ui-panel p-4 sm:p-5">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-bold text-slate-900">Datos complementarios</h3>
                <p class="text-sm text-slate-500">Información interna y documentos de respaldo.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <label class="text-sm font-semibold">
                Fecha real de ingreso (si se registró después)
                <input type="datetime-local" name="fecha_ingreso" value="{{ old('fecha_ingreso') }}"
                       class="mt-1 block w-full rounded-xl border-slate-300">
            </label>
            <div class="md:col-span-2">
                <x-sistema.campos-nativos modulo="compras" :ayudas="['autorizado_por' => 'Registra aquí a la persona que autorizó la compra.']" />
            </div>

            <div>
                <label for="campo-pdfs" class="mb-1 block text-sm font-semibold text-slate-700">Facturas y respaldos en PDF</label>
                <input id="campo-pdfs" type="file" name="pdfs[]" multiple accept="application/pdf"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                <p class="mt-1 text-xs text-slate-500">Adjunta hasta 10 archivos de 10 MB cada uno. Si el guardado falla, vuelve a adjuntarlos.</p>
            </div>
        </div>
    </div>

    {{-- DETALLE DE PRODUCTOS --}}
    <div class="ui-panel p-4 sm:p-5">
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

        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Líneas de compra">
            <table class="ui-table min-w-[740px]">
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
                <div class="text-xl font-bold text-slate-900" id="totalFactura">0.00</div>
            </div>
        </div>
    </div>

    @if($consolidados->isNotEmpty())
        <div class="ui-panel p-4 sm:p-5">
            <fieldset id="distribucion-compra" class="space-y-3">
                <legend class="font-bold text-slate-900">Distribución por consolidado</legend>
                <p class="text-sm text-slate-600">Si la factura corresponde a un consolidado, selecciónalo e indica cuántas unidades le corresponden.</p>
                @foreach($consolidados as $consolidado)
                    <div class="rounded-xl border border-slate-200 p-3" data-destino>
                        <label class="flex min-h-10 items-center gap-2 text-sm font-semibold">
                            <input type="checkbox" name="consolidados[]" value="{{ $consolidado->id }}"
                                @checked(in_array($consolidado->id, old('consolidados', (array) request('consolidados', []))))>
                            {{ $consolidado->nombre }}
                        </label>
                        <fieldset class="mt-2 space-y-2" data-reparto hidden disabled aria-label="Distribución para {{ $consolidado->nombre }}">
                            <p data-reparto-aviso class="text-sm text-slate-600">Agrega a la factura un producto pendiente de este consolidado.</p>
                            @foreach($pendientes->get($consolidado->id) as $linea)
                                <label class="items-center justify-between gap-3 text-sm" data-reparto-producto="{{ $linea['codigo'] }}" hidden>
                                    <span>{{ $linea['producto'] }} · {{ $linea['codigo'] }} · {{ $linea['pendiente'] }} pendientes</span>
                                    <input type="number" min="0" max="{{ $linea['pendiente'] }}" class="w-24 rounded-xl border-slate-300" disabled
                                        aria-label="Unidades de {{ $linea['codigo'] }} para {{ $consolidado->nombre }}"
                                        name="distribucion[{{ $consolidado->id }}][{{ bin2hex($linea['codigo']) }}]"
                                        value="{{ old('distribucion.'.$consolidado->id.'.'.bin2hex($linea['codigo'])) }}">
                                </label>
                            @endforeach
                        </fieldset>
                    </div>
                @endforeach
            </fieldset>
        </div>
    @endif

    <x-sistema.campos modulo="compras" />

    {{-- ACCIONES --}}
    <div class="flex justify-end border-t border-slate-200 pt-4">
        <button type="submit" class="ui-btn-primary">
            Guardar compra
        </button>
    </div>

</form>

<template id="tplLinea">
    <tr class="align-top transition hover:bg-slate-50">
        <td class="w-36 px-3 py-3">
            <select name="producto_tipo[]" aria-label="Tipo de producto"
                class="productoTipo w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                required>
                <option value="existente">Existente</option>
                <option value="nuevo">Nuevo</option>
            </select>
        </td>

        <td class="px-3 py-3">
            <div class="wrapExistente">
                <select name="producto_codigo[]" aria-label="Producto existente" data-searchable="true" data-search-placeholder="Buscar producto por nombre o código"
                    class="productoCodigo w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                    <option value="">Seleccione...</option>
                    @foreach($productos as $pr)
                        <option value="{{ $pr->codigo }}">{{ $pr->nombre }} ({{ $pr->codigo }})</option>
                    @endforeach
                </select>
            </div>

            <div class="wrapNuevo space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3" hidden>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <input type="text" name="producto_nombre[]" aria-label="Nombre del producto nuevo" maxlength="150"
                        class="productoNombre w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        placeholder="Nombre producto nuevo">

                    <input type="text" name="producto_codigo_nuevo[]" aria-label="Código del producto nuevo" maxlength="50"
                        class="productoCodigoNuevo w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        placeholder="Código nuevo">

                    <input type="hidden" name="producto_unidad[]" class="productoUnidad" value="UND">

                    <input type="number" min="1" step="1" name="producto_vida_util_meses[]" aria-label="Vida útil en meses" max="1200"
                        class="productoVidaUtil w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        placeholder="Vida útil (meses)">
                </div>

                <div>
                    <input type="text"
                        name="producto_categoria[]" aria-label="Categoría del producto nuevo" maxlength="50"
                        class="productoCategoria w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        list="listaCategorias"
                        placeholder="Categoría: selecciona o escribe una nueva">
                    <p class="mt-1 text-xs text-slate-500">
                        Puedes elegir una categoría existente o escribir una nueva.
                    </p>
                </div>

                <input type="text" name="producto_descripcion[]" aria-label="Descripción del producto nuevo" maxlength="10000"
                    class="productoDesc w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    placeholder="Descripción (opcional)">
                <x-sistema.campos modulo="productos" prefijo="campos_producto[INDICE_PRODUCTO]" :plantilla="true" />
            </div>
        </td>

        <td class="w-28 px-3 py-3">
            <input type="number" min="1" value="1" name="cantidad[]" aria-label="Cantidad" max="10000"
                class="cantidad w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                required>
        </td>

        <td class="w-32 px-3 py-3">
            <input type="number" min="0" step="0.01" value="0.00" name="precio_unitario[]" aria-label="Precio unitario" max="9999999999.99"
                class="precio w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                required>
        </td>

        <td class="w-24 px-3 py-3 text-right font-bold text-slate-900 valorLinea">0.00</td>

        <td class="w-12 px-3 py-3 text-center">
            <button type="button" aria-label="Quitar línea"
                class="btnDel inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 bg-white text-sm font-bold text-rose-600 transition hover:bg-rose-50">
                ×
            </button>
        </td>
    </tr>
</template>

<script>
(() => {
    const tipoProveedor = document.getElementById('proveedor_tipo');
    const proveedorExistente = document.getElementById('wrap_existente');
    const proveedorNuevo = document.getElementById('wrap_nuevo');
    const body = document.getElementById('detalleBody');
    const plantilla = document.getElementById('tplLinea');
    const total = document.getElementById('totalFactura');
    const agregar = document.getElementById('btnAddLinea');
    const anteriores = @js(\Illuminate\Support\Arr::only(session()->getOldInput(), [
        'producto_tipo', 'producto_codigo', 'producto_codigo_nuevo', 'producto_nombre', 'producto_unidad',
        'producto_vida_util_meses', 'producto_categoria', 'producto_descripcion', 'cantidad', 'precio_unitario',
        'campos_producto',
    ]));

    function actualizarProveedor() {
        const esNuevo = tipoProveedor.value === 'nuevo';
        proveedorExistente.hidden = esNuevo;
        proveedorNuevo.hidden = !esNuevo;
        proveedorExistente.querySelector('select').required = !esNuevo;
        proveedorNuevo.querySelector('input').required = esNuevo;
    }

    const destinos = document.querySelectorAll('#distribucion-compra [data-destino]');
    const autorizo = tipoProveedor.form.elements.namedItem('autorizado_por');
    const autorizacionObligatoria = autorizo?.required ?? false;
    function actualizarReparto() {
        const seleccionados = tipoProveedor.form.querySelectorAll('[name="consolidados[]"]:checked');
        if (autorizo) autorizo.required = autorizacionObligatoria || seleccionados.length > 0;

        const codigos = new Set([...body.rows]
            .filter(fila => fila.querySelector('.productoTipo').value === 'existente')
            .map(fila => fila.querySelector('.productoCodigo').value));

        for (const destino of destinos) {
            const seleccionado = destino.querySelector('input[type=checkbox]').checked;
            const reparto = destino.querySelector('[data-reparto]');
            reparto.hidden = reparto.disabled = !seleccionado;
            let coincide = false;
            for (const etiqueta of reparto.querySelectorAll('[data-reparto-producto]')) {
                const activo = seleccionado && codigos.has(etiqueta.dataset.repartoProducto);
                etiqueta.hidden = !activo;
                etiqueta.classList.toggle('flex', activo);
                etiqueta.querySelector('input').disabled = !activo;
                coincide ||= activo;
            }
            reparto.querySelector('[data-reparto-aviso]').hidden = coincide;
        }
    }

    function actualizarLineas() {
        const filas = [...body.rows];
        let suma = 0;
        filas.forEach((fila, indice) => {
            fila.querySelectorAll('[data-campos-plantilla] [name]').forEach(campo => {
                campo.name = campo.name.replace(/^campos_producto\[[^\]]+\]/, 'campos_producto[' + indice + ']');
            });
            const cantidad = Number(fila.querySelector('.cantidad').value);
            const precio = Number(fila.querySelector('.precio').value);
            const valor = Math.round(cantidad * precio * 100);
            fila.querySelector('.valorLinea').textContent = (valor / 100).toFixed(2);
            fila.querySelector('.btnDel').disabled = filas.length === 1;
            suma += valor;
        });
        agregar.disabled = filas.length >= 100;
        total.textContent = (suma / 100).toFixed(2);
        actualizarReparto();
    }

    function actualizarProducto(fila) {
        const esNuevo = fila.querySelector('.productoTipo').value === 'nuevo';
        fila.querySelector('.wrapExistente').hidden = esNuevo;
        fila.querySelector('.wrapNuevo').hidden = !esNuevo;
        const campos = fila.querySelector('[data-campos-plantilla]');
        if (campos) campos.disabled = !esNuevo;
        fila.querySelector('.productoCodigo').required = !esNuevo;
        fila.querySelectorAll('.productoNombre, .productoCodigoNuevo, .productoCategoria').forEach(campo => {
            campo.required = esNuevo;
        });
    }

    function recuperar(fila, indice) {
        for (const campo of fila.querySelectorAll('[name$="[]"]:not([data-campos-plantilla] *)')) {
            const valor = anteriores[campo.name.slice(0, -2)]?.[indice];
            if (typeof valor === 'string' || typeof valor === 'number') campo.value = valor;
        }
        const valores = anteriores.campos_producto?.[indice] ?? {};
        for (const campo of fila.querySelectorAll('[data-campos-plantilla] [name]')) {
            const clave = campo.name.split('][')[1]?.split(']')[0];
            const valor = valores[clave];
            if (valor === undefined || campo.type === 'hidden' || campo.type === 'file') continue;
            if (campo.type === 'checkbox') {
                campo.checked = valor === '1' || valor === 1 || valor === true;
            } else if (campo.multiple) {
                const seleccion = new Set(Array.isArray(valor) ? valor.map(String) : []);
                for (const opcion of campo.options) opcion.selected = seleccion.has(opcion.value);
            } else if (typeof valor === 'string' || typeof valor === 'number') {
                campo.value = valor;
            }
        }
    }

    function agregarLinea(indice = null) {
        const fila = plantilla.content.firstElementChild.cloneNode(true);
        if (indice !== null) recuperar(fila, indice);
        fila.querySelector('.productoTipo').addEventListener('change', () => actualizarProducto(fila));
        fila.addEventListener('input', actualizarLineas);
        fila.querySelector('.btnDel').addEventListener('click', () => {
            fila.remove();
            actualizarLineas();
            agregar.focus();
        });
        body.append(fila);
        actualizarProducto(fila);
        actualizarLineas();
        if (window.enhanceSearchableSelect) window.enhanceSearchableSelect(fila.querySelector('.productoCodigo'));
        if (indice === null) fila.querySelector('.productoTipo').focus();
    }

    proveedorNuevo.classList.remove('hidden');
    tipoProveedor.addEventListener('change', actualizarProveedor);
    actualizarProveedor();
    for (const destino of destinos) destino.querySelector('input[type=checkbox]').addEventListener('change', actualizarReparto);
    body.addEventListener('change', actualizarReparto);
    agregar.addEventListener('click', () => agregarLinea());
    const cantidad = Math.min(100, Math.max(1, Array.isArray(anteriores.producto_tipo) ? anteriores.producto_tipo.length : 0));
    for (let indice = 0; indice < cantidad; indice++) agregarLinea(indice);
})();
</script>
