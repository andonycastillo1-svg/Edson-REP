  @extends('layouts.admin')

  @section('content')
  @php
    $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';
    $esOperador = auth()->user()->role_id == 2;
    $origenSeleccionado = old('bodega_origen_id', $origenId ?? ($esOperador ? auth()->user()->bodega_id : null));
  @endphp

  <div class="min-h-[calc(100vh-120px)] px-6 py-10">
    <div class="max-w-4xl mx-auto">

      <div class="mb-6">
        <a href="{{ route($routePrefix . '.bodegas.index') }}" class="text-sm text-blue-700 hover:underline">← Volver</a>
        <h1 class="text-3xl font-bold text-slate-900 mt-2">Nueva solicitud de traslado</h1>
        <p class="text-sm text-slate-50000">El encargado de la bodega destino debe aprobar o rechazar.</p>
      </div>

      @if ($errors->any())
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
          <div class="font-semibold mb-1">Revisa estos datos:</div>
          <ul class="list-disc ml-5 text-sm">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route($routePrefix . '.operaciones.traslados.store') }}"
            class="rounded-2xl bg-white shadow-sm border border-slate-200 overflow-hidden">
        @csrf

        <div class="px-6 py-4 border-b border-slate-100">
          <div class="text-sm font-semibold text-slate-800">Origen y destino</div>
          <div class="text-xs text-slate-500">Tip: si entraste desde bodegas, el origen viene precargado.</div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-semibold text-slate-700">Bodega origen</label>

            @if($esOperador)
              <input type="hidden" name="bodega_origen_id" value="{{ auth()->user()->bodega_id }}">
              <div class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-100">
                {{ optional($bodegas->firstWhere('id', auth()->user()->bodega_id))->nombre }}
              </div>
            @else
              <select name="bodega_origen_id" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">Selecciona...</option>
                @foreach($bodegas as $b)
                  <option value="{{ $b->id }}"
                    {{ (string) old('bodega_origen_id', $origenId) === (string) $b->id ? 'selected' : '' }}>
                    {{ $b->nombre }}
                  </option>
                @endforeach
              </select>
            @endif
          </div>

          <div>
            <label class="text-sm font-semibold text-slate-700">Bodega destino</label>
            <select id="bodega_destino_id" name="bodega_destino_id" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="">Selecciona...</option>
              @foreach($bodegas as $b)
                @if((string)$b->id !== (string)$origenSeleccionado)
                  <option value="{{ $b->id }}" {{ (string) old('bodega_destino_id') === (string) $b->id ? 'selected' : '' }}>
                    {{ $b->nombre }}
                  </option>
                @endif
              @endforeach
            </select>
          </div>
        </div>

        <div class="px-6 pb-6">
          <label class="text-sm font-semibold text-slate-700">Observación (opcional)</label>
          <textarea name="observacion" rows="2"
            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
            placeholder="Ej: Enviar con guía #123, frágil, etc.">{{ old('observacion') }}</textarea>
        </div>

        <div class="px-6 py-4 border-t border-b border-slate-100 flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold text-slate-800">Productos</div>
            <div class="text-xs text-slate-500">Agrega una o varias líneas.</div>
          </div>
        </div>

        <div class="p-6">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
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
