@extends('layouts.app')
@section('title', 'Nueva asignación')
@section('content')
@php
    $usuario = auth()->user();
    $routePrefix = \App\Services\AccesoInventarioOperativo::prefijo($usuario);
    $puedeElegirNuevo = app(\App\Services\AccesoInventarioOperativo::class)->tiene($usuario, 'asignaciones.entregar_nuevo');
    $disponibles = $inventarios->groupBy(fn ($fila) => $fila->bodega_id.'|'.$fila->producto_codigo)->map(function ($filas) {
        $fila = $filas->first();

        return [
            'codigo' => $fila->producto_codigo,
            'bodega' => $fila->bodega_id,
            'nombre' => ($fila->producto?->nombre ?? $fila->producto_codigo).' · '.$fila->producto_codigo,
            'usado' => (int) $filas->where('stock_tipo', 'usado')->sum('cantidad'),
            'nuevo' => (int) $filas->where('stock_tipo', 'nuevo')->sum('cantidad'),
        ];
    })->values();
@endphp
<div class="mx-auto max-w-4xl px-4 py-8">
    <x-internal-navigation :back-url="route($routePrefix.'.asignaciones.index')" />
    <form action="{{ route($routePrefix.'.asignaciones.store') }}" method="POST" class="ui-form space-y-4">
        @csrf
        <input type="hidden" name="solicitud_id" value="{{ old('solicitud_id', (string) \Illuminate\Support\Str::uuid()) }}">
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h1 class="ui-page-title text-slate-900">Nueva asignación</h1>
            <label for="colaborador-codigo" class="mb-1 mt-4 block text-sm font-bold">Colaborador</label>
            <select id="colaborador-codigo" name="colaborador_codigo" data-searchable="true" data-search-placeholder="Buscar colaborador"
                    class="w-full rounded-xl border-slate-300" required>
                <option value="">Selecciona al colaborador</option>
                @foreach($colaboradores as $colaborador)
                    <option value="{{ $colaborador->codigo }}" @selected(old('colaborador_codigo', request('colaborador_codigo')) === $colaborador->codigo)>
                        {{ $colaborador->nombre }} · {{ $colaborador->codigo }}
                    </option>
                @endforeach
            </select>
            <div class="mt-4"><x-flujo.consolidado-origen :consolidados="$consolidadosOrigen" /></div>
            <label class="ui-label mt-4 block">Medio de solicitud
                <select name="medio_solicitud" class="ui-input mt-1">
                    <option value="">Sin especificar</option>
                    @foreach(['Correo', 'WhatsApp'] as $medio)
                        <option value="{{ $medio }}" @selected(old('medio_solicitud') === $medio)>{{ $medio }}</option>
                    @endforeach
                    <option value="{{ \App\Services\RegistrarAsignaciones::MEDIO_INTERNO }}" disabled>Requerimiento interno</option>
                </select>
            </label>
        </section>

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold" id="titulo-productos">Productos</h2>
            @if($bodegas->count() === 1)
                <p class="text-sm text-slate-600">Bodega de salida <strong>{{ $bodegas->sole()->nombre }}</strong></p>
            @endif
            <p class="text-sm" id="estado-productos" role="status"></p>
            <ul class="grid gap-2 sm:grid-cols-2" id="productos-pendientes" hidden></ul>
            <div id="items-wrapper" class="space-y-3"></div>
            <button type="button" class="ui-btn-secondary" id="add-item">Agregar otro producto</button>
            <template id="item-template">
                <fieldset class="item-row grid gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                    @if($bodegas->count() === 1)
                        <input type="hidden" data-campo="bodega_id" value="{{ $bodegas->first()->id }}">
                    @else
                        <label class="text-sm font-semibold">Bodega
                            <select data-campo="bodega_id" class="mt-1 w-full rounded-xl border-slate-300" required>
                                @foreach($bodegas as $bodega)
                                    <option value="{{ $bodega->id }}">{{ $bodega->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label class="text-sm font-semibold">Producto
                        <select data-campo="producto_codigo" class="mt-1 w-full rounded-xl border-slate-300" required></select>
                    </label>
                    <p class="text-sm text-slate-600" data-surtido aria-live="polite"></p>
                    <div class="flex items-end justify-between gap-3">
                        <label class="ui-label" data-cantidad>Unidades a entregar
                            <input type="number" min="1" value="1" step="1" class="ui-input w-24 mt-1" data-campo="cantidad_asignada" required>
                        </label>
                        <button type="button" class="ui-btn-secondary" data-quitar>Quitar</button>
                    </div>
                    <label class="ui-label" data-sustitucion hidden>Sustituye una asignación
                        <select data-campo="sustituir_asignacion_id" class="ui-input mt-1" disabled></select>
                    </label>
                    <label class="ui-label" data-motivo-reemplazo hidden>Motivo del reemplazo
                        <select data-campo="motivo_asignacion" class="ui-input mt-1" disabled>
                            <option value="">Selecciona el motivo</option>
                            @foreach($motivos as $razon)
                                <option value="{{ $razon->codigo }}">{{ $razon->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if($puedeElegirNuevo)
                        <details class="sm:col-span-2">
                            <summary class="cursor-pointer text-sm font-semibold">Entregar inventario nuevo por excepción</summary>
                            <label class="mt-2 block text-sm">Motivo para elegir nuevo
                                <input data-campo="motivo_inventario_nuevo" maxlength="500" class="mt-1 w-full rounded-xl border-slate-300">
                            </label>
                        </details>
                    @endif
                </fieldset>
            </template>
        </section>

        <details class="rounded-xl border border-slate-200 bg-white p-5" @if(old('modalidad') === 'tercero') open @endif>
            <summary class="cursor-pointer font-bold">Salida por medio de un tercero</summary>
            <label class="mt-3 block text-sm font-semibold">
                <input type="checkbox" name="modalidad" value="tercero" @checked(old('modalidad') === 'tercero')}>
                El colaborador recibirá el equipo por medio de otra persona
            </label>
            <label class="mt-3 block text-sm">Nombre del tercero (opcional)
                <input name="tercero" value="{{ old('tercero') }}" maxlength="180" class="mt-1 w-full rounded-xl border-slate-300">
            </label>
        </details>

        <x-sistema.campos-nativos modulo="asignaciones" />
        <x-sistema.campos modulo="asignaciones" />

        <p class="text-sm text-slate-600" id="entrega-ayuda">Al guardar, registras la entrega directa al colaborador.</p>
        <button type="submit" class="ui-btn-primary" aria-describedby="entrega-ayuda">Guardar asignación</button>
    </form>
</div>

<script>
(() => {
    const disponibles = @js($disponibles);
    const filas = document.getElementById('items-wrapper');
    const forma = filas.closest('form');
    const plantilla = document.getElementById('item-template');
    const origen = forma.elements.consolidado_id;
    const medio = forma.elements.medio_solicitud;
    const colaborador = forma.elements.colaborador_codigo;
    const agregarOtro = document.getElementById('add-item');
    const estado = document.getElementById('estado-productos');
    const pendientes = document.getElementById('productos-pendientes');
    const guardar = forma.querySelector('button[type=submit]');
    let consulta;
    let contexto = {productos: [], custodias: [], bodegas: null};
    let medioIndependiente = medio.value;

    function actualizarMedio() {
        medio.disabled = !!origen?.value;
        medio.value = origen?.value ? @js(\App\Services\RegistrarAsignaciones::MEDIO_INTERNO) : medioIndependiente;
    }

    function pintar() {
        const productos = [...filas.querySelectorAll('[data-campo="producto_codigo"]')].map(campo => campo.value);
        [...filas.children].forEach((fila, indice) => {
            for (const campo of fila.querySelectorAll('[data-campo]')) {
                campo.name = `items[${indice}][${campo.dataset.campo}]`;
            }
            const producto = fila.querySelector('[data-campo="producto_codigo"]');
            const repetido = producto.value && productos.filter(codigo => codigo === producto.value).length > 1;
            producto.setCustomValidity(repetido ? 'Selecciona cada código una sola vez.' : '');
        });
        guardar.disabled = !filas.children.length;
        for (const boton of pendientes.querySelectorAll('button')) {
            boton.disabled = boton.dataset.sinStock === '1' || productos.includes(boton.dataset.codigo) || filas.children.length >= 100;
        }
        agregarOtro.disabled = filas.children.length >= 100;
    }

    function agregar(datos = {}) {
        const fila = plantilla.content.firstElementChild.cloneNode(true);
        const campo = clave => fila.querySelector(`[data-campo="${clave}"]`);
        const bodega = campo('bodega_id');
        const producto = campo('producto_codigo');
        const cantidad = campo('cantidad_asignada');
        const reemplazo = campo('sustituir_asignacion_id');
        const motivo = campo('motivo_asignacion');
        const nuevo = campo('motivo_inventario_nuevo');
        if (bodega instanceof HTMLSelectElement && contexto.bodegas !== null) {
            const autorizadas = contexto.bodegas.map(String);
            for (const opcion of [...bodega.options]) {
                if (!autorizadas.includes(opcion.value)) opcion.remove();
            }
        }
        if (datos.bodega_id && (!(bodega instanceof HTMLSelectElement) || [...bodega.options].some(opcion => opcion.value === String(datos.bodega_id)))) {
            bodega.value = datos.bodega_id;
        }
        if (nuevo && datos.motivo_inventario_nuevo) {
            nuevo.value = datos.motivo_inventario_nuevo;
            nuevo.closest('details').open = true;
        }
        function actualizar(sugerir = false) {
            const ficha = disponibles.find(item => String(item.bodega) === bodega.value && item.codigo === producto.value);
            const pendiente = contexto.productos.find(item => item.codigo === producto.value);
            const stock = nuevo?.value.trim() ? ficha?.nuevo : (ficha?.nuevo ?? 0) + (ficha?.usado ?? 0);
            cantidad.max = origen?.value ? Math.min(pendiente?.pendiente ?? 0, stock ?? 0, 500) : 1;
            if (sugerir) cantidad.value = origen?.value ? Math.max(1, Number(cantidad.max)) : 1;
            fila.querySelector('[data-cantidad]').hidden = !origen?.value;
            fila.querySelector('[data-surtido]').textContent = ficha
                ? `${stock ?? 0} disponible(s) · ${ficha.usado && !nuevo?.value.trim() ? 'Prioridad a inventario usado' : 'Inventario nuevo'}`
                : (producto.value ? 'Sin existencias en esta bodega.' : '');

            const custodias = contexto.custodias.filter(item => item.producto_codigo === producto.value);
            const anterior = reemplazo.value || (sugerir ? '' : datos.sustituir_asignacion_id ?? '');
            reemplazo.replaceChildren(new Option('Entrega sin sustitución', ''), ...custodias.map(item =>
                new Option(`Asignación #${item.id} · ${item.fecha.slice(0, 10)} · ${item.cantidad_asignada} en custodia`, item.id)));
            reemplazo.value = anterior;
            reemplazo.closest('label').hidden = reemplazo.disabled = custodias.length === 0;
            motivo.closest('label').hidden = motivo.disabled = !reemplazo.value;
            motivo.required = !!reemplazo.value;
            if (reemplazo.value) cantidad.max = Math.min(1, Number(cantidad.max));
            pintar();
        }
        function cargar(seleccionado) {
            producto.replaceChildren(new Option('Selecciona un producto', ''), ...disponibles
                .filter(item => String(item.bodega) === bodega.value && (!origen?.value || contexto.productos.some(p => p.codigo === item.codigo)))
                .map(item => new Option(item.nombre, item.codigo)));
            producto.value = seleccionado ?? '';
            actualizar();
        }
        bodega.addEventListener('change', () => cargar(producto.value));
        producto.addEventListener('change', () => {
            reemplazo.value = motivo.value = '';
            actualizar(true);
        });
        reemplazo.addEventListener('change', () => {
            if (reemplazo.value) cantidad.value = 1;
            else motivo.value = '';
            datos.sustituir_asignacion_id = '';
            actualizar();
        });
        nuevo?.addEventListener('input', () => actualizar());
        fila.querySelector('[data-quitar]').addEventListener('click', () => {
            fila.remove();
            pintar();
        });
        filas.append(fila);
        cantidad.value = datos.cantidad_asignada ?? 1;
        motivo.value = datos.motivo_asignacion ?? '';
        cargar(datos.producto_codigo);
    }

    function mostrarPendientes() {
        pendientes.replaceChildren();
        pendientes.hidden = !origen?.value;
        for (const producto of contexto.productos) {
            const item = document.createElement('li');
            item.className = 'flujo-card space-y-2';
            const nombre = document.createElement('strong');
            nombre.textContent = `${producto.nombre} · ${producto.codigo}`;
            const saldo = document.createElement('p');
            saldo.className = 'text-sm';
            const bodegas = Object.entries(producto.existencias).filter(([, n]) => n > 0).sort((a, b) => b[1] - a[1]);
            const stock = bodegas.reduce((total, [, n]) => total + n, 0);
            saldo.textContent = `Aprobadas ${producto.aprobada} · Entregadas ${producto.entregada} · Pendientes ${producto.pendiente} · ${stock ? 'Existencias ' + stock : 'Sin existencias'}`;
            const boton = document.createElement('button');
            boton.className = 'ui-btn-secondary';
            boton.type = 'button';
            boton.textContent = 'Agregar';
            boton.setAttribute('aria-label', 'Agregar ' + producto.nombre);
            boton.dataset.codigo = producto.codigo;
            boton.dataset.sinStock = stock ? '0' : '1';
            boton.addEventListener('click', () => agregar({
                producto_codigo: producto.codigo, bodega_id: bodegas[0][0],
                cantidad_asignada: Math.min(500, producto.pendiente, bodegas[0][1]),
            }));
            item.append(nombre, saldo, boton);
            pendientes.append(item);
        }
    }

    async function cargarContexto(restaurar = []) {
        consulta?.abort();
        consulta = new AbortController();
        const nuevaConsulta = consulta;
        contexto = {productos: [], custodias: [], bodegas: null};
        filas.replaceChildren();
        pendientes.replaceChildren();
        agregarOtro.hidden = !!origen?.value;
        document.getElementById('titulo-productos').textContent = origen?.value ? 'Productos aprobados pendientes' : 'Productos';
        pintar();
        estado.textContent = '';
        if (colaborador.value) {
            const url = new URL(@js(route($routePrefix.'.asignaciones.contexto')), location.href);
            url.searchParams.set('colaborador_codigo', colaborador.value);
            if (origen?.value) url.searchParams.set('consolidado_id', origen.value);
            estado.textContent = 'Consultando productos…';
            try {
                const respuesta = await fetch(url, {signal: consulta.signal, headers: {Accept: 'application/json'}});
                if (!respuesta.ok) throw new Error();
                const datos = await respuesta.json();
                if (nuevaConsulta.signal.aborted) return;
                contexto = datos;
                estado.textContent = origen?.value && !contexto.productos.length ? 'Este colaborador no tiene productos pendientes en el consolidado.' : '';
            } catch (error) {
                if (error.name === 'AbortError') return;
                estado.textContent = 'No se pudo consultar el requerimiento. Cambia la selección para reintentar.';
                return;
            }
        } else if (origen?.value) {
            estado.textContent = 'Selecciona un colaborador para ver sus productos pendientes.';
        }
        mostrarPendientes();
        restaurar.forEach(agregar);
        if (!origen?.value && !restaurar.length) agregar();
        pintar();
    }
    colaborador.addEventListener('change', () => {
        const anteriores = origen?.value ? [] : [...filas.children].map(fila => Object.fromEntries(
            [...fila.querySelectorAll('[data-campo]')]
                .filter(campo => !['sustituir_asignacion_id', 'motivo_asignacion'].includes(campo.dataset.campo))
                .map(campo => [campo.dataset.campo, campo.value])
        ));
        cargarContexto(anteriores);
    });
    medio.addEventListener('change', () => medioIndependiente = medio.value);
    origen?.addEventListener('change', () => {
        actualizarMedio();
        cargarContexto();
    });
    agregarOtro.addEventListener('click', () => agregar());
    actualizarMedio();
    cargarContexto(Object.values(@js(old('items', []))));
    const tercero = forma.elements.modalidad;
    const datosTercero = forma.elements.tercero;
    function mostrarTercero() {
        datosTercero.disabled = !tercero.checked;
        document.getElementById('entrega-ayuda').textContent = tercero.checked
            ? 'Al guardar, registras la salida con el tercero.'
            : 'Al guardar, registras la entrega directa al colaborador.';
    }
    tercero.addEventListener('change', mostrarTercero);
    mostrarTercero();
})();
</script>
@endsection
