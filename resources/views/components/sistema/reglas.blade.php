@props(['catalogo', 'reglas', 'efectivos'])
<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-100 text-slate-700"><tr><th class="p-3">Permiso</th><th class="p-3">Regla</th><th class="p-3">Acceso efectivo</th></tr></thead>
        <tbody class="divide-y divide-slate-200">
            @forelse($catalogo as $clave)
                @php($estado = isset($reglas[$clave]) ? ($reglas[$clave] ? 'permitir' : 'denegar') : 'heredar')
                <tr>
                    <td class="p-3">{{ \App\Sistema\CatalogoPermisos::CONFIGURACION[$clave] ?? $clave }}</td>
                    <td class="p-3">
                        <select name="reglas[{{ $clave }}]" aria-label="{{ $clave }}" class="rounded-xl border-slate-300 text-sm">
                            @foreach(['heredar' => 'Heredar', 'permitir' => 'Permitir', 'denegar' => 'Denegar'] as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected($estado === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="p-3">{{ $efectivos[$clave]['permitido'] ? 'Permitido' : 'Denegado' }} · {{ $efectivos[$clave]['origen'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="p-3 text-slate-500">La cuenta carece de un ámbito operativo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
