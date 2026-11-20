@extends('layouts.app')
@section('title', 'Nueva asignación')
@section('content')
@php
    $usuario = auth()->user();
    $routePrefix = \App\Sistema\CatalogoPermisos::prefijo($usuario->rolOperativo());
    $puedeElegirNuevo = app(\App\Sistema\Permisos::class)->permite($usuario, 'asignaciones.entregar_nuevo');
    $disponibles = $inventarios->groupBy(fn ($fila) => $fila->bodega_id.'|'.$fila->producto_codigo)->map(function ($filas) {
        $fila = $filas->first();

        return [
            'codigo' => $fila->producto_codigo,
            'bodega' => $fila->bodega_id,
            'nombre' => ($fila->producto?->nombre ?? $fila->producto_codigo).' · '.$fila->producto_codigo,
            'usado' => $filas->contains('stock_tipo', 'usado'),
        ];
    })->values();
@endphp
<div class="mx-auto max-w-4xl px-4 py-8">
    <x-internal-navigation :back-url="route($routePrefix.'.asignaciones.index')" />
    <form action="{{ route($routePrefix.'.asignaciones.store') }}" method="POST" class="ui-form space-y-4">
        @csrf
        <input type="hidden" name="solicitud_id" value="{{ old('solicitud_id', (string) \Illuminate\Support\Str::uuid()) }}">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h1 class="text-2xl font-extrabold text-slate-900">Nueva asignación</h1>
            <label for="colaborador-codigo" class="mb-1 mt-4 block text-sm font-bold">Colaborador</label>
            <select id="colaborador-codigo" name="colaborador_codigo" data-searchable="true" data-search-placeholder="Buscar colaborador"
                    class="w-full rounded-xl border-slate-300" required>
                <option value="">Selecciona al colaborador</option>
                @foreach($colaboradores as $colaborador)
                    <option value="{{ $colaborador->codigo }}" @selected(old('colaborador_codigo') === $colaborador->codigo)>
                        {{ $colaborador->nombre }} · {{ $colaborador->codigo }}
                    </option>
                @endforeach
            </select>
        </section>

        <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold">Productos</h2>
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
                        <span class="text-sm text-slate-600">Cantidad: 1 unidad</span>
                        <button type="button" class="ui-btn-secondary" data-quitar>Quitar</button>
                    </div>
                    <label class="text-sm font-semibold">Motivo de asignación o reemplazo
                        <select data-campo="motivo_asignacion" class="mt-1 w-full rounded-xl border-slate-300">
                            <option value="">Sin motivo específico</option>
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

        <details class="rounded-2xl border border-slate-200 bg-white p-5" @if(old('modalidad') === 'tercero') open @endif>
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
    const disponibles = @json($disponibles);
    const filas = document.getElementById('items-wrapper');
    const forma = filas.closest('form');
    const plantilla = document.getElementById('item-template');
    const pintar = () => {
        Array.from(filas.children).forEach((fila, indice) => {
            fila.querySelectorAll('[data-campo]').forEach(campo => {
                campo.name = `items[${indice}][${campo.dataset.campo}]`;
            });
            fila.querySelector('[data-quitar]').hidden = filas.children.length === 1;
        });
        const productos = Array.from(filas.querySelectorAll('[data-campo="producto_codigo"]'));
        for (const producto of productos) {
            producto.setCustomValidity(
                producto.value && productos.filter(p => p.value === producto.value).length > 1
                    ? 'Selecciona cada código una sola vez.' : ''
            );
        }
    };
    function agregar(datos = {}) {
        const fila = plantilla.content.firstElementChild.cloneNode(true);
        const bodega = fila.querySelector('[data-campo="bodega_id"]');
        const producto = fila.querySelector('[data-campo="producto_codigo"]');
        const motivo = fila.querySelector('[data-campo="motivo_inventario_nuevo"]');
        fila.querySelector('[data-campo="motivo_asignacion"]').value = datos.motivo_asignacion ?? '';
        if (datos.bodega_id) bodega.value = datos.bodega_id;
        if (motivo && datos.motivo_inventario_nuevo) {
            motivo.value = datos.motivo_inventario_nuevo;
            motivo.closest('details').open = true;
        }
        function mostrarSurtido() {
            const ficha = disponibles.find(item => String(item.bodega) === bodega.value && item.codigo === producto.value);
            fila.querySelector('[data-surtido]').textContent = ficha
                ? (ficha.usado && !motivo?.value.trim() ? 'Surtir de inventario usado.' : 'Surtir de inventario nuevo.')
                : '';
        }
        function cargar(seleccionado) {
            producto.replaceChildren(new Option('Selecciona un producto', ''), ...disponibles
                .filter(ficha => String(ficha.bodega) === bodega.value)
                .map(ficha => new Option(ficha.nombre, ficha.codigo)));
            producto.value = seleccionado ?? '';
            mostrarSurtido();
            pintar();
        }
        bodega.addEventListener('change', () => cargar(producto.value));
        producto.addEventListener('change', () => {
            mostrarSurtido();
            pintar();
        });
        motivo?.addEventListener('input', mostrarSurtido);
        fila.querySelector('[data-quitar]').addEventListener('click', () => {
            fila.remove();
            pintar();
        });
        filas.append(fila);
        cargar(datos.producto_codigo);
    }
    const anteriores = Object.values(@json(old('items', [])));
    if (anteriores.length) anteriores.forEach(agregar);
    else agregar();
    document.getElementById('add-item').addEventListener('click', () => agregar());
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
</script>
@endsection
