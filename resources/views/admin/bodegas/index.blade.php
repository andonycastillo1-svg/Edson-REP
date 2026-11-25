@extends('layouts.app')

@section('title', 'Bodegas')

@section('content')
@php
    $usuario = auth()->user();
    $permisos = app(\App\Sistema\Permisos::class);
    $esAdmin = $usuario->rolOperativo() === 1;
    $prefijo = $esAdmin ? 'admin' : 'operador';
    $traslados = $prefijo.'.operaciones.traslados';
    $origenes = $permisos->ruta($usuario, $traslados.'.create')
        ? app(\App\Services\AccesoInventarioOperativo::class)->bodegasPara($usuario, 'traslados.crear')
        : [];
    $puedeEditar = $esAdmin && $permisos->ruta($usuario, 'admin.bodegas.edit');
    $puedeEliminar = $esAdmin && $permisos->ruta($usuario, 'admin.bodegas.destroy');
    $puedeAgregarStock = $esAdmin && $permisos->ruta($usuario, 'admin.bodegas.entrada');
@endphp

<div class="space-y-4">
    <x-ui.encabezado titulo="Bodegas" descripcion="Consulta las existencias y las operaciones disponibles por bodega.">
        <x-ui.exportar-excel modulo="bodegas" />
        @if($esAdmin && $permisos->ruta($usuario, $traslados.'.index'))
            <x-ui.accion :href="route($traslados.'.index')" icono="traslado">Revisar traslados</x-ui.accion>
        @endif
        @if($esAdmin && $permisos->ruta($usuario, 'admin.bodegas.create'))
            <x-ui.accion :href="route('admin.bodegas.create')" variante="primaria">Nueva bodega</x-ui.accion>
        @endif
    </x-ui.encabezado>

    <div class="flex flex-wrap items-center gap-2">
        <x-sistema.enlace-datos modulo="bodegas" />
    </div>
    <x-sistema.consulta-campos :tabla="$tablaCampos" />
    <p class="text-sm">{{ $bodegas->count() }} bodegas · {{ $bodegas->where('tipo', 'Principal')->count() }} principales · {{ $bodegas->where('tipo', 'Regional')->count() }} regionales</p>

    <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
        @forelse($bodegas as $bodega)
            @php
                $esPropia = (int) $bodega->id === (int) $usuario->bodega_id;
                $puedeTrasladar = in_array($bodega->id, $origenes, true);
                $soloConsulta = !$puedeTrasladar && !$puedeEditar && !$puedeEliminar && !$puedeAgregarStock;
            @endphp

            <article class="ui-card flex min-w-0 flex-col gap-4 p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="ui-module-icon"><x-ui.icono nombre="bodega" /></span>
                    <div class="min-w-0">
                        <h2 class="break-words text-lg font-semibold">{{ $bodega->nombre ?? ('Bodega #'.$bodega->id) }}</h2>
                        <p class="mt-1 break-words text-sm">{{ $bodega->ubicacion ?: 'Sin ubicación registrada' }} · ID {{ $bodega->id }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.estado :variante="$bodega->tipo === 'Principal' ? 'informacion' : 'neutra'">{{ $bodega->tipo ?? 'Sin tipo' }}</x-ui.estado>
                    @if($esPropia)<x-ui.estado>Bodega asignada</x-ui.estado>@endif
                    @if(!$esAdmin && !$esPropia && $puedeTrasladar)<x-ui.estado>Acceso operativo</x-ui.estado>@endif
                    @if($soloConsulta)<x-ui.estado>Solo consulta</x-ui.estado>@endif
                </div>
                <dl class="grid grid-cols-2 gap-2 rounded-lg bg-subtle p-3 text-sm">
                    <div><dt class="text-muted">Productos</dt><dd class="mt-1 font-semibold tabular-nums">{{ number_format($bodega->items_count) }}</dd></div>
                    <div><dt class="text-muted">Unidades disponibles</dt><dd class="mt-1 font-semibold tabular-nums">{{ number_format($bodega->stock_total) }}</dd></div>
                </dl>
                <x-sistema.detalle-campos :tabla="$tablaCampos" :registro="$bodega->id" />

                <div class="ui-card-actions mt-auto">
                    @rutaDisponible($prefijo.'.bodegas.show')
                        <x-ui.accion :href="route($prefijo.'.bodegas.show', $bodega)" icono="caja">Inventario</x-ui.accion>
                    @endrutaDisponible
                    @if($puedeTrasladar)
                        <x-ui.accion :href="route($traslados.'.create', ['origen' => $bodega->id])" icono="traslado">Trasladar</x-ui.accion>
                    @endif
                    @if($puedeEditar)
                        <x-ui.accion :href="route('admin.bodegas.edit', $bodega)">Editar</x-ui.accion>
                    @endif
                    @if($puedeEliminar)
                        <form method="POST" action="{{ route('admin.bodegas.destroy', $bodega) }}" data-confirmar="¿Eliminar esta bodega?">
                            @csrf @method('DELETE')
                            <x-ui.accion variante="riesgo" type="submit">Eliminar</x-ui.accion>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p class="ui-empty-state sm:col-span-full">No hay bodegas con estos filtros.</p>
        @endforelse
    </div>
</div>
@endsection
