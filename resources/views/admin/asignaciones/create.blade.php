@extends((int) auth()->user()->role_id === 2 ? 'layouts.operador' : 'layouts.admin')

@section('title', 'Nueva asignación')

@section('content')
@php
    $prefix = (int) auth()->user()->role_id === 2 ? 'operador' : 'admin';
    $input = 'w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-blue-200';
    $label = 'mb-1 block text-xs font-bold text-slate-600';

    $inventarioOptions = $inventarios->map(fn ($i) => [
        'producto_codigo' => $i->producto_codigo,
        'bodega_id' => $i->bodega_id,
        'stock_tipo' => $i->stock_tipo ?? 'nuevo',
        'cantidad' => (int) $i->cantidad,
        'vida_restante_meses' => $i->vida_util_restante_meses,
        'label' => (optional($i->producto)->descripcion ?: optional($i->producto)->nombre)
            .' - '.$i->producto_codigo
            .' ('.optional($i->bodega)->nombre.')'
            .' - '.(($i->stock_tipo ?? 'nuevo') === 'usado' ? 'Usado' : 'Nuevo')
            .' - Stock: '.$i->cantidad,
    ]);
@endphp

<div class="mx-auto w-full max-w-6xl">
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-bold">Corrige los siguientes errores:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @foreach(['success' => 'emerald', 'error' => 'rose'] as $key => $color)
        @if(session($key))
            <div class="mb-4 rounded-xl border border-{{ $color }}-200 bg-{{ $color }}-50 px-4 py-3 text-sm font-semibold text-{{ $color }}-700">
                {{ session($key) }}
            </div>
        @endif
    @endforeach

    <form method="POST"
          action="{{ route($prefix.'.asignaciones.store') }}"
          enctype="multipart/form-data"
          class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        @csrf

        <header class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.15em] text-blue-600">
                    Inventario / Asignaciones
                </p>
                <h1 class="mt-1 text-2xl font-extrabold text-slate-950">Nueva asignación</h1>
                <p class="mt-1 text-sm text-slate-500">Registra la entrega de productos a un colaborador.</p>
            </div>

            <a href="{{ route($prefix.'.asignaciones.index') }}"
               class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-center text-sm font-bold text-blue-700 hover:bg-blue-100">
                Ver asignaciones
            </a>
        </header>

        <section class="border-t border-slate-200">
            <h2 class="border-b border-slate-200 bg-slate-50/70 px-5 py-3 text-sm font-extrabold text-slate-900 sm:px-6">
                Datos generales
            </h2>

            <div class="grid grid-cols-1 gap-4 p-5 sm:p-6 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="{{ $label }}">Colaborador</label>
                    <select id="colaborador_codigo"
                            name="colaborador_codigo"
                            data-searchable="true"
                            data-search-placeholder="Buscar colaborador..."
                            class="{{ $input }}"
                            required>
                        @foreach($colaboradores as $c)
                            <option value="{{ $c->codigo }}"
                                @selected(old('colaborador_codigo') === $c->codigo)>
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $label }}">Fecha</label>
                    <input type="date"
                           name="fecha"
                           value="{{ old('fecha', date('Y-m-d')) }}"
                           class="{{ $input }}"
                           required>
                </div>

                <div>
                    <label class="{{ $label }}">Aprobado por</label>
                    <select name="aprobado_por" class="{{ $input }}" required>
                        @foreach($aprobadores as $a)
                            <option value="{{ $a }}" @selected(old('aprobado_por') === $a)>
                                {{ $a }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $label }}">Medio de solicitud</label>
                    <select name="medio_solicitud" class="{{ $input }}" required>
                        <option value="WhatsApp" @selected(old('medio_solicitud') === 'WhatsApp')>WhatsApp</option>
                        <option value="Correo" @selected(old('medio_solicitud') === 'Correo')>Correo</option>
                    </select>
                </div>

                <div>
                    <label class="{{ $label }}">Evidencia opcional</label>
                    <input type="file"
                           name="imagen"
                           accept="image/*"
                           class="block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:border-0 file:border-r file:border-slate-200 file:bg-slate-50 file:px-4 file:py-2.5 file:font-bold">
                </div>

                <div class="md:col-span-2">
                    <label class="{{ $label }}">Observaciones</label>
                    <textarea name="observaciones"
                              rows="2"
                              class="{{ $input }}"
                              placeholder="Escribe una observación si aplica...">{{ old('observaciones') }}</textarea>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-200">
            <div class="flex items-center justify-between gap-3 bg-slate-50/70 px-5 py-3 sm:px-6">
                <div>
                    <h2 class="text-sm font-extrabold text-slate-900">Productos a asignar</h2>
                    <p class="text-xs text-slate-500">Selecciona producto, condición, bodega y cantidad.</p>
                </div>

                <button type="button"
                        id="add-item"
                        class="rounded-xl border border-blue-200 bg-white px-4 py-2 text-xs font-extrabold text-blue-700 hover:bg-blue-50">
                    + Agregar producto
                </button>
            </div>

            <div id="items-wrapper" class="divide-y divide-slate-200"></div>
        </section>

        <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <p class="text-xs text-slate-500">Revisa la información antes de guardar.</p>

            <div class="flex gap-2">
                <a href="{{ route($prefix.'.asignaciones.index') }}"
                   class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700">
                    Cancelar
                </a>

                <button type="submit"
                        class="rounded-xl !border-blue-600 !bg-blue-600 px-5 py-2.5 text-sm font-extrabold !text-white hover:!bg-blue-700">
                    Guardar asignación
                </button>
            </div>
        </footer>
    </form>
</div>

<template id="item-template">
    <div class="item-row px-5 py-4 sm:px-6">
        <div class="mb-3 flex items-center justify-between">
            <p class="item-title text-sm font-bold text-slate-800">Producto</p>
            <button type="button"
                    class="remove-item rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700">
                Quitar
            </button>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
            <div class="md:col-span-5">
                <label class="{{ $label }}">Producto</label>
                <select data-name="producto_codigo"
                        data-searchable="true"
                        data-search-placeholder="Buscar producto..."
                        class="{{ $input }}"
                        required></select>
            </div>

            <div class="md:col-span-2">
                <label class="{{ $label }}">Condición</label>
                <select data-name="stock_tipo" class="{{ $input }}" required>
                    <option value="nuevo">Nuevo</option>
                    <option value="usado">Usado</option>
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="{{ $label }}">Bodega</label>
                <select data-name="bodega_id" class="{{ $input }}" required>
                    @foreach($bodegas as $b)
                        <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="{{ $label }}">Cantidad</label>
                <input type="number"
                       min="1"
                       value="1"
                       data-name="cantidad_asignada"
                       class="{{ $input }}"
                       required>
            </div>

            <p class="stock-warning md:col-span-12 text-xs font-semibold text-amber-700"></p>

            <div class="tracking hidden md:col-span-12 rounded-xl border border-blue-200 bg-blue-50/50 p-4">
                <input type="hidden" data-name="tipo_entrega" value="inicial">

                <div class="flex flex-col gap-3 md:flex-row md:items-end">
                    <div class="flex-1">
                        <label class="{{ $label }}">Asignación anterior</label>
                        <select data-name="asignacion_anterior_id"
                                class="previous {{ $input }}"></select>
                    </div>

                    <div class="md:w-52">
                        <label class="{{ $label }}">Tipo de entrega</label>
                        <select class="delivery-mode {{ $input }}">
                            <option value="reposicion">Reposición</option>
                            <option value="adicional">Entrega adicional</option>
                        </select>
                    </div>
                </div>

                <p class="assignment-info mt-3 text-xs font-semibold text-slate-600"></p>

                <div class="extra-fields mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="requested-field">
                        <label class="{{ $label }}">Solicitado por</label>
                        <input type="text"
                               data-name="solicitado_por"
                               class="{{ $input }}"
                               placeholder="Supervisor o solicitante">
                    </div>

                    <div class="reason-field">
                        <label class="{{ $label }}">Motivo</label>
                        <select data-name="motivo_reposicion" class="{{ $input }}">
                            <option value="">Seleccionar</option>
                            <option value="desgaste_prematuro">Desgaste prematuro</option>
                            <option value="dano_accidental">Daño accidental</option>
                            <option value="mal_uso">Mal uso</option>
                            <option value="perdida">Pérdida</option>
                            <option value="cambio_talla_especificacion">Cambio de talla o especificación</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="{{ $label }}">Justificación</label>
                        <textarea data-name="justificacion_reposicion"
                                  rows="2"
                                  class="{{ $input }}"
                                  placeholder="Explica el motivo de la nueva entrega..."></textarea>
                    </div>
                </div>
            </div>

            <p class="assignment-status hidden md:col-span-12 rounded-lg px-3 py-2 text-xs font-semibold"></p>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inventory = @json($inventarioOptions);
    const oldItems = @json(old('items', []));
    const endpoint = @json(route($prefix.'.asignaciones.activas_producto'));
    const wrapper = document.getElementById('items-wrapper');
    const template = document.getElementById('item-template');
    const collaborator = document.getElementById('colaborador_codigo');

    const get = (object, keys, fallback = null) => {
        for (const key of keys) {
            if (object?.[key] !== null && object?.[key] !== undefined) return object[key];
        }
        return fallback;
    };

    const assignmentsFrom = json =>
        Array.isArray(json) ? json : (json.asignaciones || json.data || []);

    const secondsLabel = seconds => {
        if (seconds === null || seconds === undefined) return 'No aplica';
        const days = Math.max(0, Math.floor(Number(seconds) / 86400));
        return `${Math.floor(days / 30)} mes(es), ${days % 30} día(s)`;
    };

    const assignmentId = item =>
        get(item, ['id', 'asignacion_id', 'asignacion_anterior_id']);

    const remaining = item =>
        Number(get(item, [
            'vida_restante_segundos',
            'vida_util_restante_segundos',
            'vida_restante_anterior_segundos'
        ], 0));

    const isExpired = item =>
        get(item, ['vida_finalizada', 'vida_util_finalizada', 'expired'], false)
        || remaining(item) <= 0;

    const buildProducts = select => {
        inventory.forEach(item => {
            select.add(new Option(item.label, item.producto_codigo));
        });
    };

    const reindex = () => {
        wrapper.querySelectorAll('.item-row').forEach((row, index) => {
            row.querySelector('.item-title').textContent = `Producto ${index + 1}`;
            row.querySelectorAll('[data-name]').forEach(field => {
                field.name = `items[${index}][${field.dataset.name}]`;
            });
        });
    };

    const setRequired = (field, required) => {
        field.required = required;
    };

    const updateMode = row => {
        const selectedId = row.querySelector('.previous').value;
        const assignment = row.assignments?.find(
            item => String(assignmentId(item)) === String(selectedId)
        );

        if (!assignment) return;

        const additional = row.querySelector('.delivery-mode').value === 'adicional';
        const expired = isExpired(assignment);
        const type = row.querySelector('[data-name="tipo_entrega"]');
        const requested = row.querySelector('[data-name="solicitado_por"]');
        const reason = row.querySelector('[data-name="motivo_reposicion"]');
        const justification = row.querySelector('[data-name="justificacion_reposicion"]');

        type.value = additional
            ? 'adicional'
            : (expired ? 'reposicion_normal' : 'reposicion_anticipada');

        row.querySelector('.requested-field').classList.toggle('hidden', additional || expired);
        row.querySelector('.reason-field').classList.toggle('hidden', additional || expired);

        setRequired(requested, !additional && !expired);
        setRequired(reason, !additional && !expired);
        setRequired(justification, additional || !expired);
    };

    const showAssignment = row => {
        const selectedId = row.querySelector('.previous').value;
        const assignment = row.assignments.find(
            item => String(assignmentId(item)) === String(selectedId)
        );

        if (!assignment) return;

        const date = get(assignment, ['fecha', 'fecha_asignacion'], 'Sin fecha');
        const quantity = get(assignment, ['cantidad_activa', 'cantidad_asignada', 'cantidad'], 1);

        row.querySelector('.assignment-info').textContent =
            `${isExpired(assignment) ? 'Vida útil finalizada' : 'Vida útil vigente'} · `
            + `Fecha: ${date} · Cantidad activa: ${quantity} · `
            + `Vida restante: ${secondsLabel(remaining(assignment))}`;

        updateMode(row);
    };

    const loadAssignments = async row => {
        const product = row.querySelector('[data-name="producto_codigo"]');
        const warehouse = row.querySelector('[data-name="bodega_id"]');
        const condition = row.querySelector('[data-name="stock_tipo"]');
        const panel = row.querySelector('.tracking');
        const status = row.querySelector('.assignment-status');
        const previous = row.querySelector('.previous');
        const type = row.querySelector('[data-name="tipo_entrega"]');

        panel.classList.add('hidden');
        status.classList.add('hidden');
        type.value = 'inicial';
        row.assignments = [];

        if (!collaborator.value || !product.value) return;

        try {
            const params = new URLSearchParams({
                  colaborador_codigo: collaborator.value,
                  producto_codigo: product.value,
                  bodega_id: warehouse.value,
                  stock_tipo: condition.value
            });
            const response = await fetch(`${endpoint}?${params}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
              const detail = await response.text();
              console.error('Error al consultar asignaciones:', response.status, detail);
                  throw new Error(`Error ${response.status}`);
            }

            row.assignments = assignmentsFrom(await response.json());
            if (!row.assignments.length) return;

            previous.innerHTML = '';

            row.assignments.forEach(item => {
                const id = assignmentId(item);
                const date = get(item, ['fecha', 'fecha_asignacion'], 'Sin fecha');
                previous.add(new Option(`#${id} · ${date}`, id));
            });

            if (row.defaults?.asignacion_anterior_id) {
                previous.value = row.defaults.asignacion_anterior_id;
            }

            row.querySelector('.delivery-mode').value =
                row.defaults?.tipo_entrega === 'adicional' ? 'adicional' : 'reposicion';

            panel.classList.remove('hidden');
            showAssignment(row);
        } catch (error) {
            status.textContent = 'No fue posible consultar asignaciones anteriores.';
            status.className =
                'assignment-status md:col-span-12 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700';
        }
    };

    const updateStock = row => {
        const product = row.querySelector('[data-name="producto_codigo"]');
        const warehouse = row.querySelector('[data-name="bodega_id"]');
        const condition = row.querySelector('[data-name="stock_tipo"]');
        const quantity = row.querySelector('[data-name="cantidad_asignada"]');
        const warning = row.querySelector('.stock-warning');

        const current = inventory.find(item =>
            item.producto_codigo === product.value
            && String(item.bodega_id) === String(warehouse.value)
            && item.stock_tipo === condition.value
        );

        quantity.max = current?.cantidad || 0;
        warning.textContent = current?.cantidad > 0
            ? `Stock disponible: ${current.cantidad}.`
            : 'No hay existencias de esta condición en la bodega seleccionada.';
    };

    const addItem = (defaults = {}) => {
        const row = template.content.firstElementChild.cloneNode(true);
        row.defaults = defaults;

        const product = row.querySelector('[data-name="producto_codigo"]');
        const warehouse = row.querySelector('[data-name="bodega_id"]');
        const condition = row.querySelector('[data-name="stock_tipo"]');
        const quantity = row.querySelector('[data-name="cantidad_asignada"]');

        buildProducts(product);

        product.value = defaults.producto_codigo || product.value;
        warehouse.value = defaults.bodega_id || warehouse.value;
        condition.value = defaults.stock_tipo || 'nuevo';
        quantity.value = defaults.cantidad_asignada || 1;

        ['solicitado_por', 'motivo_reposicion', 'justificacion_reposicion']
            .forEach(name => {
                if (defaults[name]) row.querySelector(`[data-name="${name}"]`).value = defaults[name];
            });

        [product, warehouse, condition].forEach(field => {
            field.addEventListener('change', () => {
                updateStock(row);
                loadAssignments(row);
            });
        });

        row.querySelector('.previous').addEventListener('change', () => showAssignment(row));
        row.querySelector('.delivery-mode').addEventListener('change', () => updateMode(row));

        row.querySelector('.remove-item').addEventListener('click', () => {
            row.remove();
            if (!wrapper.children.length) addItem();
            reindex();
        });

        wrapper.appendChild(row);

        if (window.enhanceSearchableSelect) {
            window.enhanceSearchableSelect(product);
        }

        updateStock(row);
        reindex();
        loadAssignments(row);
    };

    document.getElementById('add-item').addEventListener('click', () => addItem());

    collaborator.addEventListener('change', () => {
        wrapper.querySelectorAll('.item-row').forEach(loadAssignments);
    });

    oldItems.length ? oldItems.forEach(addItem) : addItem();
});
</script>
@endsection