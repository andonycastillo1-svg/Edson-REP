<x-sistema.pagina titulo="Campos personalizados" :volver="route('sistema.configuracion')">
    <x-sistema.panel>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex flex-wrap gap-2">
                <select name="modulo" aria-label="Filtrar por módulo" class="rounded-xl border-slate-300">
                    <option value="">Todos los módulos</option>
                    @foreach(\App\Sistema\Modulos::DEFINICIONES as $key => $spec)<option value="{{ $key }}" @selected($modulo === $key)>{{ $spec[0] }}</option>@endforeach
                </select>
                <button class="rounded-xl bg-slate-100 px-4 py-2 font-semibold">Filtrar</button>
            </form>
            <a href="{{ route('sistema.campos.crear') }}" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Nuevo campo</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 text-left"><tr><th class="p-3">Campo</th><th class="p-3">Módulo</th><th class="p-3">Tipo</th><th class="p-3">Estado</th></tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($campos as $campo)
                        <tr>
                            <td class="p-3"><a class="font-semibold text-blue-700" href="{{ route('sistema.campos.editar', $campo) }}">{{ $campo->etiqueta }}</a><div class="text-xs text-slate-500">{{ $campo->clave }}</div></td>
                            <td class="p-3">{{ \App\Sistema\Modulos::DEFINICIONES[$campo->modulo][0] ?? $campo->modulo }}</td>
                            <td class="p-3">{{ \App\Sistema\ValoresCampos::TIPOS[$campo->tipo] }}</td>
                            <td class="p-3">{{ $campo->archivado_en ? 'Archivado' : ($campo->activo ? 'Activo' : 'Inactivo') }}</td>
                        </tr>
                    @empty<tr><td colspan="4" class="p-3 text-slate-600">Sin campos para este filtro.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $campos->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>
