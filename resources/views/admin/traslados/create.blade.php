  @extends('layouts.admin')

  @section('content')
  @php
    $routePrefix = \App\Services\AccesoInventarioOperativo::prefijo(auth()->user());
    $origenFijo = $origenes->count() === 1;
    $origenSeleccionado = old('bodega_origen_id', $origenId);
  @endphp

  <div class="max-w-4xl mx-auto">
    <div>
      <x-internal-navigation :back-url="route($routePrefix . '.operaciones.traslados.index')" :back-route="$routePrefix.'.operaciones.traslados.index'" />

      <div class="mb-6">
        <h1 class="ui-page-title text-slate-900 mt-2">Nueva solicitud de traslado</h1>
        <p class="text-sm text-slate-600">La persona encargada de la bodega de destino debe aprobar o rechazar la solicitud.</p>
      </div>

      <form method="POST" action="{{ route($routePrefix . '.operaciones.traslados.store') }}"
            enctype="multipart/form-data"
            class="rounded-xl bg-white shadow-sm border border-slate-200 overflow-hidden">
        @csrf
            <input type="hidden" name="solicitud_id" value="{{ old('solicitud_id', (string) \Illuminate\Support\Str::uuid()) }}">

        <div class="px-6 py-4 border-b border-slate-100">
          <div class="text-sm font-semibold text-slate-800">Origen y destino</div>
          <div class="text-xs text-slate-500">Tip: si entraste desde bodegas, el origen viene precargado.</div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            @if($origenFijo)<p class="text-sm font-semibold">Bodega de origen</p>
            @else<label for="bodega_origen" class="text-sm font-semibold">Bodega de origen</label>@endif

            @if($origenFijo)
              <input type="hidden" name="bodega_origen_id" value="{{ $origenId }}">
              <div class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-100">
                {{ optional($bodegas->firstWhere('id', $origenId))->nombre }}
              </div>
            @else
              <select id="bodega_origen" name="bodega_origen_id" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">Selecciona...</option>
                @foreach($origenes as $b)
                  <option value="{{ $b->id }}"
                    {{ (string) old('bodega_origen_id', $origenId) === (string) $b->id ? 'selected' : '' }}>
                    {{ $b->nombre }}
                  </option>
                @endforeach
              </select>
            @endif
          </div>

          <div>
            <label for="bodega_destino_id" class="text-sm font-semibold text-slate-700">Bodega de destino</label>
            <select id="bodega_destino_id" name="bodega_destino_id" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="">Selecciona...</option>
              @foreach($bodegas as $b)
                  <option value="{{ $b->id }}" @selected((string) old('bodega_destino_id') === (string) $b->id)
                    @if((string) $b->id === (string) $origenSeleccionado) hidden disabled @endif>
                    {{ $b->nombre }}
                  </option>
              @endforeach
            </select>
          </div>
        </div>

        <x-sistema.campos-nativos modulo="traslados" />

        <div class="px-6 pb-6">
          <label for="campo-archivo-excel" class="text-sm font-semibold text-slate-700">Excel de destinatarios (opcional)</label>
          <input id="campo-archivo-excel" type="file" name="archivo_excel" accept=".xlsx,.xls,.csv"
            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white">
          <p class="mt-1 text-xs text-slate-500">
            Adjunta un archivo XLSX, XLS o CSV de hasta 10 MB con las personas destinatarias. Si el guardado falla, vuelve a adjuntarlo.
          </p>
        </div>

        <div class="px-6 py-4 border-t border-b border-slate-100 flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold text-slate-800">Productos</div>
            <div class="text-xs text-slate-500">Agrega una o varias líneas.</div>
          </div>
        </div>

        <div class="p-6">
          <div class="overflow-x-auto">
            <table class="ui-table w-full text-sm">
              <thead class="bg-slate-50 text-slate-600">
                <tr>
                  <th class="text-left px-3 py-2 font-semibold">Producto</th>
                  <th class="text-left px-3 py-2 font-semibold w-40">Cantidad</th>
                  <th class="px-3 py-2 w-20"></th>
                </tr>
              </thead>

              <tbody id="linesBody" class="divide-y divide-slate-100">
                @php
                  $oldLines = old('lineas', [
                    ['producto_codigo' => '', 'cantidad' => 1],
                  ]);
                @endphp

                @foreach($oldLines as $i => $line)
                  <tr class="line-row">
                    <td class="px-3 py-3">
                      <select required
                              name="lineas[{{ $i }}][producto_codigo]"
                              data-searchable="true"
                              data-search-placeholder="Buscar producto..."
                              class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Selecciona...</option>
                        @foreach($productos as $p)
                          <option value="{{ $p->codigo }}"
                            {{ ($line['producto_codigo'] ?? '') === $p->codigo ? 'selected' : '' }}>
                            {{ $p->descripcion ?: $p->nombre }} — {{ $p->codigo }}
                          </option>
                        @endforeach
                      </select>
                    </td>

                    <td class="px-3 py-3">
                      <input type="number" min="1" required
                            name="lineas[{{ $i }}][cantidad]"
                            value="{{ $line['cantidad'] ?? 1 }}"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"/>
                    </td>

                    <td class="px-3 py-3 text-right">
                      <button type="button"
                              class="btnRemove inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        ✕
                      </button>
                    </td>
                  </tr>
                @endforeach

                <tr class="bg-slate-50">
                  <td colspan="3" class="px-3 py-3">
                    <button type="button" id="btnAddLine"
                            class="inline-flex items-center gap-2 rounded-xl border border-blue-700 bg-blue-600 px-4 py-2 text-white text-sm font-semibold hover:bg-blue-700"
                            style="background-color:#2563eb;color:#fff;">
                      + Agregar línea
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <x-sistema.campos modulo="traslados" />

        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-2">
          <a href="{{ route($routePrefix . '.operaciones.traslados.index') }}"
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Cancelar
          </a>

          <button class="rounded-xl bg-blue-600 px-4 py-2 text-white text-sm font-semibold hover:bg-blue-700">
            Crear solicitud
          </button>
        </div>
    </form>

      <template id="lineTemplate">
        <tr class="line-row">
          <td class="px-3 py-3">
            <select required data-searchable="true" data-search-placeholder="Buscar producto..." class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="">Selecciona...</option>
              @foreach($productos as $p)
                <option value="{{ $p->codigo }}">{{ $p->descripcion ?: $p->nombre }} — {{ $p->codigo }}</option>
              @endforeach
            </select>
          </td>
          <td class="px-3 py-3">
            <input type="number" min="1" value="1" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"/>
          </td>
          <td class="px-3 py-3 text-right">
            <button type="button" class="btnRemove inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
              ✕
            </button>
          </td>
        </tr>
      </template>

      <script>
        (function () {
          const body = document.getElementById('linesBody');
          const tpl  = document.getElementById('lineTemplate');
          const btn  = document.getElementById('btnAddLine');
          const origenSelect = document.querySelector('select[name=\"bodega_origen_id\"]');
          const destinoSelect = document.getElementById('bodega_destino_id');

          function reindex() {
            const rows = body.querySelectorAll('.line-row');
            rows.forEach((row, idx) => {
              const sel = row.querySelector('select');
              const inp = row.querySelector('input[type="number"]');
              sel.name = `lineas[${idx}][producto_codigo]`;
              inp.name = `lineas[${idx}][cantidad]`;
            });
          }

          function bindRemove(row) {
            row.querySelector('.btnRemove').addEventListener('click', () => {
              const rows = body.querySelectorAll('.line-row');
              if (rows.length <= 1) return;
              row.remove();
              reindex();
            });
          }

          body.querySelectorAll('.line-row').forEach(bindRemove);
          body.querySelectorAll('.line-row select[data-searchable="true"]').forEach((sel) => {
            if (window.enhanceSearchableSelect) window.enhanceSearchableSelect(sel);
          });

          btn.addEventListener('click', () => {
            const fragment = tpl.content.cloneNode(true);
            const row = fragment.querySelector('.line-row');

            const addRow = btn.closest('tr');
            addRow.parentNode.insertBefore(row, addRow);

            bindRemove(row);
            const productSelect = row.querySelector('select[data-searchable="true"]');
            if (productSelect && window.enhanceSearchableSelect) {
              window.enhanceSearchableSelect(productSelect);
            }
            reindex();
          });

          reindex();

          function syncDestinoOptions() {
            if (!origenSelect || !destinoSelect) return;
            const origenId = (origenSelect.value || '').toString();

            Array.from(destinoSelect.options).forEach((opt) => {
              if (!opt.value) return;
              opt.hidden = opt.value === origenId;
              opt.disabled = opt.value === origenId;
            });

            if (destinoSelect.value === origenId) {
              destinoSelect.value = '';
            }
          }

          if (origenSelect && destinoSelect) {
            origenSelect.addEventListener('change', syncDestinoOptions);
            syncDestinoOptions();
          }
        })();
      </script>

    </div>
  </div>
  @endsection
