<x-sistema.pagina titulo="Necesidades de compra" :volver="route('dashboard')">
    @include('flujo.accesos')
    @if(!$bodega)
        <p class="ui-alert-warning">Configura la bodega central para consultar las existencias.</p>
    @else
        <x-sistema.panel titulo="Consolidados aprobados">
            <form method="GET" class="space-y-3">
                <fieldset class="grid gap-2 sm:grid-cols-2">
                    @forelse($disponibles as $fila)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="consolidados[]" value="{{ $fila->id }}" @checked($seleccion->contains('id', $fila->id))>
                            <span>{{ $fila->nombre }} · {{ $fila->coordinador?->name }}</span>
                        </label>
                    @empty
                        <p class="text-sm">Todavía no hay consolidados aprobados.</p>
                    @endforelse
                </fieldset>
                @if($disponibles->isNotEmpty())<button class="ui-btn-secondary">Calcular selección</button>@endif
            </form>
        </x-sistema.panel>
        @if($seleccion->isNotEmpty())
            <form method="POST" enctype="multipart/form-data" action="{{ route('flujo.compras.constancia') }}" class="space-y-4">
                @csrf
                @foreach($seleccion as $fila)<input type="hidden" name="consolidados[]" value="{{ $fila->id }}">@endforeach
                <x-sistema.panel :titulo="'Existencias disponibles · '.$bodega->nombre">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50"><tr><th class="p-3">Producto</th><th class="p-3">Aprobada</th><th class="p-3">Central</th><th class="p-3">Sugerida</th><th class="p-3">Propuesta revisada</th></tr></thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach($lineas as $i => $fila)
                                    <tr>
                                        <td class="p-3">{{ $fila['producto'] }} · {{ $fila['codigo'] }}</td><td class="p-3">{{ $fila['aprobada'] }}</td>
                                        <td class="p-3">{{ $fila['central'] }}</td><td class="p-3">{{ $fila['sugerida'] }}</td>
                                        <td class="p-3"><input type="number" min="0" max="400000000" aria-label="Propuesta para {{ $fila['producto'] }}"
                                            name="propuestas[{{ bin2hex($fila['codigo']) }}]" value="{{ $fila['sugerida'] }}" class="w-28 rounded-xl border-slate-300"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-sistema.panel>
                <x-sistema.panel titulo="Constancia de autorización externa">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">Medio<select name="medio" required class="mt-1 block w-full rounded-xl border-slate-300"><option>Correo</option><option>WhatsApp</option></select></label>
                        <label class="text-sm">Fecha<input name="fecha" type="date" required value="{{ old('fecha', today()->toDateString()) }}" class="mt-1 block w-full rounded-xl border-slate-300"></label>
                        <label class="text-sm">Persona que autorizó<input name="autorizo" required maxlength="160" value="{{ old('autorizo') }}" class="mt-1 block w-full rounded-xl border-slate-300"></label>
                        <label class="text-sm">Adjunto (opcional)<input name="documento" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="mt-1 block w-full"></label>
                        <label class="text-sm sm:col-span-2">Referencia u observaciones<textarea name="referencia" required maxlength="4000" rows="2" class="mt-1 block w-full rounded-xl border-slate-300">{{ old('referencia') }}</textarea></label>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <button class="ui-btn-primary">Registrar constancia</button>
                        @php($prefijo = \App\Sistema\CatalogoPermisos::prefijo(auth()->user()->rolOperativo()))
                        @rutaDisponible($prefijo.'.compras.create')
                            <a class="ui-btn-secondary" href="{{ route($prefijo.'.compras.create', ['consolidados' => $seleccion->modelKeys()]) }}">Abrir registro de compras</a>
                        @endrutaDisponible
                    </div>
                </x-sistema.panel>
            </form>
        @endif
    @endif
    <x-sistema.panel titulo="Constancias registradas">
        <ul class="divide-y divide-slate-200 text-sm">
            @forelse($constancias as $fila)
                <li class="py-3">
                    <details>
                        <summary class="cursor-pointer font-semibold">{{ $fila->fecha->format('d/m/Y') }} · {{ $fila->autorizo }} · {{ $fila->medio }}</summary>
                        <p class="my-2 whitespace-pre-wrap">{{ $fila->referencia }}</p>
                        @if($fila->ruta)<a class="underline" href="{{ route('flujo.compras.documento', $fila) }}">Abrir adjunto</a>@endif
                        <ul class="mt-2">
                            @foreach($fila->plan as $linea)
                                <li>{{ $linea['producto'] }} · {{ $linea['propuesta'] }} unidades propuestas</li>
                            @endforeach
                        </ul>
                    </details>
                </li>
            @empty
                <li class="py-3">No hay constancias registradas.</li>
            @endforelse
        </ul>
        {{ $constancias->links() }}
    </x-sistema.panel>
</x-sistema.pagina>
