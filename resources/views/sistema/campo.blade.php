<x-sistema.pagina :titulo="$campo ? $campo->etiqueta : 'Nuevo campo personalizado'" :volver="route('sistema.campos')">
    <x-sistema.panel>
        @if($campo?->archivado_en)
            <p class="text-sm text-slate-600">Archivado el {{ $campo->archivado_en->format('d/m/Y H:i') }}. Se conserva el historial de valores.</p>
        @else
            <form method="POST" action="{{ $campo ? route('sistema.campos.actualizar', $campo) : route('sistema.campos.guardar') }}" class="space-y-5">
                @csrf @if($campo) @method('PUT') @endif
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-semibold">Módulo
                        @if($campo)<input type="hidden" name="modulo" value="{{ $campo->modulo }}">@endif
                        <select name="{{ $campo ? '' : 'modulo' }}" @disabled($campo) required class="mt-1 w-full rounded-xl border-slate-300">
                            @foreach(\App\Sistema\Modulos::DEFINICIONES as $key => $spec)<option value="{{ $key }}" @selected(old('modulo', $campo?->modulo) === $key)>{{ $spec[0] }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Identificador
                        <input name="clave" value="{{ old('clave', $campo?->clave) }}" pattern="[a-z][a-z0-9_]{1,59}" @readonly($campo) required class="mt-1 w-full rounded-xl border-slate-300">
                    </label>
                    <label class="text-sm font-semibold">Tipo de dato
                        @if($campo)<input type="hidden" name="tipo" value="{{ $campo->tipo }}">@endif
                        <select id="sistema-tipo-campo" name="{{ $campo ? '' : 'tipo' }}" @disabled($campo) required class="mt-1 w-full rounded-xl border-slate-300">
                            @foreach(\App\Sistema\ValoresCampos::TIPOS as $key => $etiqueta)<option value="{{ $key }}" @selected(old('tipo', $campo?->tipo) === $key)>{{ $etiqueta }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Etiqueta<input name="etiqueta" value="{{ old('etiqueta', $campo?->etiqueta) }}" maxlength="100" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-semibold">Orden<input name="orden" type="number" min="0" max="65000" value="{{ old('orden', $campo?->orden ?? 0) }}" required class="mt-1 w-full rounded-xl border-slate-300"></label>
                    @php($default = $campo?->predeterminado)
                    <label class="text-sm font-semibold">Valor predeterminado
                        <input name="predeterminado" value="{{ old('predeterminado', is_array($default) ? implode(',', $default) : (is_bool($default) ? (int) $default : $default)) }}" class="mt-1 w-full rounded-xl border-slate-300">
                        <span class="mt-1 block text-xs font-normal text-slate-600">Usa 0 o 1 para Sí/No, e ID de opción para listas. Separa varios ID con comas.</span>
                    </label>
                    <label class="text-sm font-semibold sm:col-span-2 lg:col-span-3">Ayuda<textarea name="ayuda" maxlength="500" rows="2" class="mt-1 w-full rounded-xl border-slate-300">{{ old('ayuda', $campo?->ayuda) }}</textarea></label>
                    @if(app(\App\Sistema\Permisos::class)->esSuperadministrador(auth()->user()))
                    @foreach(['permiso_lectura' => 'Permiso adicional de lectura', 'permiso_edicion' => 'Permiso adicional de edición'] as $key => $titulo)
                        <label class="text-sm font-semibold">{{ $titulo }}
                            <select name="{{ $key }}" class="mt-1 w-full rounded-xl border-slate-300">
                                <option value="">Usar acceso del módulo</option>
                                @foreach($permisos as $valor)<option value="{{ $valor }}" @selected(old($key, $campo?->{$key}) === $valor)>{{ $valor }}</option>@endforeach
                            </select>
                        </label>
                    @endforeach
                        <label class="text-sm font-semibold">Contraseña actual si cambia el acceso
                            <input name="current_password" type="password" autocomplete="current-password" class="mt-1 w-full rounded-xl border-slate-300">
                        </label>
                    @endif
                </div>
                <div class="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach(['activo' => 'Activo', 'obligatorio' => 'Obligatorio en registros nuevos', 'formulario' => 'Mostrar en formularios', 'tabla' => 'Mostrar en tabla', 'filtro' => 'Habilitar filtro', 'exportacion' => 'Incluir al exportar'] as $key => $titulo)
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $campo?->{$key} ?? in_array($key, ['activo','formulario'])))>{{ $titulo }}</label>
                    @endforeach
                </div>
                <section id="sistema-opciones-panel" hidden>
                    <h2 class="mb-2 font-bold">Opciones</h2>
                    <div id="sistema-opciones" class="space-y-2">
                        @foreach(old('opciones', $campo?->opciones?->toArray() ?? []) as $i => $opcion)
                            @include('sistema.opcion', compact('i', 'opcion'))
                        @endforeach
                    </div>
                    <template id="sistema-opcion-plantilla">@include('sistema.opcion', ['i' => 'NUEVA_OPCION', 'opcion' => ['activo' => true]])</template>
                    <button type="button" id="sistema-agregar-opcion" class="mt-3 rounded-xl border px-4 py-2 text-sm font-semibold">Agregar opción</button>
                </section>
                <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Guardar campo</button>
            </form>
        @endif
    </x-sistema.panel>
    @if($campo && !$campo->archivado_en)
        <form method="POST" action="{{ route('sistema.campos.archivar', $campo) }}">
            @csrf @method('PATCH')
            <button class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Archivar campo</button>
        </form>
    @endif
    <script>
        (() => {
            const tipo = document.getElementById('sistema-tipo-campo');
            if (!tipo) return;
            const panel = document.getElementById('sistema-opciones-panel');
            const lista = document.getElementById('sistema-opciones');
            const plantilla = document.getElementById('sistema-opcion-plantilla');
            let indice = lista.children.length + 1;
            function reflejarTipo() {
                panel.hidden = !['lista', 'multiple'].includes(tipo.value);
            }
            document.getElementById('sistema-agregar-opcion').addEventListener('click', () => {
                const fila = plantilla.content.cloneNode(true);
                fila.querySelectorAll('[name]').forEach(campo => campo.name = campo.name.replace('NUEVA_OPCION', String(indice)));
                lista.appendChild(fila);
                indice += 1;
            });
            tipo.addEventListener('change', reflejarTipo);
            reflejarTipo();
        })();
    </script>
</x-sistema.pagina>
