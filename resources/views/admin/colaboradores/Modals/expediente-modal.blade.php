{{-- MODAL EXPEDIENTE ADMINISTRATIVO --}}
<div
    x-show="modalOpen"
    x-cloak
    x-transition.opacity
    @keydown.escape.window="closeModal()"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6"
>
    <div
        class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
        @click="closeModal()"
    ></div>

    <section class="relative flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">

        {{-- Encabezado --}}
        <header class="flex flex-none flex-col gap-4 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div class="min-w-0">
                <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-blue-600">
                    Expediente administrativo
                </p>

                <h2
                    class="mt-1 truncate text-xl font-extrabold text-slate-950"
                    x-text="detalle.nombre || 'Cargando...'"
                ></h2>

                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                    <span>
                        Código:
                        <strong class="text-slate-700" x-text="detalle.codigo || '—'"></strong>
                    </span>

                    <span>
                        Puesto:
                        <strong class="text-slate-700" x-text="detalle.puesto || '—'"></strong>
                    </span>

                    <span>
                        Estado:
                        <strong class="text-slate-700" x-text="detalle.estado || '—'"></strong>
                    </span>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <a
                    x-show="detalle.codigo"
                    :href="`/${routePrefix}/colaboradores/${detalle.codigo}/ficha-tecnica`"
                    target="_blank"
                    class="inline-flex items-center justify-center rounded-xl bg-slate-800 px-4 py-2 text-xs font-extrabold text-white hover:bg-slate-900"
                >
                    Descargar Excel
                </a>

                <button
                    type="button"
                    @click="closeModal()"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                    aria-label="Cerrar"
                >
                    ×
                </button>
            </div>
        </header>

        {{-- Pestañas pequeñas --}}
        <nav class="flex flex-none gap-2 overflow-x-auto border-b border-slate-200 bg-slate-50 px-5 py-3 sm:px-6">
            <button
                type="button"
                @click="expedienteTab='productos'"
                :class="expedienteTab==='productos'
                    ? 'border-blue-600 bg-blue-600 text-white'
                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100'"
                class="whitespace-nowrap rounded-lg border px-3 py-2 text-xs font-extrabold"
            >
                Productos
                <span class="ml-1" x-text="asignaciones.length"></span>
            </button>

            <button
                type="button"
                @click="expedienteTab='vehiculo'"
                :class="expedienteTab==='vehiculo'
                    ? 'border-blue-600 bg-blue-600 text-white'
                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100'"
                class="whitespace-nowrap rounded-lg border px-3 py-2 text-xs font-extrabold"
            >
                Vehículo
                <span class="ml-1" x-text="productosVehiculo.length"></span>
            </button>

            <button
                type="button"
                @click="expedienteTab='descuentos'"
                :class="expedienteTab==='descuentos'
                    ? 'border-rose-600 bg-rose-600 text-white'
                    : 'border-rose-200 bg-white text-rose-600 hover:bg-rose-50'"
                class="whitespace-nowrap rounded-lg border px-3 py-2 text-xs font-extrabold"
            >
                Descuentos RRHH
                <span class="ml-1" x-text="cobros.length"></span>
            </button>
        </nav>

        {{-- Contenido --}}
        <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">

            <div
                x-show="loadingDetalle"
                class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-6 text-center text-sm font-semibold text-slate-500"
            >
                Cargando expediente...
            </div>

            <div x-show="!loadingDetalle">

                {{-- Resumen compacto: sin tarjetas repetidas --}}
                <div
                    x-show="expedienteTab==='resumen'"
                    class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4"
                >
                    <p class="text-sm font-semibold text-slate-700">
                        Selecciona Productos, Vehículo o Descuentos RRHH para consultar el expediente.
                    </p>
                </div>

                {{-- Productos --}}
                <div x-show="expedienteTab==='productos'" x-cloak>
                    <section class="overflow-hidden rounded-2xl border border-slate-200">
                        <header class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-extrabold text-slate-950">
                                    Productos asignados directamente
                                </h3>

                                <p class="text-xs text-slate-500">
                                    Artículos bajo responsabilidad directa del colaborador.
                                </p>
                            </div>

                            <span class="text-xs font-bold text-slate-600">
                                <span x-text="asignaciones.length"></span> productos ·
                                Q <span x-text="money(totalInventario)"></span>
                            </span>
                        </header>

                        <template x-if="asignaciones.length===0">
                            <div class="p-5 text-sm text-slate-500">
                                No tiene productos asignados directamente.
                            </div>
                        </template>

                        <template x-if="asignaciones.length>0">
                            <div>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[850px] text-xs">
                                        <thead class="bg-slate-50 text-slate-500">
                                            <tr>
                                                <th class="px-3 py-3 text-left">Artículo</th>
                                                <th class="px-3 py-3 text-left">Bodega</th>
                                                <th class="px-3 py-3 text-left">Cant.</th>
                                                <th class="px-3 py-3 text-left">Costo</th>
                                                <th class="px-3 py-3 text-left">Total</th>
                                                <th class="px-3 py-3 text-left">Asignado</th>
                                                <th class="px-3 py-3 text-left">Estado</th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-slate-100">
                                            <template
                                                x-for="item in asignacionesPaginadas()"
                                                :key="'asignacion-'+item._index"
                                            >
                                                <tr class="hover:bg-slate-50">
                                                    <td class="px-3 py-3">
                                                        <p
                                                            class="font-bold text-slate-900"
                                                            x-text="item.producto || '—'"
                                                        ></p>

                                                        <p
                                                            class="text-[11px] text-slate-500"
                                                            x-text="'Código: '+(item.producto_codigo || '—')"
                                                        ></p>
                                                    </td>

                                                    <td class="px-3 py-3" x-text="item.bodega || '—'"></td>
                                                    <td class="px-3 py-3" x-text="item.cantidad || 0"></td>

                                                    <td
                                                        class="px-3 py-3"
                                                        x-text="'Q '+money(item.costo_unitario)"
                                                    ></td>

                                                    <td
                                                        class="px-3 py-3 font-bold"
                                                        x-text="'Q '+money(item.total)"
                                                    ></td>

                                                    <td
                                                        class="px-3 py-3"
                                                        x-text="item.fecha_asignacion || '—'"
                                                    ></td>

                                                    <td class="px-3 py-3">
                                                        <span
                                                            class="rounded-full px-2 py-1 text-[11px] font-bold"
                                                            :class="badgeVidaUtil(item.estado_vida_util)"
                                                            x-text="item.estado_vida_util || '—'"
                                                        ></span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <footer class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs">
                                    <span>
                                        <span x-text="asignacionesPaginadas().length"></span>
                                        de
                                        <span x-text="asignaciones.length"></span>
                                    </span>

                                    <template x-if="totalPaginasAsignaciones()>1">
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                :disabled="asignacionesPage<=1"
                                                @click="cambiarPaginaAsignaciones(asignacionesPage-1)"
                                                class="rounded-lg border bg-white px-3 py-1 disabled:opacity-40"
                                            >
                                                ‹
                                            </button>

                                            <span>
                                                <span x-text="asignacionesPage"></span>/<span x-text="totalPaginasAsignaciones()"></span>
                                            </span>

                                            <button
                                                type="button"
                                                :disabled="asignacionesPage>=totalPaginasAsignaciones()"
                                                @click="cambiarPaginaAsignaciones(asignacionesPage+1)"
                                                class="rounded-lg border bg-white px-3 py-1 disabled:opacity-40"
                                            >
                                                ›
                                            </button>
                                        </div>
                                    </template>
                                </footer>
                            </div>
                        </template>
                    </section>
                </div>

                {{-- Vehículo --}}
                <div x-show="expedienteTab==='vehiculo'" x-cloak>
                    <section class="overflow-hidden rounded-2xl border border-slate-200">
                        <header class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-extrabold text-slate-950">
                                    Vehículo asignado
                                </h3>

                                <p class="text-xs text-slate-500">
                                    Vehículo y artículos asociados al colaborador.
                                </p>
                            </div>

                            <template x-if="vehiculoAsignado">
                                <span class="text-xs font-bold text-blue-700">
                                    <span x-text="productosVehiculo.length"></span> artículos ·
                                    Q <span x-text="money(totalProductosVehiculo)"></span>
                                </span>
                            </template>
                        </header>

                        <template x-if="!vehiculoAsignado">
                            <div class="p-5 text-sm text-slate-500">
                                Este colaborador no tiene vehículo activo asignado.
                            </div>
                        </template>

                        <template x-if="vehiculoAsignado">
                            <div>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[850px] text-xs">
                                        <thead class="bg-slate-50 text-slate-500">
                                            <tr>
                                                <th class="px-3 py-3 text-left">Vehículo</th>
                                                <th class="px-3 py-3 text-left">Placa</th>
                                                <th class="px-3 py-3 text-left">VIN</th>
                                                <th class="px-3 py-3 text-left">Fecha</th>
                                                <th class="px-3 py-3 text-left">Estado</th>
                                                <th class="px-3 py-3 text-left">Artículos</th>
                                                <th class="px-3 py-3 text-left">Monto</th>
                                                <th class="px-3 py-3 text-right">Detalle</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-3 py-3 font-bold" x-text="vehicleTitle()"></td>

                                                <td
                                                    class="px-3 py-3"
                                                    x-text="vehiculoAsignado.placa || '—'"
                                                ></td>

                                                <td
                                                    class="break-all px-3 py-3"
                                                    x-text="vehiculoAsignado.vin || '—'"
                                                ></td>

                                                <td
                                                    class="px-3 py-3"
                                                    x-text="vehiculoAsignado.fecha_asignacion || '—'"
                                                ></td>

                                                <td
                                                    class="px-3 py-3"
                                                    x-text="vehiculoAsignado.estado || 'Activa'"
                                                ></td>

                                                <td
                                                    class="px-3 py-3 font-bold"
                                                    x-text="productosVehiculo.length"
                                                ></td>

                                                <td class="px-3 py-3 font-bold">
                                                    Q <span x-text="money(totalProductosVehiculo)"></span>
                                                </td>

                                                <td class="px-3 py-3 text-right">
                                                    <button
                                                        type="button"
                                                        @click="vehiculoDetalleOpen=!vehiculoDetalleOpen"
                                                        class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 font-bold text-blue-700"
                                                    >
                                                        <span x-text="vehiculoDetalleOpen ? 'Ocultar' : 'Ver artículos'"></span>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div
                                    x-show="vehiculoDetalleOpen"
                                    x-cloak
                                    class="border-t border-slate-200 bg-slate-50 p-4"
                                >
                                    <template x-if="productosVehiculo.length===0">
                                        <div class="rounded-xl border bg-white p-4 text-sm text-slate-500">
                                            No hay productos activos para este vehículo.
                                        </div>
                                    </template>

                                    <template x-if="productosVehiculo.length>0">
                                        <div>
                                            <div class="overflow-x-auto rounded-xl border bg-white">
                                                <table class="w-full min-w-[900px] text-xs">
                                                    <thead class="bg-slate-50 text-slate-500">
                                                        <tr>
                                                            <th class="px-3 py-3 text-left">Artículo</th>
                                                            <th class="px-3 py-3 text-left">Bodega</th>
                                                            <th class="px-3 py-3 text-left">Cantidad</th>
                                                            <th class="px-3 py-3 text-left">Costo</th>
                                                            <th class="px-3 py-3 text-left">Total</th>
                                                            <th class="px-3 py-3 text-left">Fecha</th>
                                                            <th class="px-3 py-3 text-left">Motivo</th>
                                                            <th class="px-3 py-3 text-left">Estado</th>
                                                        </tr>
                                                    </thead>

                                                    <tbody class="divide-y divide-slate-100">
                                                        <template
                                                            x-for="item in productosVehiculoPaginados()"
                                                            :key="'vehiculo-'+item._index"
                                                        >
                                                            <tr class="hover:bg-slate-50">
                                                                <td class="px-3 py-3">
                                                                    <p
                                                                        class="font-bold"
                                                                        x-text="item.producto || '—'"
                                                                    ></p>

                                                                    <p
                                                                        class="text-[11px] text-slate-500"
                                                                        x-text="'Código: '+(item.producto_codigo || '—')"
                                                                    ></p>
                                                                </td>

                                                                <td class="px-3 py-3" x-text="item.bodega || '—'"></td>
                                                                <td class="px-3 py-3" x-text="item.cantidad || 0"></td>

                                                                <td
                                                                    class="px-3 py-3"
                                                                    x-text="'Q '+money(item.costo_unitario)"
                                                                ></td>

                                                                <td
                                                                    class="px-3 py-3 font-bold"
                                                                    x-text="'Q '+money(item.total)"
                                                                ></td>

                                                                <td class="px-3 py-3" x-text="item.fecha || '—'"></td>
                                                                <td class="px-3 py-3" x-text="item.motivo || '—'"></td>
                                                                <td class="px-3 py-3" x-text="item.estado || 'Activo'"></td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <footer class="mt-2 flex items-center justify-between rounded-xl border bg-white px-3 py-2 text-xs">
                                                <span>
                                                    <span x-text="productosVehiculoPaginados().length"></span>
                                                    de
                                                    <span x-text="productosVehiculo.length"></span>
                                                </span>

                                                <template x-if="totalPaginasProductosVehiculo()>1">
                                                    <div class="flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            :disabled="productosVehiculoPage<=1"
                                                            @click="cambiarPaginaProductosVehiculo(productosVehiculoPage-1)"
                                                            class="rounded-lg border px-3 py-1 disabled:opacity-40"
                                                        >
                                                            ‹
                                                        </button>

                                                        <span>
                                                            <span x-text="productosVehiculoPage"></span>/<span x-text="totalPaginasProductosVehiculo()"></span>
                                                        </span>

                                                        <button
                                                            type="button"
                                                            :disabled="productosVehiculoPage>=totalPaginasProductosVehiculo()"
                                                            @click="cambiarPaginaProductosVehiculo(productosVehiculoPage+1)"
                                                            class="rounded-lg border px-3 py-1 disabled:opacity-40"
                                                        >
                                                            ›
                                                        </button>
                                                    </div>
                                                </template>
                                            </footer>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </section>
                </div>

                {{-- Descuentos --}}
                <div x-show="expedienteTab==='descuentos'" x-cloak>
                    <section class="overflow-hidden rounded-2xl border border-rose-200">
                        <header class="flex flex-col gap-3 border-b border-rose-200 bg-rose-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-extrabold text-rose-950">
                                    Descuentos pendientes
                                </h3>

                                <p class="text-xs text-rose-700">
                                    Casos pendientes de revisión por Recursos Humanos.
                                </p>
                            </div>

                            <p class="text-sm font-extrabold text-rose-950">
                                Q <span x-text="money(totalCobros)"></span>
                                · <span x-text="cobros.length"></span> casos
                            </p>
                        </header>

                        <template x-if="cobros.length===0">
                            <div class="p-5 text-sm text-slate-500">
                                No tiene descuentos pendientes de revisión.
                            </div>
                        </template>

                        <template x-if="cobros.length>0">
                            <div>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[750px] text-xs">
                                        <thead class="bg-slate-50 text-slate-500">
                                            <tr>
                                                <th class="px-3 py-3 text-left">Artículo</th>
                                                <th class="px-3 py-3 text-left">Razón</th>
                                                <th class="px-3 py-3 text-left">Monto</th>
                                                <th class="px-3 py-3 text-left">Estado</th>
                                                <th class="px-3 py-3 text-right">Acción</th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-slate-100">
                                            <template
                                                x-for="cobro in cobrosPaginados()"
                                                :key="'cobro-'+cobro._index"
                                            >
                                                <tr class="hover:bg-slate-50">
                                                    <td class="px-3 py-3">
                                                        <p
                                                            class="font-bold"
                                                            x-text="cobro.producto || '—'"
                                                        ></p>

                                                        <p
                                                            class="text-[11px] text-slate-500"
                                                            x-text="'Código: '+(cobro.producto_codigo || '—')"
                                                        ></p>
                                                    </td>

                                                    <td
                                                        class="px-3 py-3"
                                                        x-text="razonCobro(cobro)"
                                                    ></td>

                                                    <td class="px-3 py-3 font-bold text-rose-700">
                                                        Q <span x-text="money(cobro.monto_cobro)"></span>
                                                    </td>

                                                    <td class="px-3 py-3">
                                                        <span
                                                            class="rounded-full px-2 py-1 font-bold"
                                                            :class="badgeCobro(cobro.estado)"
                                                            x-text="estadoCobroTexto(cobro.estado)"
                                                        ></span>
                                                    </td>

                                                    <td class="px-3 py-3 text-right">
                                                        <button
                                                            type="button"
                                                            @click="seleccionarCobroRRHH(cobro._index); abrirCentroRRHH()"
                                                            class="rounded-lg bg-rose-600 px-3 py-2 font-bold text-white hover:bg-rose-700"
                                                        >
                                                            Revisar
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <footer class="flex items-center justify-between border-t border-rose-100 bg-rose-50 px-4 py-2 text-xs text-rose-700">
                                    <span>
                                        <span x-text="cobrosPaginados().length"></span>
                                        de
                                        <span x-text="cobros.length"></span>
                                    </span>

                                    <template x-if="totalPaginasCobros()>1">
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                :disabled="cobrosPage<=1"
                                                @click="cambiarPaginaCobros(cobrosPage-1)"
                                                class="rounded-lg border border-rose-200 bg-white px-3 py-1 disabled:opacity-40"
                                            >
                                                ‹
                                            </button>

                                            <span>
                                                <span x-text="cobrosPage"></span>/<span x-text="totalPaginasCobros()"></span>
                                            </span>

                                            <button
                                                type="button"
                                                :disabled="cobrosPage>=totalPaginasCobros()"
                                                @click="cambiarPaginaCobros(cobrosPage+1)"
                                                class="rounded-lg border border-rose-200 bg-white px-3 py-1 disabled:opacity-40"
                                            >
                                                ›
                                            </button>
                                        </div>
                                    </template>
                                </footer>
                            </div>
                        </template>
                    </section>
                </div>

            </div>
        </div>
    </section>
</div>