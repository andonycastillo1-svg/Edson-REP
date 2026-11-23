<ul class="mt-2 space-y-1 text-sm">
    @forelse($facturas as $factura)
        <li class="flex flex-wrap items-center gap-x-2">
            @rutaDisponible($prefijo.'.compras.show')
                <a class="font-semibold underline" href="{{ route($prefijo.'.compras.show', $factura->id) }}">Factura {{ $factura->no_factura }}</a>
            @else
                <span class="font-semibold">Factura {{ $factura->no_factura }}</span>
            @endrutaDisponible
            <span>{{ $factura->proveedor }} · {{ \Illuminate\Support\Carbon::parse($factura->fecha_compra)->format('d/m/Y') }}</span>
            @if(!$factura->distribucion_confirmada_en)<span class="font-semibold text-amber-800">Pendiente de conciliación</span>@endif
        </li>
    @empty
        <li class="text-slate-600">Sin facturas vinculadas.</li>
    @endforelse
</ul>
