<x-sistema.panel titulo="Relaciones operativas" id="relaciones" data-panel-pestana>
    <dl class="mb-4 grid gap-3 text-sm sm:grid-cols-2">
        <div><dt class="font-semibold">Bodega</dt><dd>{{ $usuario->bodega?->nombre ?? 'Sin bodega asignada' }}</dd></div>
        <div><dt class="font-semibold">Coordinador del supervisor</dt><dd>{{ $usuario->coordinador?->name ?? 'Sin coordinador asignado' }}</dd></div>
        <div><dt class="font-semibold">Regiones con responsabilidad principal</dt><dd>{{ $usuario->regionesCoordinadas->pluck('nombre')->join(', ') ?: 'Sin regiones principales' }}</dd></div>
        <div><dt class="font-semibold">Supervisores relacionados</dt><dd>{{ $usuario->supervisoresAsignados->pluck('name')->join(', ') ?: 'Sin supervisores asignados' }}</dd></div>
        <div><dt class="font-semibold">Almacenista</dt><dd>{{ $usuario->almacenistasAsignados->pluck('name')->join(', ') ?: 'Sin almacenista asignado' }}</dd></div>
    </dl>
    <form method="POST" action="{{ route('sistema.usuario.relaciones', $usuario) }}" class="space-y-4" data-confirmar="¿Guardar las relaciones operativas?">
        @csrf @method('PUT')
        <input type="hidden" name="panel_cuenta" value="#relaciones">
        <fieldset>
            <legend class="font-semibold">Regiones operativas</legend>
            <p class="mb-2 text-sm">Las operaciones se habilitan cuando la cuenta también tiene el permiso correspondiente.</p>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($regiones as $region)
                    <label class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm">
                        <input type="checkbox" name="regiones[]" value="{{ $region->id }}" @checked(in_array($region->id, old('regiones', $regionesOperativas)))>
                        {{ $region->nombre }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend class="font-semibold">Bodegas para consulta</legend>
            <p class="mb-2 text-sm">Estas relaciones habilitan la consulta de existencias si la cuenta tiene permiso de lectura.</p>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($bodegas as $bodega)
                    <label class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm">
                        <input type="checkbox" name="bodegas_operativas[]" value="{{ $bodega->id }}" @checked(in_array($bodega->id, old('bodegas_operativas', $bodegasOperativas)))>
                        {{ $bodega->nombre }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend class="font-semibold">Bodegas para operar inventario</legend>
            <p class="mb-2 text-sm">Marca las bodegas donde puede realizar las acciones que le otorgues en Permisos. Las entregas y devoluciones requieren además la región del colaborador.</p>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($bodegas as $bodega)
                    <label class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm">
                        <input type="checkbox" name="bodegas_inventario[]" value="{{ $bodega->id }}" @checked(in_array($bodega->id, old('bodegas_inventario', $bodegasInventario)))>
                        {{ $bodega->nombre }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        @if($bodegaPrincipal)
            <label class="flex min-h-11 items-center gap-2 text-sm">
                <input type="checkbox" name="opera_requerimientos" value="1" @checked(old('opera_requerimientos', $operaRequerimientos))>
                Responsabilidad de requerimientos en {{ $bodegaPrincipal->nombre }}
            </label>
        @endif
        @if($usuario->rolOperativo() === 5)
            <fieldset>
                <legend class="font-semibold">Responsabilidad adicional de coordinación</legend>
                <p class="mb-2 text-sm">En regiones con varios responsables, el operador elegirá el coordinador al preparar el consolidado.</p>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($regiones as $region)
                        <label class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm">
                            <input type="checkbox" name="coordinacion[]" value="{{ $region->id }}" @checked(in_array($region->id, old('coordinacion', $regionesAdicionales)))>
                            {{ $region->nombre }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
        <x-sistema.confirmacion />
        <button type="submit" class="ui-btn-primary">Guardar relaciones</button>
    </form>
</x-sistema.panel>
