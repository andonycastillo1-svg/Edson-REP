{{-- MODAL REVISIÓN RRHH --}}
<div
    x-show="centroRRHHOpen"
    x-cloak
    x-transition.opacity
    @keydown.escape.window="cerrarCentroRRHH()"
    class="fixed inset-0 z-[80] flex items-center justify-center p-4"
>
    <div
        class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
        @click="cerrarCentroRRHH()"
    ></div>

    <section class="relative flex max-h-[88vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">

        {{-- Encabezado --}}
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-600">
                    Revisión RRHH
                </p>

                <h2 class="mt-1 text-lg font-bold text-slate-900">
                    Descuento sugerido
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Información principal del caso.
                </p>
            </div>

            <button
                type="button"
                @click="cerrarCentroRRHH()"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-lg text-slate-400 transition hover:bg-slate-50 hover:text-slate-700"
                aria-label="Cerrar"
            >
                ×
            </button>
        </header>

        {{-- Selector --}}
        <div
            x-show="cobros.length > 1"
            class="border-b border-slate-200 bg-slate-50/60 px-5 py-3"
        >
            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                Caso
            </label>

            <select
                x-model.number="cobroActivoIndex"
                @change="seleccionarCobroRRHH(cobroActivoIndex)"
                class="w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
            >
                <template x-for="(cobro, index) in cobros" :key="'caso-'+index">
                    <option
                        :value="index"
                        x-text="`${cobro.producto || 'Sin producto'} · Q ${money(cobro.monto_cobro)}`"
                    ></option>
                </template>
            </select>
        </div>

        {{-- Contenido --}}
        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">

            <template x-if="cobros.length === 0">
                <div class="py-8 text-center">
                    <p class="text-sm font-semibold text-slate-800">
                        No hay casos pendientes
                    </p>

                    <p class="mt-1 text-xs text-slate-500">
                        No existen descuentos por revisar.
                    </p>
                </div>
            </template>

            <template x-if="cobroActivo">
                <div>

                    {{-- Producto y monto --}}
                    <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3
                                class="truncate text-base font-semibold text-slate-900"
                                x-text="cobroActivo.producto || '—'"
                            ></h3>

                            <p class="mt-1 text-xs text-slate-500">
                                Código:
                                <span
                                    class="font-medium text-slate-700"
                                    x-text="cobroActivo.producto_codigo || '—'"
                                ></span>
                            </p>
                        </div>

                        <div class="shrink-0 sm:text-right">
                            <p class="text-[11px] font-medium text-rose-600">
                                Monto sugerido
                            </p>

                            <p class="mt-0.5 text-xl font-bold text-rose-700">
                                Q <span x-text="money(cobroActivo.monto_cobro)"></span>
                            </p>
                        </div>
                    </div>

                    {{-- Datos --}}
                    <dl class="grid grid-cols-1 divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div class="py-3 sm:pr-4">
                            <dt class="text-[11px] font-medium text-slate-500">
                                Costo original
                            </dt>

                            <dd class="mt-1 text-sm font-semibold text-slate-900">
                                Q <span x-text="money(cobroActivo.costo_producto)"></span>
                            </dd>
                        </div>

                        <div class="py-3 sm:px-4">
                            <dt class="text-[11px] font-medium text-slate-500">
                                Vida útil / restante
                            </dt>

                            <dd class="mt-1 text-sm font-semibold text-slate-900">
                                <span x-text="cobroActivo.vida_util_meses || 0"></span>
                                /
                                <span x-text="cobroActivo.meses_restantes || 0"></span>
                                meses
                            </dd>
                        </div>

                        <div class="py-3 sm:pl-4">
                            <dt class="text-[11px] font-medium text-slate-500">
                                Estado
                            </dt>

                            <dd class="mt-1">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold"
                                    :class="badgeCobro(cobroActivo.estado)"
                                    x-text="estadoCobroTexto(cobroActivo.estado)"
                                ></span>
                            </dd>
                        </div>
                    </dl>

                    {{-- Justificación --}}
                    <div class="border-t border-slate-200 pt-4">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                            Justificación
                        </p>

                        <p
                            class="mt-2 border-l-2 border-slate-300 pl-3 text-sm leading-6 text-slate-600"
                            x-text="cobroActivo.detalle || 'Sin justificación registrada.'"
                        ></p>
                    </div>

                </div>
            </template>

        </div>
    </section>
</div>