<x-sistema.pagina titulo="Bitácora de auditoría" :volver="route('sistema.configuracion')">
    <x-sistema.panel>
        <form method="GET" class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
            @foreach(['actor' => 'ID del usuario', 'modulo' => 'Módulo', 'accion' => 'Acción', 'desde' => 'Desde', 'hasta' => 'Hasta'] as $key => $label)
                <label class="text-sm font-semibold">{{ $label }}<input name="{{ $key }}" type="{{ match($key) { 'actor' => 'number', 'desde', 'hasta' => 'date', default => 'text' } }}" value="{{ request($key) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            @endforeach
            <button class="rounded-xl bg-slate-100 px-4 py-2 font-semibold">Filtrar</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100"><tr><th class="p-3">Fecha</th><th class="p-3">Usuario</th><th class="p-3">Evento</th><th class="p-3">Registro</th><th class="p-3">Resultado y cambios</th></tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($eventos as $evento)
                        <tr>
                            <td class="whitespace-nowrap p-3">{{ $evento->created_at }}</td>
                            <td class="p-3">{{ $evento->actor_id ? '#'.$evento->actor_id : 'Consola / sistema' }}</td>
                            <td class="p-3">{{ $evento->modulo }} / {{ $evento->accion }}</td>
                            <td class="p-3">{{ $evento->registro }}</td>
                            <td class="p-3"><details><summary class="cursor-pointer font-semibold text-blue-700">{{ $evento->resultado }}</summary>
                                @if($evento->antes)<p class="mt-2 font-semibold">Antes</p><pre class="max-w-lg whitespace-pre-wrap break-all">{{ $evento->antes }}</pre>@endif
                                @if($evento->despues)<p class="mt-2 font-semibold">Después</p><pre class="max-w-lg whitespace-pre-wrap break-all">{{ $evento->despues }}</pre>@endif
                                <p class="text-xs text-slate-500">Solicitud {{ $evento->solicitud ?? 'de consola' }}</p>
                            </details></td>
                        </tr>
                    @empty<tr><td colspan="5" class="p-3 text-slate-600">No hay eventos para estos filtros.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $eventos->links() }}</div>
    </x-sistema.panel>
</x-sistema.pagina>
