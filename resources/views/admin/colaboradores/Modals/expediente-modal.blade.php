{{-- MODAL EXPEDIENTE ADMINISTRATIVO --}}
<div x-show="modalOpen"
     x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center p-2">

  <div class="absolute inset-0 bg-black/40" @click="closeModal()"></div>

  <div class="relative w-[98vw] max-w-none rounded-2xl bg-white shadow-xl max-h-[96vh] overflow-hidden">

    {{-- HEADER --}}
    <div class="border-b border-slate-200 bg-white">
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between px-4 py-3">
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wide text-blue-600">
            Expediente administrativo
          </div>

          <div class="mt-1 text-base font-bold text-slate-900" x-text="detalle.nombre || 'Cargando...'"></div>

          <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-600">
            <span>Código: <strong x-text="detalle.codigo || '—'"></strong></span>
            <span>Puesto: <strong x-text="detalle.puesto || '—'"></strong></span>
            <span>Estado: <strong x-text="detalle.estado || '—'"></strong></span>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <a x-show="detalle.codigo"
             :href="`/${routePrefix}/colaboradores/${detalle.codigo}/ficha-tecnica`"
             target="_blank"
             class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition">
            Descargar ficha
          </a>

          <button type="button"
                  @click="closeModal()"
                  class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-800">
            Cerrar ✕
          </button>
        </div>
      </div>

      {{-- PESTAÑAS --}}
      <div class="flex gap-1 overflow-x-auto border-t border-slate-100 px-4 py-2">
        <button type="button"
                class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold"
                :class="expedienteTab === 'resumen' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                @click="expedienteTab = 'resumen'">
          Resumen
        </button>

        <button type="button"
                class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold"
                :class="expedienteTab === 'productos' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                @click="expedienteTab = 'productos'">
          Productos directos
          <span class="ml-1 rounded-full bg-white/30 px-2 py-0.5" x-text="asignaciones.length"></span>
        </button>

        <button type="button"
                class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold"
                :class="expedienteTab === 'vehiculo' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                @click="expedienteTab = 'vehiculo'">
          Vehículo
          <span class="ml-1 rounded-full bg-white/30 px-2 py-0.5" x-text="productosVehiculo.length"></span>
        </button>

        <button type="button"
                class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold"
                :class="expedienteTab === 'descuentos' ? 'bg-rose-700 text-white' : 'bg-rose-50 text-rose-700 hover:bg-rose-100'"
                @click="expedienteTab = 'descuentos'">
          Descuentos RRHH
          <span class="ml-1 rounded-full bg-white/30 px-2 py-0.5" x-text="cobros.length"></span>
        </button>
      </div>
    </div>

    {{-- BODY --}}
    <div class="max-h-[78vh] overflow-y-auto p-3">

      <div x-show="loadingDetalle"
           class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
        Cargando expediente...
      </div>

      <div x-show="!loadingDetalle">

        {{-- TAB RESUMEN --}}
        <div x-show="expedienteTab === 'resumen'" class="space-y-3">

          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <button type="button"
                    @click="expedienteTab = 'productos'"
                    class="text-left rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 hover:bg-slate-100">
              <div class="text-xs font-semibold text-slate-500">Productos directos</div>
              <div class="mt-1 text-2xl font-bold text-slate-900" x-text="asignaciones.length"></div>
              <div class="text-sm text-slate-600">
                Q <span class="font-semibold" x-text="money(totalInventario)"></span>
              </div>
            </button>

            <button type="button"
                    @click="expedienteTab = 'vehiculo'"
                    class="text-left rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 hover:bg-blue-100">
              <div class="text-xs font-semibold text-blue-700">Vehículo asignado</div>
              <div class="mt-1 text-lg font-bold text-blue-950" x-text="vehiculoAsignado ? vehicleTitle() : 'Sin vehículo'"></div>
              <div class="text-sm text-blue-800">
                Monto artículos: Q <span class="font-semibold" x-text="money(totalProductosVehiculo)"></span>
              </div>
            </button>

            <button type="button"
                    @click="expedienteTab = 'vehiculo'; vehiculoDetalleOpen = true"
                    class="text-left rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 hover:bg-indigo-100">
              <div class="text-xs font-semibold text-indigo-700">Artículos en vehículo</div>
              <div class="mt-1 text-2xl font-bold text-indigo-950" x-text="productosVehiculo.length"></div>
              <div class="text-sm text-indigo-800">
                Q <span class="font-semibold" x-text="money(totalProductosVehiculo)"></span>
              </div>
            </button>

            <button type="button"
                    @click="expedienteTab = 'descuentos'"
                    class="text-left rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 hover:bg-rose-100">
              <div class="text-xs font-semibold text-rose-700">Descuentos RRHH</div>
              <div class="mt-1 text-2xl font-bold text-rose-950">
                Q <span x-text="money(totalCobros)"></span>
              </div>
              <div class="text-sm text-rose-800">
                Casos: <span class="font-semibold" x-text="cobros.length"></span>
              </div>
            </button>
          </div>

          <div class="rounded-xl border border-slate-200 bg-slate-900 px-4 py-4 text-white">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
              <div>
                <div class="text-sm font-semibold">Total general asignado</div>
                <div class="text-xs text-slate-300">
                  Productos directos + productos asociados al vehículo.
                </div>
              </div>

              <div class="text-xl font-bold">
                Q <span x-text="money(totalGeneral)"></span>
              </div>
            </div>
          </div>
        </div>

        {{-- TAB PRODUCTOS DIRECTOS --}}
        <div x-show="expedienteTab === 'productos'">
          <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between border-b border-slate-200 px-3 py-2">
              <div>
                <h3 class="text-sm font-semibold text-slate-900">Productos asignados directamente</h3>
                <p class="text-xs text-slate-500">Artículos bajo responsabilidad directa del colaborador.</p>
              </div>

              <div class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">
                <span x-text="asignaciones.length"></span>
                productos |
                Q <span x-text="money(totalInventario)"></span>
              </div>
            </div>

            <template x-if="asignaciones.length === 0">
              <div class="px-3 py-3 text-sm text-slate-600">
                No tiene productos asignados directamente.
              </div>
            </template>

            <template x-if="asignaciones.length > 0">
              <div>
                <div class="overflow-x-auto">
                  <table class="w-full text-xs">
                    <thead class="bg-slate-100 text-slate-600">
                      <tr>
                        <th class="px-3 py-2 text-left font-semibold">Artículo</th>
                        <th class="px-3 py-2 text-left font-semibold">Bodega</th>
                        <th class="px-3 py-2 text-left font-semibold">Cant.</th>
                        <th class="px-3 py-2 text-left font-semibold">Costo</th>
                        <th class="px-3 py-2 text-left font-semibold">Total</th>
                        <th class="px-3 py-2 text-left font-semibold">Asignado</th>
                        <th class="px-3 py-2 text-left font-semibold">Estado</th>
                      </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200">
                      <template x-for="item in asignacionesPaginadas()" :key="'asignacion-' + item._index">
                        <tr class="align-top hover:bg-slate-50">
                          <td class="px-3 py-2">
                            <div class="font-semibold text-slate-900" x-text="item.producto || '—'"></div>
                            <div class="text-[11px] text-slate-500" x-text="'Código: ' + (item.producto_codigo || '—')"></div>
                          </td>

                          <td class="px-3 py-2 text-slate-700" x-text="item.bodega || '—'"></td>
                          <td class="px-3 py-2 text-slate-700" x-text="item.cantidad || 0"></td>
                          <td class="px-3 py-2 text-slate-700" x-text="'Q ' + money(item.costo_unitario)"></td>
                          <td class="px-3 py-2 font-semibold text-slate-900" x-text="'Q ' + money(item.total)"></td>
                          <td class="px-3 py-2 text-slate-700" x-text="item.fecha_asignacion || '—'"></td>

                          <td class="px-3 py-2">
                            <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold"
                                  :class="badgeVidaUtil(item.estado_vida_util)"
                                  x-text="item.estado_vida_util || '—'"></span>
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>

                <div class="flex flex-col gap-2 border-t border-slate-200 bg-slate-50 px-3 py-2 md:flex-row md:items-center md:justify-between">
                  <div class="text-[11px] text-slate-600">
                    Mostrando
                    <span class="font-semibold" x-text="asignacionesPaginadas().length"></span>
                    de
                    <span class="font-semibold" x-text="asignaciones.length"></span>
                    productos.
                  </div>

                  <template x-if="totalPaginasAsignaciones() > 1">
                    <div class="flex items-center gap-1">
                      <button type="button"
                              class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-40"
                              :disabled="asignacionesPage <= 1"
                              @click="cambiarPaginaAsignaciones(asignacionesPage - 1)">
                        ‹
                      </button>

                      <div class="px-2 text-[11px] font-semibold text-slate-700">
                        <span x-text="asignacionesPage"></span>/<span x-text="totalPaginasAsignaciones()"></span>
                      </div>

                      <button type="button"
                              class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-40"
                              :disabled="asignacionesPage >= totalPaginasAsignaciones()"
                              @click="cambiarPaginaAsignaciones(asignacionesPage + 1)">
                        ›
                      </button>
                    </div>
                  </template>
                </div>
              </div>
            </template>
          </div>
        </div>

        {{-- TAB VEHÍCULO --}}
        <div x-show="expedienteTab === 'vehiculo'">
          <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between border-b border-slate-200 px-3 py-2">
              <div>
                <h3 class="text-sm font-semibold text-slate-900">Vehículo asignado</h3>
                <p class="text-xs text-slate-500">Vehículo bajo responsabilidad del colaborador y productos asociados.</p>
              </div>

              <template x-if="vehiculoAsignado">
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800">
                  <span x-text="productosVehiculo.length"></span>
                  artículos |
                  Q <span x-text="money(totalProductosVehiculo)"></span>
                </div>
              </template>
            </div>

            <template x-if="!vehiculoAsignado">
              <div class="px-3 py-3 text-sm text-slate-600">
                Este colaborador no tiene vehículo activo asignado.
              </div>
            </template>

            <template x-if="vehiculoAsignado">
              <div>
                <div class="overflow-x-auto">
                  <table class="w-full text-xs">
                    <thead class="bg-slate-100 text-slate-600">
                      <tr>
                        <th class="px-3 py-2 text-left font-semibold">Vehículo</th>
                        <th class="px-3 py-2 text-left font-semibold">Placa</th>
                        <th class="px-3 py-2 text-left font-semibold">VIN</th>
                        <th class="px-3 py-2 text-left font-semibold">Fecha</th>
                        <th class="px-3 py-2 text-left font-semibold">Estado</th>
                        <th class="px-3 py-2 text-left font-semibold">Artículos</th>
                        <th class="px-3 py-2 text-left font-semibold">Monto</th>
                        <th class="px-3 py-2 text-right font-semibold">Detalle</th>
                      </tr>
                    </thead>

                    <tbody>
                      <tr class="border-t align-top hover:bg-slate-50">
                        <td class="px-3 py-2">
                          <div class="font-semibold text-slate-900" x-text="vehicleTitle()"></div>
                          <div class="text-[11px] text-slate-500">Vehículo activo</div>
                        </td>

                        <td class="px-3 py-2 text-slate-700" x-text="vehiculoAsignado.placa || '—'"></td>

                        <td class="px-3 py-2 text-slate-700">
                          <span class="break-all" x-text="vehiculoAsignado.vin || '—'"></span>
                        </td>

                        <td class="px-3 py-2 text-slate-700" x-text="vehiculoAsignado.fecha_asignacion || '—'"></td>

                        <td class="px-3 py-2">
                          <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700"
                                x-text="vehiculoAsignado.estado || 'Activa'">
                          </span>
                        </td>

                        <td class="px-3 py-2 text-slate-700">
                          <span class="font-semibold" x-text="productosVehiculo.length"></span>
                        </td>

                        <td class="px-3 py-2 font-semibold text-slate-900">
                          Q <span x-text="money(totalProductosVehiculo)"></span>
                        </td>

                        <td class="px-3 py-2 text-right">
                          <button type="button"
                                  class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-[11px] font-semibold text-blue-700 hover:bg-blue-100"
                                  @click="vehiculoDetalleOpen = !vehiculoDetalleOpen">
                            <span x-text="vehiculoDetalleOpen ? 'Ocultar artículos' : 'Ver artículos'"></span>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div x-show="vehiculoDetalleOpen" x-cloak class="border-t border-slate-200 bg-slate-50">
                  <div class="px-3 py-3">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-2">
                      <div>
                        <h4 class="text-sm font-semibold text-slate-900">Productos asociados al vehículo</h4>
                        <p class="text-xs text-slate-500">Productos, herramientas o refacciones cargadas al vehículo activo.</p>
                      </div>

                      <div class="text-xs font-semibold text-indigo-700">
                        Total: Q <span x-text="money(totalProductosVehiculo)"></span>
                      </div>
                    </div>

                    <template x-if="productosVehiculo.length === 0">
                      <div class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-600">
                        No hay productos activos registrados para este vehículo.
                      </div>
                    </template>

                    <template x-if="productosVehiculo.length > 0">
                      <div>
                        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                          <table class="w-full text-xs">
                            <thead class="bg-white text-slate-600">
                              <tr>
                                <th class="px-3 py-2 text-left font-semibold">Artículo</th>
                                <th class="px-3 py-2 text-left font-semibold">Bodega</th>
                                <th class="px-3 py-2 text-left font-semibold">Cantidad</th>
                                <th class="px-3 py-2 text-left font-semibold">Costo</th>
                                <th class="px-3 py-2 text-left font-semibold">Total</th>
                                <th class="px-3 py-2 text-left font-semibold">Fecha</th>
                                <th class="px-3 py-2 text-left font-semibold">Motivo</th>
                                <th class="px-3 py-2 text-left font-semibold">Estado</th>
                              </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-200">
                              <template x-for="item in productosVehiculoPaginados()" :key="'producto-vehiculo-' + item._index">
                                <tr class="align-top hover:bg-slate-50">
                                  <td class="px-3 py-2">
                                    <div class="font-semibold text-slate-900" x-text="item.producto || '—'"></div>
                                    <div class="text-[11px] text-slate-500" x-text="'Código: ' + (item.producto_codigo || '—')"></div>
                                  </td>

                                  <td class="px-3 py-2 text-slate-700" x-text="item.bodega || '—'"></td>
                                  <td class="px-3 py-2 text-slate-700" x-text="item.cantidad || 0"></td>
                                  <td class="px-3 py-2 text-slate-700" x-text="'Q ' + money(item.costo_unitario)"></td>
                                  <td class="px-3 py-2 font-semibold text-slate-900" x-text="'Q ' + money(item.total)"></td>
                                  <td class="px-3 py-2 text-slate-700" x-text="item.fecha || '—'"></td>
                                  <td class="px-3 py-2 text-slate-700" x-text="item.motivo || '—'"></td>

                                  <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700"
                                          x-text="item.estado || 'Activo'">
                                    </span>
                                  </td>
                                </tr>
                              </template>
                            </tbody>
                          </table>
                        </div>

                        <div class="mt-2 flex flex-col gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 md:flex-row md:items-center md:justify-between">
                          <div class="text-[11px] text-slate-600">
                            Mostrando
                            <span class="font-semibold" x-text="productosVehiculoPaginados().length"></span>
                            de
                            <span class="font-semibold" x-text="productosVehiculo.length"></span>
                            artículos.
                          </div>

                          <template x-if="totalPaginasProductosVehiculo() > 1">
                            <div class="flex items-center gap-1">
                              <button type="button"
                                      class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-40"
                                      :disabled="productosVehiculoPage <= 1"
                                      @click="cambiarPaginaProductosVehiculo(productosVehiculoPage - 1)">
                                ‹
                              </button>

                              <div class="px-2 text-[11px] font-semibold text-slate-700">
                                <span x-text="productosVehiculoPage"></span>/<span x-text="totalPaginasProductosVehiculo()"></span>
                              </div>

                              <button type="button"
                                      class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-40"
                                      :disabled="productosVehiculoPage >= totalPaginasProductosVehiculo()"
                                      @click="cambiarPaginaProductosVehiculo(productosVehiculoPage + 1)">
                                ›
                              </button>
                            </div>
                          </template>
                        </div>
                      </div>
                    </template>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>

        {{-- TAB DESCUENTOS --}}
        <div x-show="expedienteTab === 'descuentos'">
          <div class="rounded-xl border border-rose-200 bg-white overflow-hidden">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between border-b border-rose-200 bg-rose-50 px-3 py-2">
              <div>
                <h3 class="text-sm font-semibold text-rose-950">Descuentos pendientes de revisión RRHH</h3>
                <p class="text-xs text-rose-700">
                  Montos sugeridos por daño o reemplazo antes de completar la vida útil.
                </p>
              </div>

              <div class="flex items-center gap-2">
                <div class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-right">
                  <div class="text-[11px] font-semibold text-rose-700">Total sugerido</div>
                  <div class="text-sm font-bold text-rose-950">
                    Q <span x-text="money(totalCobros)"></span>
                  </div>
                </div>

                <div class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-right">
                  <div class="text-[11px] font-semibold text-rose-700">Casos</div>
                  <div class="text-sm font-bold text-rose-950" x-text="cobros.length"></div>
                </div>
              </div>
            </div>

            <template x-if="cobros.length === 0">
              <div class="px-3 py-3 text-sm text-slate-600">
                Este colaborador no tiene descuentos pendientes de revisión.
              </div>
            </template>

            <template x-if="cobros.length > 0">
              <div>
                <div class="overflow-x-auto">
                  <table class="w-full text-xs">
                    <thead class="bg-slate-100 text-slate-600">
                      <tr>
                        <th class="px-3 py-2 text-left font-semibold">Artículo</th>
                        <th class="px-3 py-2 text-left font-semibold">Razón</th>
                        <th class="px-3 py-2 text-left font-semibold">Monto sugerido</th>
                        <th class="px-3 py-2 text-left font-semibold">Estado</th>
                        <th class="px-3 py-2 text-right font-semibold">Acción</th>
                      </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200">
                      <template x-for="cobro in cobrosPaginados()" :key="'cobro-visible-' + cobro._index">
                        <tr class="align-top hover:bg-slate-50">
                          <td class="px-3 py-2">
                            <div class="font-semibold text-slate-900" x-text="cobro.producto || '—'"></div>
                            <div class="text-[11px] text-slate-500">
                              Código: <span x-text="cobro.producto_codigo || '—'"></span>
                            </div>
                          </td>

                          <td class="px-3 py-2 text-slate-700" x-text="razonCobro(cobro)"></td>

                          <td class="px-3 py-2">
                            <template x-if="Number(cobro.monto_cobro || 0) > 0">
                              <div class="font-bold text-rose-700">
                                Q <span x-text="money(cobro.monto_cobro)"></span>
                              </div>
                            </template>

                            <template x-if="Number(cobro.monto_cobro || 0) <= 0">
                              <div>
                                <div class="font-semibold text-slate-700">Q 0.00</div>
                                <div class="text-[11px] text-slate-500">Sin cobro</div>
                              </div>
                            </template>
                          </td>

                          <td class="px-3 py-2">
                            <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold"
                                  :class="badgeCobro(cobro.estado)"
                                  x-text="estadoCobroTexto(cobro.estado)">
                            </span>
                          </td>

                          <td class="px-3 py-2 text-right">
                            <button type="button"
                                    class="rounded-lg bg-rose-700 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-rose-800"
                                    @click="seleccionarCobroRRHH(cobro._index); abrirCentroRRHH();">
                              Revisar
                            </button>
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>

                <div class="flex flex-col gap-1 border-t border-rose-100 bg-rose-50 px-3 py-1.5 md:flex-row md:items-center md:justify-between">
                  <div class="text-[10px] text-rose-700">
                    <span x-text="cobrosPaginados().length"></span>
                    de
                    <span x-text="cobros.length"></span>
                    registros
                  </div>

                  <template x-if="totalPaginasCobros() > 1">
                    <div class="flex items-center gap-1">
                      <button type="button"
                              class="rounded-md border border-rose-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-40"
                              :disabled="cobrosPage <= 1"
                              @click="cambiarPaginaCobros(cobrosPage - 1)">
                        ‹
                      </button>

                      <div class="px-1 text-[10px] font-semibold text-rose-800">
                        <span x-text="cobrosPage"></span>/<span x-text="totalPaginasCobros()"></span>
                      </div>

                      <button type="button"
                              class="rounded-md border border-rose-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-40"
                              :disabled="cobrosPage >= totalPaginasCobros()"
                              @click="cambiarPaginaCobros(cobrosPage + 1)">
                        ›
                      </button>
                    </div>
                  </template>
                </div>
              </div>
            </template>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>