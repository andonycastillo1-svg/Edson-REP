# Flujos principales

## Entrada manual de inventario

1. `InventarioController@createEntrada` muestra formulario para una bodega.
2. `BodegaAccessService::canModifyStock` valida permiso.
3. `storeEntrada` valida producto y cantidad.
4. Se incrementa inventario y se registra movimiento `Entrada`.

## Compra

1. `CompraController@index/create` carga proveedores, productos y categorías.
2. `store` valida fecha, proveedor, productos, cantidades, precios y PDFs.
3. Si el proveedor o producto es nuevo, lo crea.
4. Crea compra y detalles.
5. Suma stock en bodega `Principal`.
6. Registra movimientos de entrada.
7. Guarda PDFs adjuntos en storage público.

## Traslado entre bodegas

1. `OperacionTrasladoController@create` prepara bodegas/productos disponibles.
2. `store` crea operación tipo `TRASLADO` en estado `PENDIENTE` y sus detalles.
3. Puede guardar archivo Excel/CSV adjunto.
4. `aprobar` descuenta stock de origen, incrementa destino, registra movimientos y marca `APROBADO`.
5. `rechazar` registra motivo y marca `RECHAZADO`.
6. `hoja` muestra hoja de traslado y `archivo` sirve el adjunto.

## Asignación de inventario

1. `AsignacionInventarioController@create` carga colaboradores activos y productos con inventario.
2. `store` valida colaborador, fecha, aprobador, medio, imagen e items.
3. Verifica stock y restricciones de bodega.
4. Crea `grupo_asignacion`.
5. Descuenta inventario.
6. Crea asignaciones en estado `Activa`.
7. Registra movimientos e historial.
8. Si es reemplazo por daño, llama a `AsignacionVidaUtilService`.
9. Redirige al PDF de asignación.

## Reemplazo por daño y alerta RRHH

1. Se marca un item como reemplazo por daño.
2. El sistema busca una asignación activa previa del mismo producto y colaborador.
3. `AsignacionVidaUtilService` calcula meses restantes.
4. Si quedan meses de vida útil, marca asignación anterior como `Dañada` y crea alerta RRHH en estado `pendiente`.
5. RRHH puede listar, filtrar, exportar y finalizar la alerta.

## Devoluciones

Flujos detectados en `AsignacionInventarioController`:

- `devolver`.
- `devolverLote`.
- `devolverTodoColaborador`.

Pasos generales:

1. Valida autorización y estado/cantidad.
2. Actualiza asignación.
3. Incrementa inventario.
4. Registra movimiento de devolución.
5. Puede generar hoja de devolución y cargar PDF firmado.

## Vehículos

1. `VehiculoController` realiza CRUD de vehículos.
2. `AsignacionVehiculoController@store` asigna vehículo a colaborador.
3. `desasignar` cierra asignación y actualiza datos finales.
4. Se generan PDFs de asignación/desasignación y se suben PDFs firmados.

## Productos/refacciones de vehículo

1. `VehiculoProductoController@index` lista asignaciones y datos necesarios.
2. `store` asigna producto/refacción a vehículo y descuenta inventario.
3. `cerrar` finaliza por `regresar`, `consumido`, `danado` o `baja`.
4. Si se regresa, incrementa inventario.
5. Si hay daño por mal uso, puede generar alerta RRHH.
6. Hay PDFs de asignación y devolución.

## Fichas técnicas de colaboradores

1. `ColaboradorController@detalle` devuelve información consolidada.
2. `fichaTecnica` exporta ficha individual.
3. `fichasTecnicasMasivas` exporta varias fichas.

## Alertas RRHH

1. `RrhhDashboardController@index` cuenta pendientes.
2. `alertas` lista y filtra.
3. `export` genera CSV.
4. `finalizar` cambia alerta pendiente a `finalizado` con validación de confirmación.
