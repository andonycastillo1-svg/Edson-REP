<x-sistema.pagina titulo="Plantillas de documentos" configuracion>
    <div class="sistema-pestanas" aria-label="Tipo de documento">
        @foreach(\App\Services\FormatosDocumento::TIPOS as $clave => $titulo)
            <a href="{{ route('sistema.documentos', ['tipo' => $clave]) }}" @if($tipo === $clave) aria-current="page" @endif>{{ ['asignacion' => 'Asignación', 'traslado' => 'Traslado', 'devolucion' => 'Devolución'][$clave] }}</a>
        @endforeach
    </div>
    <x-sistema.panel :titulo="$vigente->id ? 'Versión vigente #'.$vigente->id : 'Formato corporativo inicial'">
        <form method="POST" enctype="multipart/form-data" action="{{ route('sistema.documentos.publicar', $tipo) }}" class="space-y-3">
            @csrf
            <div class="grid gap-3 md:grid-cols-2">
                @foreach(\App\Services\FormatosDocumento::TEXTOS as $campo => $etiqueta)
                    @php($extendido = in_array($campo, ['introduccion', 'condiciones', 'aceptacion', 'pie']))
                    <label class="ui-label {{ $extendido ? 'md:col-span-2' : '' }}">
                        {{ $etiqueta }}
                        @if($extendido)
                            <textarea class="ui-input mt-1" name="{{ $campo }}" rows="{{ $campo === 'condiciones' ? 5 : 2 }}">{{ old($campo, $formato[$campo]) }}</textarea>
                        @else
                            <input class="ui-input mt-1" name="{{ $campo }}" value="{{ old($campo, $formato[$campo]) }}"
                                @required(in_array($campo, ['empresa', 'titulo', 'firma_1', 'firma_2']))>
                        @endif
                    </label>
                @endforeach
                <label class="ui-label">Logotipo (PNG o JPG)
                    <input class="ui-input mt-1" type="file" name="logo" accept="image/png,image/jpeg">
                </label>
                <label class="ui-check"><input type="checkbox" name="restaurar_logo" value="1"> Restablecer logotipo corporativo</label>
                <label class="ui-label">Tamaño del logotipo
                    <input class="ui-input mt-1" type="number" name="ancho_logo" min="36" max="110" value="{{ old('ancho_logo', $formato['ancho_logo']) }}" required>
                </label>
                <label class="ui-label">Distribución
                    <select class="ui-input mt-1" name="distribucion">
                        <option value="compacta" @selected(old('distribucion', $formato['distribucion']) === 'compacta')>Compacta</option>
                        <option value="vertical" @selected(old('distribucion', $formato['distribucion']) === 'vertical')>Vertical</option>
                    </select>
                </label>
            </div>
            <fieldset class="flex flex-wrap gap-3">
                <legend class="mb-1 font-semibold">Datos adicionales visibles</legend>
                @foreach($campos as $clave => $titulo)
                    <label class="ui-check"><input type="checkbox" name="campos[]" value="{{ $clave }}" @checked(in_array($clave, old('campos', $formato['campos']), true))>{{ $titulo }}</label>
                @endforeach
            </fieldset>
            <div class="flex flex-wrap gap-2">
                <button class="ui-btn-secondary" formaction="{{ route('sistema.documentos.vista_previa', $tipo) }}" formtarget="_blank">Vista previa</button>
                <button class="ui-btn-secondary" formaction="{{ route('sistema.documentos.vista_previa', $tipo) }}" name="pdf" value="1">Descargar muestra PDF</button>
            </div>
            <div class="border-t border-slate-200 pt-3">
                <label class="ui-label block max-w-md">Contraseña actual para publicar
                    <input class="ui-input mt-1" type="password" name="current_password" autocomplete="current-password">
                </label>
                <button class="ui-btn-primary mt-2">Publicar nueva versión</button>
            </div>
        </form>
    </x-sistema.panel>
    <x-sistema.panel titulo="Versiones publicadas">
        <table class="ui-table">
            <thead><tr><th>Versión</th><th>Fecha de publicación</th></tr></thead>
            <tbody>
                @forelse($versiones as $version)
                    <tr><td>#{{ $version->id }}</td><td>{{ $version->created_at }}</td></tr>
                @empty
                    <tr><td colspan="2">Aún se usa el formato corporativo inicial.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $versiones->links() }}
    </x-sistema.panel>
</x-sistema.pagina>
