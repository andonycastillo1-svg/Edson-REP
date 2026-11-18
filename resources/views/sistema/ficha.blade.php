<x-sistema.pagina :titulo="$nombre.' · '.$registro" :volver="route('sistema.datos.lista', $modulo)">
    <x-sistema.panel titulo="Campos personalizados">
        @php($servicio = app(\App\Sistema\ValoresCampos::class))
        @if($campos->isEmpty())
            <p class="text-sm text-slate-600">No hay campos disponibles para este registro.</p>
        @endif
        <dl class="space-y-3">
            @foreach($campos as $campo)
                <div class="border-b border-slate-100 pb-2">
                    <dt class="font-semibold">{{ $campo->etiqueta }}</dt>
                    <dd class="whitespace-pre-wrap text-sm text-slate-700">@if($campo->tipo === 'archivo' && $actuales->get($campo->id) && app(\App\Sistema\Permisos::class)->permite(auth()->user(), $modulo.'.datos_archivos'))<a class="font-semibold text-blue-700" href="{{ route('sistema.datos.archivo', [$modulo, $registro, $campo]) }}">{{ $servicio->mostrar($campo, $actuales->get($campo->id)) }}</a>@else{{ $servicio->mostrar($campo, $actuales->get($campo->id)) ?: 'Sin valor' }}@endif</dd>
                </div>
            @endforeach
        </dl>
        @if($editar)
            <form method="POST" enctype="multipart/form-data" action="{{ route('sistema.datos.actualizar', [$modulo, $registro]) }}" class="mt-5 space-y-4">
                @csrf @method('PUT')
                <x-sistema.campos :modulo="$modulo" :registro="$registro" />
                <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Guardar campos</button>
            </form>
        @endif
    </x-sistema.panel>
    @if($historial->isNotEmpty() || $valoresArchivados->isNotEmpty())
        <x-sistema.panel titulo="Historial de campos">
            @php($descargar = app(\App\Sistema\Permisos::class)->permite(auth()->user(), $modulo.'.datos_archivos'))
            <ul class="divide-y divide-slate-200 text-sm">
                @foreach($valoresArchivados as $id => $valor)
                    @php($campo = $catalogoHistorico->get($id))
                    <li class="py-2"><strong>{{ $campo->etiqueta }}</strong> · Inactivo o archivado ·
                        @if($campo->tipo === 'archivo' && $valor && $descargar)
                            <a class="font-semibold text-blue-700" href="{{ route('sistema.datos.archivo', [$modulo,$registro,$campo]) }}">{{ $servicio->mostrar($campo, $valor) }}</a>
                        @else
                            {{ $servicio->mostrar($campo, $valor) }}
                        @endif
                    </li>
                @endforeach
                @foreach($historial as $version)
                    @php($campo = $catalogoHistorico->get($version->campo_id))
                    @php($valor = json_decode($version->valor, true))
                    <li class="py-2"><strong>{{ $campo->etiqueta }}</strong> · {{ $version->created_at }} ·
                        @if($campo->tipo === 'archivo' && $valor && $descargar)
                            <a class="font-semibold text-blue-700" href="{{ route('sistema.datos.archivo', [$modulo,$registro,$campo,'version' => $version->id]) }}">{{ $servicio->mostrar($campo, $valor) }}</a>
                        @else
                            {{ $servicio->mostrar($campo, $valor) }}
                        @endif
                    </li>
                @endforeach
            </ul>
            {{ $historial->links() }}
        </x-sistema.panel>
    @endif
</x-sistema.pagina>
