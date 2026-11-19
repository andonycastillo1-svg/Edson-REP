<x-sistema.pagina titulo="Campos personalizados" :volver="route('sistema.configuracion')" configuracion
                 descripcion="Agrega datos a los módulos. Al archivar un campo, sus valores anteriores se conservan.">
    <x-sistema.panel>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex flex-wrap gap-2">
                <input type="search" name="q" value="{{ $q }}" aria-label="Buscar campo por etiqueta" placeholder="Buscar por etiqueta" maxlength="100">
                <select name="modulo" aria-label="Filtrar por módulo" class="rounded-xl border-slate-300">
                    <option value="">Todos los módulos</option>
                    @foreach(\App\Sistema\Modulos::DEFINICIONES as $key => $spec)<option value="{{ $key }}" @selected($modulo === $key)>{{ $spec[0] }}</option>@endforeach
                </select>
                <button type="submit" class="ui-btn-secondary">Filtrar</button>
            </form>
            <a href="{{ route('sistema.campos.crear', array_filter(['modulo' => $modulo])) }}" class="ui-btn-primary">Nuevo campo</a>
        </div>
        <div class="ui-table-scroll" tabindex="0" role="region" aria-label="Listado de campos personalizados">
            <table class="ui-table">
                <thead><tr><th scope="col">Campo</th><th scope="col">Módulo</th><th scope="col">Tipo</th><th scope="col">Estado</th></tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($campos as $campo)
                        <tr>
                            <td class="p-3"><a class="font-semibold text-blue-700 underline-offset-4 hover:underline" href="{{ route('sistema.campos.editar', $campo) }}">{{ $campo->etiqueta }}</a></td>
                            <td class="p-3">{{ \App\Sistema\Modulos::DEFINICIONES[$campo->modulo][0] ?? $campo->modulo }}</td>
                            <td class="p-3">{{ \App\Sistema\ValoresCampos::TIPOS[$campo->tipo] }}</td>
                            <td class="p-3"><span @class(['ui-badge', 'ui-badge-success' => $campo->activo && !$campo->archivado_en, 'ui-badge-warning' => !$campo->activo && !$campo->archivado_en])>{{ $campo->archivado_en ? 'Archivado' : ($campo->activo ? 'Activo' : 'Inactivo') }}</span></td>
                        </tr>
                    @empty<tr><td colspan="4" class="p-3 text-slate-600">Sin campos para este filtro.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $campos->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>
