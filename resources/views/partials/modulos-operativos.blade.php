    @php
        $usuario = auth()->user();
        $ambito = $usuario->rolOperativo();
        $prefijo = \App\Sistema\CatalogoPermisos::prefijo($ambito);
        $permisos = app(\App\Sistema\Permisos::class);
        $operativo = \App\Services\AccesoInventarioOperativo::prefijo($usuario);
        $compras = $permisos->ruta($usuario, "$operativo.compras.index") ? 'index' : 'create';
        $asignaciones = $permisos->ruta($usuario, "$operativo.asignaciones.index") ? 'index' : 'create';
        $traslados = $permisos->ruta($usuario, "$operativo.operaciones.traslados.index") ? 'index' : 'create';
        $flujo = app(\App\Services\FlujoAcceso::class);
        $grupos = [

            'Inventario' => [
                ["$prefijo.bodegas.index", "$prefijo.bodegas.*", 'Bodegas e inventario', 'bodega', $permisos->ruta($usuario, "$prefijo.bodegas.index")],
                ["$operativo.compras.$compras", ['admin.compras.*', 'operador.compras.*'], 'Compras', 'compra', $permisos->ruta($usuario, "$operativo.compras.$compras")],
                ["$operativo.asignaciones.$asignaciones", ['admin.asignaciones.*', 'operador.asignaciones.*', 'supervisor.asignaciones.*', 'flujo.importar.*'], 'Asignaciones y devoluciones', 'caja', $permisos->ruta($usuario, "$operativo.asignaciones.$asignaciones")],
                ["$operativo.operaciones.traslados.$traslados", '*.operaciones.traslados.*', 'Traslados', 'traslado', $permisos->ruta($usuario, "$operativo.operaciones.traslados.$traslados")],
            ],
            'Requerimientos y compras' => [
                ['flujo.existencias.index', 'flujo.existencias.*', 'Existencias autorizadas', 'bodega',
                    ($permisos->permite($usuario, 'bodegas.consultar') && app(\App\Services\DelegacionesOperativas::class)->bodegasTitular($usuario) !== [] && !$permisos->ruta($usuario, "$prefijo.bodegas.index"))
                    || app(\App\Services\DelegacionesOperativas::class)->bodegas($usuario, 'bodegas.consultar') !== []
                    || $permisos->permite($usuario, 'inventario.movimientos')],
                ['flujo.inspecciones.index', ['flujo.inspecciones.*', 'flujo.cortes.*'], 'Inspecciones', 'lista', $flujo->puede($usuario, 'inspecciones')],
                ['flujo.consolidados.index', 'flujo.consolidados.*', 'Consolidados', 'capas', $flujo->puede($usuario, 'consolidados')],
                ['flujo.compras.index', 'flujo.compras.*', 'Necesidades de compra', 'compra', $flujo->puede($usuario, 'compras')],
                ['flujo.catalogos', 'flujo.catalogos*', 'Catálogos operativos', 'lista', $flujo->puede($usuario, 'configurar')],
            ],
            'Personal' => [
                ["$prefijo.colaboradores.index", "$prefijo.colaboradores.*", 'Colaboradores', 'personas', $permisos->ruta($usuario, "$prefijo.colaboradores.index")],
                ["$prefijo.usuarios.index", "$prefijo.usuarios.*", 'Usuarios', 'usuario', $permisos->ruta($usuario, "$prefijo.usuarios.index")],
                ['rrhh.alertas.index', 'rrhh.alertas.*', 'Incidencias de RR. HH.', 'alerta', $permisos->ruta($usuario, 'rrhh.alertas.index')],
            ],
            'Flota' => [
                ['admin.vehiculos.index', ['admin.vehiculos.index', 'admin.vehiculos.show', 'admin.vehiculos.create', 'admin.vehiculos.edit'], 'Vehículos', 'vehiculo', $permisos->ruta($usuario, 'admin.vehiculos.index')],
                ['admin.vehiculos.asignaciones.index', 'admin.vehiculos.asignaciones.*', 'Asignaciones de vehículos', 'caja', $permisos->ruta($usuario, 'admin.vehiculos.asignaciones.index')],
                ['admin.vehiculos.productos.index', 'admin.vehiculos.productos.*', 'Productos del vehículo', 'herramienta', $permisos->ruta($usuario, 'admin.vehiculos.productos.index')],
            ],
        ];
        $grupos = collect($grupos)->map(fn ($enlaces) => array_values(array_filter($enlaces, fn ($enlace) => $enlace[4])))
            ->filter(fn ($enlaces) => $enlaces !== []);
    @endphp
@if($superficie === 'panel')
    @forelse($grupos as $grupo => $enlaces)
        <section class="space-y-3">
            <h2 class="text-base font-semibold">{{ $grupo }}</h2>
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($enlaces as [$ruta, $patron, $nombre, $icono])
                    @php($pendientes = request()->routeIs('coordinacion.dashboard') && $ruta === 'flujo.consolidados.index')
                    <li class="min-w-0">
                        <a href="{{ route($ruta, $pendientes ? ['estado' => 'pendientes'] : []) }}" class="ui-module-card">
                            <span class="ui-module-icon"><x-ui.icono :nombre="$icono" /></span>
                            <span class="min-w-0 grow">
                                <span class="block font-semibold">{{ $pendientes ? 'Consolidados pendientes' : $nombre }}</span>
                                @if(isset($notas[$ruta]))<span class="mt-1 block text-sm">{{ $notas[$ruta] }}</span>@endif
                            </span>
                            <x-ui.icono nombre="flecha" class="shrink-0 text-muted" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="ui-empty-state">Tu cuenta no tiene módulos operativos disponibles.</p>
    @endforelse
@else
    @foreach($grupos as $grupo => $enlaces)
        <div class="ui-rail-group">
            <p>{{ $grupo }}</p>
            @foreach($enlaces as [$ruta, $patron, $nombre, $icono])
                <a class="ui-rail-link" href="{{ route($ruta) }}" @if(request()->routeIs(...(array) $patron)) aria-current="page" @endif>
                    <x-ui.icono :nombre="$icono" /> <span>{{ $nombre }}</span>
                </a>
            @endforeach
        </div>
    @endforeach
@endif
