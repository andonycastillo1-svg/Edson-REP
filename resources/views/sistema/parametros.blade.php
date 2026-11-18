<x-sistema.pagina titulo="Parámetros generales" :volver="route('sistema.configuracion')">
    <x-sistema.panel>
        <form method="POST" enctype="multipart/form-data" action="{{ route('sistema.parametros.guardar') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf @method('PUT')
            <label class="text-sm font-semibold">Nombre del sistema<input name="nombre" value="{{ old('nombre', $general['nombre'] ?? '') }}" maxlength="100" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Nombre de la empresa<input name="empresa" value="{{ old('empresa', $general['empresa'] ?? '') }}" maxlength="100" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Logotipo<input name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full"></label>
            @if(isset($general['logo']))
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="quitar_logo" value="1">Restaurar logotipo original</label>
            @endif
            <label class="text-sm font-semibold">Mínimo disponible por producto y bodega<input type="number" name="stock_minimo" min="1" max="1000000" value="{{ old('stock_minimo', $general['stock_minimo'] ?? '') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Vida útil predeterminada de nuevos productos (meses)<input type="number" name="vida_util" min="1" max="1200" value="{{ old('vida_util', $general['vida_util'] ?? '') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold sm:col-span-2">Categorías sugeridas para nuevas compras<textarea name="categorias" rows="4" maxlength="2000" class="mt-1 w-full rounded-xl border-slate-300">{{ old('categorias', implode("\n", $general['categorias'] ?? [])) }}</textarea><span class="text-xs font-normal text-slate-600">Escribe una categoría por línea.</span></label>
            @if(app(\App\Sistema\Permisos::class)->esSuperadministrador(auth()->user()))
                <fieldset class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:col-span-2 sm:grid-cols-2">
                    <legend class="font-bold">Parámetros críticos</legend>
                    <label class="text-sm font-semibold">Bodega principal
                        <select name="bodega_principal" class="mt-1 w-full rounded-xl border-slate-300">
                            <option value="">Selección preexistente</option>
                            @foreach($bodegas as $bodega)<option value="{{ $bodega->id }}" @selected(old('bodega_principal', $general['bodega_principal'] ?? null) == $bodega->id)>{{ $bodega->nombre }}</option>@endforeach
                        </select>
                        <span class="mt-1 block text-xs font-normal text-slate-600">El cambio se bloquea cuando existen operaciones históricas.</span>
                    </label>
                    <label class="text-sm font-semibold">Minutos de inactividad (5 a 120)<input name="inactividad" type="number" min="5" max="120" value="{{ old('inactividad', $general['inactividad'] ?? '') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <x-sistema.confirmacion />
                </fieldset>
            @endif
            <div class="sm:col-span-2"><button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Guardar parámetros</button></div>
        </form>
    </x-sistema.panel>
</x-sistema.pagina>
