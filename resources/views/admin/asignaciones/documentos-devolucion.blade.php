@if($accesos['exportar'] || $accesos['evidencias'] || ($accesos['archivos'] && $firmado))
    <details>
        <summary class="cursor-pointer font-semibold text-brand">Documentos de devolución</summary>
        <div class="mt-2 min-w-44 space-y-2">
            @if($accesos['exportar'])
                <a class="block flujo-enlace" href="{{ route($routePrefix.'.asignaciones.hoja_devolucion', $grupo) }}" target="_blank" rel="noopener">Hoja de devolución</a>
            @endif
            @if($accesos['archivos'] && $firmado)
                <a class="block flujo-enlace" href="{{ route($routePrefix.'.asignaciones.ver_pdf_firmado', $firmado) }}" target="_blank" rel="noopener">Ver hoja firmada</a>
            @endif
            @if($accesos['evidencias'])
                <form class="space-y-2" method="POST" action="{{ route($routePrefix.'.asignaciones.upload_pdf_devolucion_firmado', $grupo) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="ui-label">PDF firmado
                        <input class="ui-input mt-1" type="file" name="pdf_firmado" accept=".pdf" required>
                    </label>
                    <x-ui.accion type="submit">{{ $firmado ? 'Reemplazar PDF' : 'Adjuntar PDF' }}</x-ui.accion>
                </form>
            @endif
        </div>
    </details>
@else
    —
@endif
