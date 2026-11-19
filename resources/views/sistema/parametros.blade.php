<x-sistema.pagina titulo="Parámetros generales" :volver="route('sistema.configuracion')" configuracion
                 descripcion="Ajusta la identidad del sistema, los valores iniciales de inventario y la duración de las sesiones.">
    <form method="POST" enctype="multipart/form-data" action="{{ route('sistema.parametros.guardar') }}" class="space-y-4">
        @csrf @method('PUT')
        <x-sistema.panel titulo="Identidad de la empresa">
            <div class="sistema-formulario">
                <label class="text-sm font-semibold">Nombre del sistema
                    <input name="nombre" value="{{ old('nombre', $general['nombre'] ?? '') }}" maxlength="100" class="mt-1 w-full">
                    <span class="mt-1 block text-xs font-normal">Se usa en el título de las páginas.</span>
                </label>
                <label class="text-sm font-semibold">Nombre de la empresa
                    <input name="empresa" value="{{ old('empresa', $general['empresa'] ?? '') }}" maxlength="100" class="mt-1 w-full">
                    <span class="mt-1 block text-xs font-normal">Identifica a la empresa en el inicio de sesión.</span>
                </label>
                <label class="text-sm font-semibold">Logotipo
                    <input name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full">
                    <span class="mt-1 block text-xs font-normal">Usa PNG, JPG o WebP de hasta 2 MB. Si la carga falla, vuelve a seleccionar el archivo.</span>
                </label>
                @if(isset($general['logo']))
                    <x-sistema.casilla name="quitar_logo" id="quitar-logo" :marcada="old('quitar_logo', false)">Restaurar logotipo original</x-sistema.casilla>
                @endif
            </div>
        </x-sistema.panel>
        <x-sistema.panel titulo="Inventario y compras">
            <div class="sistema-formulario">
                <label class="text-sm font-semibold">Nivel mínimo por producto y bodega
                    <input type="number" name="stock_minimo" min="1" max="1000000" value="{{ old('stock_minimo', $general['stock_minimo'] ?? '') }}" class="mt-1 w-full">
                    <span class="mt-1 block text-xs font-normal">Define cuándo se genera el aviso de inventario bajo, si el aviso está activo.</span>
                </label>
                <label class="text-sm font-semibold">Vida útil inicial de nuevos productos (meses)
                    <input type="number" name="vida_util" min="1" max="1200" value="{{ old('vida_util', $general['vida_util'] ?? '') }}" class="mt-1 w-full">
                    <span class="mt-1 block text-xs font-normal">Se usa al crear un producto sin indicar una vida útil.</span>
                </label>
                <label class="text-sm font-semibold sm:col-span-2">Categorías sugeridas para compras
                    <textarea name="categorias" rows="4" maxlength="2000" class="mt-1 w-full">{{ old('categorias', implode("\n", $general['categorias'] ?? [])) }}</textarea>
                    <span class="mt-1 block text-xs font-normal">Escribe una categoría por línea, hasta 50 caracteres por categoría.</span>
                </label>
            </div>
        </x-sistema.panel>
        <button type="submit" class="ui-btn-primary">Guardar parámetros generales</button>
    </form>

    @if(app(\App\Sistema\Permisos::class)->esSuperadministrador(auth()->user()))
        <x-sistema.panel>
            <details @if(old('formulario_parametros') === 'protegido' || $errors->has('bodega_principal') || $errors->has('inactividad')) open @endif>
                <summary class="sistema-desplegable">Bodega principal y seguridad de sesión</summary>
                <form method="POST" action="{{ route('sistema.parametros.guardar') }}" class="mt-3 space-y-4"
                      data-confirmar="¿Guardar los ajustes de bodega principal y duración de sesión para todo el sistema?">
                    @csrf @method('PUT')
                    <input type="hidden" name="formulario_parametros" value="protegido">
                    <p class="ui-alert-warning">La bodega principal recibe entradas de compras y su cambio se bloquea si existe historial operativo. El tiempo de inactividad afecta a todas las cuentas.</p>
                    <div class="sistema-formulario">
                        <label for="bodega-principal" class="text-sm font-semibold">Bodega principal
                            <select id="bodega-principal" name="bodega_principal" aria-label="Bodega principal" class="mt-1 w-full">
                                <option value="">Usar la bodega principal predeterminada</option>
                                @foreach($bodegas as $bodega)<option value="{{ $bodega->id }}" @selected(old('bodega_principal', $general['bodega_principal'] ?? null) == $bodega->id)>{{ $bodega->nombre }}</option>@endforeach
                            </select>
                        </label>
                        <label class="text-sm font-semibold">Minutos de inactividad antes de cerrar sesión
                            <input name="inactividad" type="number" min="5" max="120" value="{{ old('inactividad', $general['inactividad'] ?? '') }}" class="mt-1 w-full">
                            <span class="mt-1 block text-xs font-normal">Ingresa entre 5 y 120 minutos. Si dejas el campo vacío, se usa el tiempo predeterminado.</span>
                        </label>
                    </div>
                    <x-sistema.confirmacion />
                    <button type="submit" class="ui-btn-primary">Guardar parámetros protegidos</button>
                </form>
            </details>
        </x-sistema.panel>
    @endif
</x-sistema.pagina>
