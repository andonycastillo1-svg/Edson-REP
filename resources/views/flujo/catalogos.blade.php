<x-sistema.pagina titulo="Catálogos operativos" :volver="route('dashboard')">
    @include('flujo.accesos')
    <x-sistema.panel titulo="Regiones">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($regiones->concat([null]) as $region)
                <form action="{{ route('flujo.catalogos.region', $region?->id) }}" method="POST" class="space-y-2 rounded-xl border border-slate-200 p-3">
                    @csrf
                    <label class="text-sm font-semibold">{{ $region ? 'Nombre de región' : 'Nueva región' }}
                        <input name="nombre" value="{{ $region?->nombre }}" class="mt-1 block w-full rounded-xl border-slate-300" maxlength="120" required>
                    </label>
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="hidden" name="activa" value="0">
                        <label class="text-sm"><input type="checkbox" name="activa" value="1" @checked($region?->activa ?? true)> Activa</label>
                        <button class="ui-btn-secondary">{{ $region ? 'Guardar región' : 'Crear región' }}</button>
                    </div>
                </form>
            @endforeach
        </div>
    </x-sistema.panel>
    <details class="rounded-xl border border-slate-200 bg-white p-4">
        <summary class="cursor-pointer font-semibold">Dar de alta un producto</summary>
        <form method="POST" enctype="multipart/form-data" action="{{ route('flujo.catalogos.producto') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
            @csrf
            <label class="text-sm">Código<input name="codigo" maxlength="50" required class="mt-1 block w-full rounded-xl border-slate-300"></label>
            <label class="text-sm">Nombre<input name="nombre" maxlength="150" required class="mt-1 block w-full rounded-xl border-slate-300"></label>
            <label class="text-sm">Categoría<input name="categoria" maxlength="50" class="mt-1 block w-full rounded-xl border-slate-300"></label>
            <label class="text-sm">Unidad de medida<input name="unidad_medida" maxlength="50" class="mt-1 block w-full rounded-xl border-slate-300"></label>
            <label class="text-sm">Vida útil en meses<input type="number" min="1" max="1200" name="vida_util_meses" class="mt-1 block w-full rounded-xl border-slate-300"></label>
            <label class="text-sm">Descripción<textarea name="descripcion" rows="2" maxlength="10000" class="mt-1 block w-full rounded-xl border-slate-300"></textarea></label>
            <div class="sm:col-span-2">
                <x-sistema.campos modulo="productos" />
                <button class="ui-btn-primary mt-3">Crear producto</button>
            </div>
        </form>
    </details>
    <datalist id="productos-inspeccion">
        @foreach($productos as $producto)<option value="{{ $producto->codigo }}">{{ $producto->nombre }}</option>@endforeach
    </datalist>
    <x-sistema.panel titulo="Artículos del formulario">
        <p class="mb-3 text-sm">Asocia cada artículo a un código de inventario. Los artículos desactivados se conservan en las inspecciones históricas.</p>
        @foreach($secciones as $seccion)
            <details class="mb-3 rounded-xl border border-slate-200 p-3">
                <summary class="cursor-pointer font-semibold">{{ $seccion->nombre }}</summary>
                <form action="{{ route('flujo.catalogos.seccion', $seccion->clave) }}" method="POST" class="mt-3 flex flex-wrap items-end gap-3 border-b border-slate-200 pb-3">
                    @csrf
                    <label class="text-sm">Nombre de sección<input name="nombre" value="{{ $seccion->nombre }}" required maxlength="160" class="mt-1 block rounded-xl border-slate-300"></label>
                    <label class="text-sm">Orden de sección<input name="orden" type="number" value="{{ $seccion->orden }}" required min="0" max="65535" class="mt-1 block w-24 rounded-xl border-slate-300"></label>
                    <button class="ui-btn-secondary">Guardar sección</button>
                </form>
                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    @foreach($articulos->get($seccion->clave, collect())->concat([null]) as $articulo)
                        <form action="{{ route('flujo.catalogos.articulo', $articulo?->id) }}" method="POST" class="grid items-end gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                            @csrf
                            <label class="text-sm">Etiqueta
                                <input name="etiqueta" value="{{ $articulo?->etiqueta }}" class="mt-1 block w-full rounded-xl border-slate-300" maxlength="160" required>
                            </label>
                            <label class="text-sm">Código de inventario
                                <input name="producto_codigo" value="{{ $articulo?->producto_codigo }}" list="productos-inspeccion" class="mt-1 block w-full rounded-xl border-slate-300">
                            </label>
                            <label class="text-sm">Sección
                                <select name="seccion" class="mt-1 block w-full rounded-xl border-slate-300" required>
                                    @foreach($secciones as $opcion)
                                        <option value="{{ $opcion->clave }}" @selected($opcion->clave === $seccion->clave)>{{ $opcion->nombre }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-sm">Descripción
                                <input name="descripcion" value="{{ $articulo?->descripcion }}" class="mt-1 block w-full rounded-xl border-slate-300" maxlength="500">
                            </label>
                            <label class="text-sm">Máximo por inspección
                                <input type="number" name="maximo" min="1" max="10000" value="{{ $articulo?->maximo ?? ($seccion->clave === 'reflectivos' ? 7 : 1) }}" class="mt-1 block w-full rounded-xl border-slate-300" required>
                            </label>
                            <label class="text-sm">Orden
                                <input type="number" name="orden" min="0" max="65535" value="{{ $articulo?->orden ?? 0 }}" class="mt-1 block w-full rounded-xl border-slate-300" required>
                            </label>
                            <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
                                <input type="hidden" name="activo" value="0">
                                <label class="text-sm"><input type="checkbox" name="activo" value="1" @checked($articulo?->activo ?? true)> Mostrar en el formulario</label>
                                <button class="ui-btn-secondary">{{ $articulo ? 'Guardar artículo' : 'Agregar artículo' }}</button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </details>
        @endforeach
    </x-sistema.panel>
    <x-sistema.panel titulo="Motivos de asignación">
        <div class="grid gap-3 lg:grid-cols-2">
            @foreach($motivos->concat([null]) as $motivo)
                <form action="{{ route('flujo.catalogos.motivo', $motivo?->id) }}" method="POST" class="grid gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                    @csrf
                    <label class="text-sm">Código de motivo<input name="codigo" maxlength="45" value="{{ $motivo?->codigo }}" class="mt-1 block w-full rounded-xl border-slate-300" required></label>
                    <label class="text-sm">Etiqueta<input name="nombre" maxlength="120" value="{{ $motivo?->nombre }}" class="mt-1 block w-full rounded-xl border-slate-300" required></label>
                    <label class="text-sm sm:col-span-2">Tratamiento
                        <select name="politica" required class="mt-1 block w-full rounded-xl border-slate-300">
                            @foreach(\App\Models\MotivoAsignacion::POLITICAS as $clave => $etiqueta)
                                <option value="{{ $clave }}" @selected($clave === $motivo?->politica)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
                        <input type="hidden" name="activo" value="0">
                        <label class="text-sm"><input type="checkbox" name="activo" value="1" @checked($motivo?->activo ?? true)> Activo</label>
                        <button class="ui-btn-secondary">{{ $motivo ? 'Guardar motivo' : 'Crear motivo' }}</button>
                    </div>
                </form>
            @endforeach
        </div>
    </x-sistema.panel>
</x-sistema.pagina>
