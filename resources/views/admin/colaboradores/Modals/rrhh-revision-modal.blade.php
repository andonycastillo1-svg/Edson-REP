{{-- MODAL CENTRO RRHH --}}
<div x-show="centroRRHHOpen"
     x-cloak
     class="fixed inset-0 z-[80] flex items-start justify-center p-2">

  <div class="absolute inset-0 bg-black/50" @click="cerrarCentroRRHH()"></div>

  <div class="relative w-[96vw] max-w-none max-h-[94vh] overflow-hidden rounded-2xl bg-white shadow-xl">

    {{-- HEADER --}}
    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between border-b border-slate-200 px-4 py-3">
      <div>
        <div class="text-[11px] font-semibold uppercase tracking-wide text-rose-600">
          Centro de revisión RRHH
        </div>

        <h3 class="mt-1 text-base font-bold text-slate-900">
          Descuento sugerido
        </h3>

        <p class="text-xs text-slate-500">
          Revisa el caso antes de aplicar, descartar o trasladar a nómina.
        </p>
      </div>

      <button type="button"
              class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-100"
              @click="cerrarCentroRRHH()">
        ✕
      </button>
    </div>

    {{-- BODY --}}
    <div class="grid max-h-[78vh] grid-cols-1 overflow-hidden md:grid-cols-12">

      {{-- LISTA IZQUIERDA --}}
      <div class="border-r border-slate-200 md:col-span-4 overflow-y-auto">
        <template x-if="cobros.length === 0">
          <div class="p-4 text-sm text-slate-600">
            No hay casos pendientes.
          </div>
        </template>

        <template x-if="cobros.length > 0">
          <div class="divide-y divide-slate-200">
            <template x-for="(cobro, index) in cobros" :key="'centro-rrhh-' + index">
              <button type="button"
                      class="w-full px-3 py-3 text-left hover:bg-slate-50"
                      :class="cobroActivoIndex === index ? 'bg-rose-50' : 'bg-white'"
                      @click="seleccionarCobroRRHH(index)">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="text-xs font-semibold text-slate-900 truncate" x-text="cobro.producto || '—'"></div>
                    <div class="mt-1 text-[11px] text-slate-500">
                      Código: <span x-text="cobro.producto_codigo || '—'"></span>
                    </div>
                  </div>

                  <div class="text-right shrink-0">
                    <div class="text-xs font-bold"
                         :class="Number(cobro.monto_cobro || 0) > 0 ? 'text-rose-700' : 'text-slate-500'">
                      Q <span x-text="money(cobro.monto_cobro)"></span>
                    </div>
                  </div>
                </div>

                <div class="mt-2">
                  <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold"
                        :class="badgeCobro(cobro.estado)"
                        x-text="estadoCobroTexto(cobro.estado)">
                  </span>
                </div>
              </button>
            </template>
          </div>
        </template>
      </div>

      {{-- DETALLE DERECHA --}}
      <div class="md:col-span-8 overflow-y-auto">
        <template x-if="!cobroActivo">
          <div class="p-4 text-sm text-slate-600">
            Selecciona un caso para revisar.
          </div>
        </template>

        <template x-if="cobroActivo">
          <div class="p-4">

            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
              <div>
                <h4 class="text-lg font-bold text-slate-900" x-text="cobroActivo.producto || '—'"></h4>
                <div class="mt-1 text-xs text-slate-500">
                  Código: <span x-text="cobroActivo.producto_codigo || '—'"></span>
                </div>
              </div>

              <div class="rounded-xl border px-4 py-3 text-right"
                   :class="Number(cobroActivo.monto_cobro || 0) > 0 ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-slate-50'">
                <div class="text-xs font-semibold"
                     :class="Number(cobroActivo.monto_cobro || 0) > 0 ? 'text-rose-700' : 'text-slate-600'">
                  Monto sugerido
                </div>

                <div class="mt-1 text-xl font-bold"
                     :class="Number(cobroActivo.monto_cobro || 0) > 0 ? 'text-rose-950' : 'text-slate-700'">
                  Q <span x-text="money(cobroActivo.monto_cobro)"></span>
                </div>

                <div class="mt-1 text-[11px] text-slate-500">
                  No aplicado automáticamente
                </div>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                <div class="text-[11px] font-semibold text-slate-500">Costo producto</div>
                <div class="mt-1 text-sm font-bold text-slate-900">
                  Q <span x-text="money(cobroActivo.costo_producto)"></span>
                </div>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                <div class="text-[11px] font-semibold text-slate-500">Vida útil</div>
                <div class="mt-1 text-sm font-bold text-slate-900" x-text="(cobroActivo.vida_util_meses || 0) + ' meses'"></div>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                <div class="text-[11px] font-semibold text-slate-500">Vida restante</div>
                <div class="mt-1 text-sm font-bold text-slate-900" x-text="(cobroActivo.meses_restantes || 0) + ' meses'"></div>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                <div class="text-[11px] font-semibold text-slate-500">Estado</div>
                <div class="mt-1">
                  <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold"
                        :class="badgeCobro(cobroActivo.estado)"
                        x-text="estadoCobroTexto(cobroActivo.estado)">
                  </span>
                </div>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
              <div class="rounded-xl border border-slate-200 px-3 py-3">
                <div class="text-[11px] font-semibold text-slate-500">Fecha de asignación anterior</div>
                <div class="mt-1 text-sm font-medium text-slate-900" x-text="cobroActivo.fecha_asignacion_anterior || '—'"></div>
              </div>

              <div class="rounded-xl border border-slate-200 px-3 py-3">
                <div class="text-[11px] font-semibold text-slate-500">Fecha de daño o reemplazo</div>
                <div class="mt-1 text-sm font-medium text-slate-900" x-text="cobroActivo.fecha_dano_reemplazo || '—'"></div>
              </div>
            </div>

            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
              <div class="text-[11px] font-semibold text-slate-500">
                Justificación registrada por el sistema
              </div>

              <div class="mt-2 text-sm leading-6 text-slate-700 whitespace-pre-line"
                   x-text="cobroActivo.detalle || 'Sin justificación registrada.'">
              </div>
            </div>

            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900">
              RRHH debe revisar este caso antes de aplicar cualquier descuento en nómina.
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
              <button type="button"
                      class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                No aplicar
              </button>

              <button type="button"
                      class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                Dejar pendiente
              </button>

              <button type="button"
                      class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">
                Aprobar descuento
              </button>
            </div>

            <div class="mt-2 text-right text-xs text-slate-500">
              Estos botones son visuales por ahora. Para guardar cambios hay que agregar rutas y controlador.
            </div>

          </div>
        </template>
      </div>

    </div>
  </div>
</div>