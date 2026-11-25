@extends('layouts.app')

@section('content')
@php
  $puedeGestionarPdfsFirmados = \Illuminate\Support\Facades\Schema::hasTable('asignacion_inventario_archivos');

  $asignacionesPorColaborador = $asignacionesPorColaborador ?? collect();
  $movimientos = $movimientos ?? collect();
  $pdfsDevolucionFirmados = $pdfsDevolucionFirmados ?? collect();
  $puedeCrearAsignaciones = $puedeCrearAsignaciones ?? false;
@endphp

<div class="space-y-4">
  <div class="min-w-0 space-y-4">
    @php
        $faltantes = collect($asignacionesPorColaborador)->flatMap(fn ($grupo) => $grupo['asignaciones'])
            ->filter(fn ($fila) => $fila->entrega && !$fila->entrega->documentos->contains('tipo_documento', 'asignacion_firmada'))
            ->pluck('grupo_asignacion')->unique()->count();
    @endphp
    <x-ui.encabezado titulo="Asignaciones y devoluciones" descripcion="Consulta entregas, devoluciones y respaldos pendientes.">
        <x-ui.exportar-excel modulo="asignaciones" />
        @if($puedeVerMovimientos)
            <x-ui.exportar-excel modulo="asignaciones" :filtros="['devoluciones' => 1]" texto="Exportar devoluciones" />
        @endif
        @if($puedeCrearAsignaciones)
            <x-ui.accion :href="route($routePrefix.'.asignaciones.create')" variante="primaria">Nueva asignación</x-ui.accion>
        @endif
    </x-ui.encabezado>
    <x-sistema.enlace-datos modulo="asignaciones" />
    <x-sistema.consulta-campos :tabla="$tablaCampos" :conservar="['seguimiento']" />

    <nav class="ui-status-tabs mb-4" aria-label="Seguimiento de entregas">
        @foreach(['' => 'Todas las asignaciones', 'documentacion' => 'Hojas firmadas faltantes'] as $filtro => $etiqueta)
            <a href="{{ route($routePrefix.'.asignaciones.index', array_filter(['seguimiento' => $filtro]) + request()->only('campo')) }}"
               @if(request('seguimiento', '') === $filtro) aria-current="page" @endif class="flujo-tab gap-2">
                {{ $etiqueta }}
                @if($filtro && $faltantes > 0)<x-ui.estado variante="pendiente">{{ $faltantes }}</x-ui.estado>@endif
            </a>
        @endforeach
    </nav>

    @if(session('grupo_devolucion'))
        @rutaDisponible($routePrefix.'.asignaciones.hoja_devolucion')
            <a class="ui-btn-download mb-4" href="{{ route($routePrefix.'.asignaciones.hoja_devolucion', session('grupo_devolucion')) }}"
               target="_blank" rel="noopener noreferrer">Ver hoja de devolución</a>
        @endrutaDisponible
    @endif

    <div class="ui-panel overflow-hidden">
      <div class="border-b border-line bg-subtle px-4 py-3 text-base font-semibold text-ink">
        Colaboradores con asignaciones
      </div>

      @if(blank($asignacionesPorColaborador))
        <div class="ui-empty-state m-4">
          No hay asignaciones disponibles para este usuario.
        </div>
      @else
        <?php foreach ($asignacionesPorColaborador as $grupo): ?>
          @php
            $bulkFormId = 'bulk-return-' . $grupo['colaborador_codigo'];
            $puedeSeleccionar = $grupo['devolvibles_lote']->isNotEmpty();
          @endphp

          @if($puedeSeleccionar)
            <form id="{{ $bulkFormId }}" method="POST" action="{{ route($routePrefix . '.asignaciones.devolver_lote') }}"
                  data-confirmar="¿Registrar la resolución de los productos seleccionados?">
              @csrf
              <input type="hidden" name="solicitud_id" value="{{ \Illuminate\Support\Str::uuid() }}">
            </form>
          @endif

          <details class="asg-collab">
            <summary>
              <div class="asg-row">
                <div class="asg-left">
                  <x-ui.icono nombre="flecha" class="asg-arrow" />

                  <div class="asg-avatar">
                    {{ strtoupper(mb_substr($grupo['colaborador_nombre'] ?? 'C', 0, 1)) }}
                  </div>

                  <div>
                    <p class="asg-name">{{ $grupo['colaborador_nombre'] }}</p>
                    <p class="asg-code">Código: <strong>{{ $grupo['colaborador_codigo'] }}</strong></p>
                  </div>
                </div>

                <div class="asg-metrics">
                  <div>
                    <span class="asg-metric-label">Asignaciones</span>
                    <span class="asg-metric-value">{{ $grupo['asignaciones']->count() }}</span>
                  </div>

                  <div>
                    <span class="asg-metric-label">Activas</span>
                    <span class="ui-badge ui-badge-info">{{ $grupo['total_activo'] }}</span>
                  </div>

                  @if($grupo['sin_retorno'] > 0)
                    <div>
                      <span class="asg-metric-label">Custodia anterior sin retorno</span>
                      <span class="ui-badge ui-badge-info">{{ $grupo['sin_retorno'] }}</span>
                    </div>
                  @endif
                  <span class="ui-btn-secondary ui-btn-sm">Ver detalle</span>
                </div>
              </div>
            </summary>

            <div class="asg-detail">

              @if($grupo['puede_devolver_todo'])
              <details class="ui-card mb-3 p-3">
                <summary class="cursor-pointer font-bold">Resolver todo lo activo con la misma condición</summary>
                <form method="POST" class="mt-3 space-y-3"
                      action="{{ route($routePrefix.'.asignaciones.devolver_todo_colaborador', $grupo['colaborador_codigo']) }}"
                      data-confirmar="¿Aplicar esta condición a todos los productos bajo custodia?">
                  @csrf
                  <input type="hidden" name="solicitud_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                  @include('admin.asignaciones.motivo-devolucion')
                  @include('admin.asignaciones.condicion')
                  <button type="submit" class="ui-btn-danger">
                    Registrar resolución total
                  </button>
                </form>
              </details>
              @endif

              <div class="min-w-0">
                <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Asignaciones de {{ $grupo['colaborador_nombre'] }}">
                  <table class="ui-table asg-table">
                    <thead>
                      <tr>
                        @if($puedeSeleccionar)<th>Resolver</th>@endif
                        <th>Producto</th>
                        <th>Fecha</th>
                        <th>Bodega</th>
                        <th>Custodia</th>
                        <th>Documentación</th>
                        <th>Vida útil</th>
                        <th style="text-align:center;">Activa</th>
                        @if($puedeSeleccionar)<th style="text-align:center;">Cantidad a resolver</th>@endif
                        <x-sistema.cabeceras-campos :tabla="$tablaCampos" />
                        <th style="text-align:center;">PDF</th>
                        <th>Evidencias</th>
                      </tr>
                    </thead>

                    <tbody>
                      <?php foreach ($grupo['asignaciones'] as $a): ?>
                        @php
                          $estado = $a->estado ?? 'Activa';
                          $productoNombre = optional($a->producto)->descripcion ?? optional($a->producto)->nombre ?? $a->producto_codigo;

                          $segundosVida = $vigencias[$a->id] ?? null;
                        @endphp

                        <tr>
                          @if($puedeSeleccionar)
                          <td>
                            @if($grupo['devolvibles_lote']->contains($a->id))
                              <input type="checkbox"
                                     name="seleccionadas[]"
                                     aria-label="Seleccionar {{ $productoNombre }} para devolución"
                                     value="{{ $a->id }}"
                                     form="{{ $bulkFormId }}"
                                     class="selector"
                                     data-target="devolucion_{{ $a->id }}"
                                     data-condicion-id="condicion_{{ $a->id }}">
                            @else
                              —
                            @endif
                          </td>
                          @endif

                          <td>
                            <div class="font-semibold text-ink">{{ $productoNombre }}</div>
                            <div class="mt-1 text-sm text-muted">COD: {{ $a->producto_codigo }}</div>
                          </td>

                          <td>
                            {{ $a->fecha ? date('d/m/Y', strtotime($a->fecha)) : '—' }}
                          </td>

                          <td>
                            {{ optional($a->bodega)->nombre ?? '—' }}
                          </td>

                          <td>
                            @if($estado === 'Activa' && $a->sustituida_por_id)
                              <span class="ui-badge ui-badge-warning">{{ $a->custodia_etiqueta }}</span>
                            @elseif($estado === 'Activa')
                              <span class="ui-badge ui-badge-success">Activa</span>
                            @elseif($estado === 'Dañada')
                              <span class="ui-badge">Cerrada · daño</span>
                            @else
                              <span class="ui-badge">{{ $estado }}</span>
                            @endif
                          </td>

                          <td>
                            @if($a->entrega)
                              <div>{{ $a->entrega->documentos->contains('tipo_documento', 'asignacion_firmada') ? 'Hoja firmada cargada' : 'Hoja firmada faltante' }}</div>
                            @else
                              <div>{{ $a->evidencias->contains('tipo_documento', 'asignacion_firmada') ? 'Hoja firmada cargada' : 'Sin hoja registrada' }}</div>
                            @endif
                          </td>

                          <td>
                            @if($estado !== 'Activa')
                              <span>—</span>
                            @elseif($segundosVida > 0)
                              <span class="ui-badge ui-badge-success">Vigente</span>
                            @elseif($segundosVida !== null)
                              <span class="ui-badge ui-badge-warning">Vencida</span>
                            @else
                              <span>Desconocida</span>
                            @endif
                          </td>

                          <td style="text-align:center; font-weight:700;">
                            {{ $a->cantidad_asignada }}
                          </td>

                          @if($puedeSeleccionar)
                          <td style="text-align:center;">
                            @if($grupo['devolvibles_lote']->contains($a->id))
                              <input type="number"
                                     id="devolucion_{{ $a->id }}"
                                     name="devoluciones[{{ $a->id }}]"
                                     aria-label="Cantidad a devolver de {{ $productoNombre }}"
                                     form="{{ $bulkFormId }}"
                                     min="1"
                                     max="{{ $a->cantidad_asignada }}"
                                     value="1"
                                     disabled
                                     class="ui-input w-20">
                            @else
                              <span class="text-muted">No aplica</span>
                            @endif
                          </td>
                          @endif

                          <x-sistema.celdas-campos :tabla="$tablaCampos" :registro="$a->id" />
                          <td style="text-align:center;">
                            @if(!empty($a->grupo_asignacion))
                              @rutaDisponible($routePrefix.'.asignaciones.pdf')
                              <a href="{{ route($routePrefix . '.asignaciones.pdf', $a->grupo_asignacion) }}"
                                 target="_blank"
                                 rel="noopener noreferrer"
                                 class="ui-btn-secondary ui-btn-sm">
                                PDF
                              </a>
                              @endrutaDisponible
                            @else
                              <span class="text-muted">Sin grupo</span>
                            @endif
                          </td>

                          <td>
                            @if($a->entrega)
                              @rutaDisponible($routePrefix.'.asignaciones.entrega')
                                <a class="ui-btn-secondary ui-btn-sm" href="{{ route($routePrefix.'.asignaciones.entrega', $a->grupo_asignacion) }}">Ver entrega</a>
                              @endrutaDisponible
                            @else
                            @rutaDisponible($routePrefix.'.asignaciones.upload_pdf_firmado')
                              <form method="POST"
                                    action="{{ route($routePrefix . '.asignaciones.upload_pdf_firmado', $a) }}"
                                    enctype="multipart/form-data"
                                    class="flex flex-col gap-2">
                                @csrf
                                <div class="flex flex-wrap items-center gap-2">
                                  <input type="file"
                                         name="evidencias[]"
                                         accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                         multiple
                                         required
                                         class="max-w-full text-sm">
                                  <button type="submit" class="ui-btn-secondary ui-btn-sm">Subir</button>
                                </div>
                                <span class="text-sm text-muted">PDF firmado o imagen opcional.</span>
                              </form>
                            @endrutaDisponible

                            @if($puedeGestionarPdfsFirmados)
                              @rutaDisponible($routePrefix.'.asignaciones.ver_pdf_firmado')
                              <div class="mt-2 flex flex-col gap-1">
                                @forelse($a->evidencias as $evidencia)
                                  <a href="{{ route($routePrefix . '.asignaciones.ver_pdf_firmado', $evidencia) }}"
                                     target="_blank"
                                     rel="noopener noreferrer"
                                     class="text-xs font-semibold text-brand underline">
                                    {{ $evidencia->tipo_documento === 'asignacion_firmada' ? 'PDF' : 'Imagen' }}:
                                    {{ $evidencia->nombre_original ?? 'Evidencia' }}
                                  </a>
                                @empty
                                  <span class="text-xs text-muted">Sin evidencias</span>
                                @endforelse
                              </div>
                              @endrutaDisponible
                            @endif
                            @endif
                            @foreach($a->incidencias as $incidencia)
                              @rutaDisponible($routePrefix.'.asignaciones.incidencia')
                                <a class="block underline" href="{{ route($routePrefix.'.asignaciones.incidencia', $incidencia) }}">{{ $incidencia->motivo_etiqueta }}</a>
                              @endrutaDisponible
                            @endforeach
                          </td>
                        </tr>
                        @if($puedeSeleccionar && $grupo['devolvibles_lote']->contains($a->id))
                          <tr id="condicion_{{ $a->id }}" hidden>
                            <td colspan="99">
                              @include('admin.asignaciones.condicion', ['prefijo' => 'resoluciones['.$a->id.']', 'formulario' => $bulkFormId])
                            </td>
                          </tr>
                        @endif
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                @if($puedeSeleccionar)
                  <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-subtle p-3">
                    <div>
                      <p class="text-sm font-semibold text-ink">Resolución de custodia</p>
                      <p class="mt-1 text-sm text-muted">Selecciona los productos e indica qué ocurrió con cada uno.</p>
                      @include('admin.asignaciones.motivo-devolucion', ['formulario' => $bulkFormId])
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                      <button type="submit"
                              form="{{ $bulkFormId }}"
                              class="ui-btn-danger">
                        Registrar seleccionados
                      </button>
                    </div>
                  </div>
                @endif
              </div>

            </div>
          </details>
        <?php endforeach; ?>
      @endif
    </div>

    @if($puedeVerMovimientos)
    <div class="ui-panel mt-4 overflow-hidden">
      <div class="border-b border-line bg-subtle px-4 py-3 text-base font-semibold text-ink">
        Historial de movimientos
      </div>

      <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Movimientos de asignaciones y devoluciones">
        <table class="ui-table asg-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Colaborador</th>
              <th style="text-align:center;">Cantidad</th>
              <th>Detalle</th>
              <th>Usuario</th>
              <th style="text-align:center;">Documento</th>
            </tr>
          </thead>

          <tbody>
            @if(blank($movimientos))
              <tr>
                <td colspan="7" class="py-7 text-center text-muted">
                  Sin movimientos registrados.
                </td>
              </tr>
            @else
              <?php foreach ($movimientos as $m): ?>
                @php
                  $pdfDevolucionFirmado = null;

                  if (in_array($m->tipo, ['Devolucion', 'Reemplazo'], true) && $m->motivo_administrativo !== 'ordinaria' && !empty($m->grupo_devolucion) && $puedeGestionarPdfsFirmados) {
                    $pdfDevolucionFirmado = $pdfsDevolucionFirmados->get($m->grupo_devolucion);
                  }
                @endphp

                <tr>
                  <td>{{ ($m->ocurrio_en ?? $m->created_at)?->format('d/m/Y H:i') }}</td>

                  <td>
                    <span class="ui-badge">
                      {{ match ($m->tipo) {
                        'Asignacion' => 'Salida',
                        'Devolucion' => 'Devolución física',
                        'Reemplazo' => 'Retorno de reemplazo',
                        default => $m->tipo,
                      } }}
                    </span>
                  </td>

                  <td>
                    {{ optional(optional($m->asignacion)->colaborador)->nombre ?? '—' }}
                  </td>

                  <td style="text-align:center; font-weight:700;">
                    {{ $m->cantidad }}
                  </td>

                  <td>
                    {{ $m->detalle ?? '—' }}
                  </td>

                  <td>
                    {{ optional($m->user)->name ?? '—' }}
                  </td>

                  <td style="text-align:center;">
                    @if(in_array($m->tipo, ['Devolucion', 'Reemplazo'], true) && $m->motivo_administrativo !== 'ordinaria' && !empty($m->grupo_devolucion))
                      <div class="flex flex-col gap-2">
                        @rutaDisponible($routePrefix.'.asignaciones.hoja_devolucion')
                        <a href="{{ route($routePrefix . '.asignaciones.hoja_devolucion', $m->grupo_devolucion) }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="ui-btn-secondary ui-btn-sm">
                          Ver hoja
                        </a>
                        @endrutaDisponible

                        @rutaDisponible($routePrefix.'.asignaciones.upload_pdf_devolucion_firmado')
                        <form method="POST"
                              action="{{ route($routePrefix . '.asignaciones.upload_pdf_devolucion_firmado', $m->grupo_devolucion) }}"
                              enctype="multipart/form-data"
                              class="flex flex-col gap-2">
                          @csrf

                          <div class="flex flex-wrap items-center gap-2">
                            <input type="file"
                                   name="pdf_firmado"
                                   accept="application/pdf,.pdf"
                                   required
                                   class="max-w-full text-sm">

                            <button type="submit" class="ui-btn-secondary ui-btn-sm">
                              {{ $pdfDevolucionFirmado ? 'Reemplazar firmado' : 'Subir firmado' }}
                            </button>
                          </div>
                        </form>
                        @endrutaDisponible

                        @if($pdfDevolucionFirmado)
                          @rutaDisponible($routePrefix.'.asignaciones.ver_pdf_firmado')
                          <a href="{{ route($routePrefix . '.asignaciones.ver_pdf_firmado', $pdfDevolucionFirmado) }}"
                             target="_blank"
                             rel="noopener noreferrer"
                             class="text-xs font-semibold text-brand underline">
                            Ver firmado: {{ $pdfDevolucionFirmado->nombre_original ?? 'PDF firmado' }}
                          </a>
                          @endrutaDisponible
                        @endif
                      </div>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                </tr>
              <?php endforeach; ?>
            @endif
          </tbody>
        </table>
      </div>
    </div>
    @endif

  </div>
</div>

@include('admin.asignaciones.condicion-script')
<script>
@if(session('pdf_url'))
  window.addEventListener('load', function () {
    window.open(@json(session('pdf_url')), '_blank', 'noopener,noreferrer');
  });
@endif
  document.querySelectorAll('[id^="condicion_"] [data-campo]').forEach(campo => campo.disabled = true);
  document.querySelectorAll('.selector').forEach((checkbox) => {
    checkbox.addEventListener('change', function () {
      const targetId = this.dataset.target;
      const input = document.getElementById(targetId);

      if (!input) return;

      input.disabled = !this.checked;
      const fila = document.getElementById(this.dataset.condicionId);
      fila.hidden = !this.checked;
      fila.querySelectorAll('[data-campo]').forEach(campo => campo.disabled = !this.checked);
      const motivo = fila.querySelector('[data-campo="estado_devolucion"]');
      motivo.required = this.checked;
      if (this.checked) motivo.dispatchEvent(new Event('change'));

      if (!this.checked) {
        input.value = 1;
      }
    });
  });

  document.querySelectorAll('form[id^="bulk-return-"]').forEach((form) => {
    form.addEventListener('submit', function (event) {
      const selected = document.querySelectorAll('[form="' + form.id + '"].selector:checked');

      if (!selected.length) {
        event.preventDefault();
        alert('Selecciona al menos una asignación para devolver.');
      }
    });
  });
</script>
@endsection
