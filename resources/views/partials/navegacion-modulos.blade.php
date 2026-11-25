@auth
    @php
        $usuario = auth()->user();
        $ambito = $usuario->rolOperativo();
        $prefijo = \App\Sistema\CatalogoPermisos::prefijo($ambito);
        $permisos = app(\App\Sistema\Permisos::class);
        $flujo = app(\App\Services\FlujoAcceso::class);
        $grupos = [
            'Inventario' => [
                ["$prefijo.bodegas.index", "$prefijo.bodegas.*", 'Bodegas e inventario', 'bodega', $permisos->ruta($usuario, "$prefijo.bodegas.index")],
                ["$prefijo.compras.index", "$prefijo.compras.*", 'Compras', 'compra', $permisos->ruta($usuario, "$prefijo.compras.index") && app(\App\Services\BodegaAccessService::class)->canViewCompras($usuario)],
                ["$prefijo.asignaciones.index", "$prefijo.asignaciones.*", 'Asignaciones y devoluciones', 'caja', $permisos->ruta($usuario, "$prefijo.asignaciones.index")],
                ["$prefijo.operaciones.traslados.index", "$prefijo.operaciones.traslados.*", 'Traslados', 'traslado', $permisos->ruta($usuario, "$prefijo.operaciones.traslados.index")],
            ],
            'Requerimientos y compras' => [
                ['flujo.existencias.index', 'flujo.existencias.*', 'Existencias autorizadas', 'bodega',
                    ($permisos->permite($usuario, 'bodegas.consultar') && app(\App\Services\DelegacionesOperativas::class)->bodegasTitular($usuario) !== [] && !$permisos->ruta($usuario, "$prefijo.bodegas.index"))
                    || app(\App\Services\DelegacionesOperativas::class)->bodegas($usuario, 'bodegas.consultar') !== []
                    || $permisos->permite($usuario, 'inventario.movimientos')],
                ['flujo.inspecciones.index', ['flujo.inspecciones.*', 'flujo.cortes.*'], 'Inspecciones', 'lista', $flujo->puede($usuario, 'inspecciones')],
                ['flujo.consolidados.index', 'flujo.consolidados.*', 'Consolidados', 'capas', $flujo->puede($usuario, 'consolidados')],
                ['flujo.compras.index', 'flujo.compras.*', 'Necesidades de compra', 'compra', $flujo->puede($usuario, 'compras')],
                ['flujo.importar.index', 'flujo.importar.*', 'Asignaciones por Excel', 'archivo', $flujo->puede($usuario, 'importar') && $permisos->permite($usuario, 'asignaciones.crear')],
                ['flujo.catalogos', 'flujo.catalogos*', 'Regiones y catálogos', 'lista', $flujo->puede($usuario, 'configurar')],
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
    @endphp
    <aside id="modulos-operativos" class="ui-rail">
        <nav aria-label="Módulos" class="ui-rail-list">
            <a class="ui-rail-link" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard', '*.dashboard')) aria-current="page" @endif>
                <x-ui.icono nombre="inicio" /> Inicio
            </a>
            @foreach($grupos as $grupo => $enlaces)
                @php($visibles = array_filter($enlaces, fn ($enlace) => $enlace[4]))
                @if($visibles)
                    <div class="ui-rail-group">
                        <p>{{ $grupo }}</p>
                        @foreach($visibles as [$ruta, $patron, $nombre, $icono])
                            <a class="ui-rail-link" href="{{ route($ruta) }}" @if(request()->routeIs(...(array) $patron)) aria-current="page" @endif>
                                <x-ui.icono :nombre="$icono" /> <span>{{ $nombre }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
            <x-sistema.menu navegacion />
        </nav>
    </aside>
@endauth
