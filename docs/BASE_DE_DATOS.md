# Base de datos

## Tablas principales detectadas

### Laravel base

- `users`, `password_reset_tokens`, `sessions`.
- `cache`, `cache_locks`.
- `jobs`, `job_batches`, `failed_jobs`.

### Seguridad

- `roles`: `id`, `nombre`, timestamps.
- `users`: agrega `role_id`, `bodega_id`, `created_by` además de campos estándar.

### Inventario

- `bodegas`: `nombre`, `ubicacion`, `tipo` (`Principal`, `Regional`).
- `productos`: PK `codigo`, `nombre`, `descripcion`, `unidad_medida`, `vida_util_meses`, `categoria`.
- `inventarios`: `producto_codigo`, `bodega_id`, `cantidad`; índice único `producto_codigo` + `bodega_id`.
- `movimientos`: producto, bodega origen/destino, tipo (`Entrada`, `Salida`, `Traslado`), cantidad, fecha, usuario, vehículo.

### Compras

- `proveedores`: `nombre`.
- `compras`: proveedor, `fecha_compra` (antes `mes`), factura, forma de pago, proyecto, solicitado/autorizado, uso, total.
- `compra_detalles`: compra, producto, cantidad, precio unitario, `valor_total`.
- `compra_archivos`: compra, ruta, nombre original, mime, tamaño.

### Colaboradores y asignaciones

- `colaboradores`: PK `codigo`, nombre, teléfono, puesto, estado (`Activo`, `Inactivo`).
- `asignaciones_inventarios`: colaborador, producto, bodega, grupo, usuario, cantidad, fecha, costo, aprobación, medio, imagen, observaciones, vencimiento, estado, PDF firmado.
- `asignacion_movimientos`: asignación, tipo, cantidad, detalle, grupo devolución, usuario.
- `asignacion_estado_historiales`: asignación, estado, fecha, detalle, usuario.
- `asignacion_inventario_archivos`: asignación, grupo devolución, tipo documento, ruta, nombre, mime, tamaño, usuario.

### Operaciones

- `operaciones`: tipo, estado, bodegas origen/destino, creador, aprobador/rechazador, fechas, observación, motivo rechazo, archivo Excel.
- `operacion_detalles`: operación, producto, cantidad.

### Vehículos

- `vehiculos`: PK `vin`, placa, marca, modelo, estado (`Disponible`, `En uso`, `Mantenimiento`).
- `asignaciones_vehiculos`: vehículo, colaborador, fechas, estados inicial/final, observaciones, activa, usuarios.
- `asignacion_vehiculo_archivos`: asignación de vehículo, tipo documento, ruta, nombre, mime, tamaño, usuario.
- `vehiculo_producto_asignaciones`: vehículo, producto, bodega, cantidad, control, serial, fecha, motivo, observaciones, estado, activa, cierre, mal uso, responsable, descuento generado.
- `vehiculo_producto_archivos`: migración usa `archivo_path`, `archivo_nombre_original`, `subido_por`; modelo usa `ruta`, `nombre_original`, `mime`, `tamano`, `subido_por_user_id`. **Pendiente de confirmar**.

### RRHH

- `alertas_reemplazos_rrhh`: colaborador, producto, vehículo, asignaciones relacionadas, fechas, vida útil, meses restantes, descuento aplicable, estado y detalle.

## Relaciones explícitas en modelos

- `User` pertenece a `Role`, `Bodega` y `User` creador.
- `Bodega` tiene muchos `Inventario` y `Movimiento` como origen/destino.
- `Inventario` pertenece a `Producto` y `Bodega`.
- `Movimiento` pertenece a `Bodega` origen/destino y `Producto`.
- `AsignacionInventario` pertenece a `Colaborador`, `Producto`, `Bodega`, `User`; tiene archivos, movimientos e historial.
- `AsignacionVehiculo` pertenece a `Vehiculo` y `Colaborador`; tiene productos y PDFs firmados.
- `VehiculoProductoAsignacion` pertenece a vehículo, producto, bodega, usuarios y colaborador responsable; tiene archivos.
- `Operacion` tiene detalles y pertenece a bodegas/usuarios relacionados.
- `OperacionDetalle` pertenece a `Operacion` y `Producto`.

## Pendiente de confirmar

- `AlertaReemplazo` tiene campos relacionales pero no define relaciones Eloquent explícitas.
- `Colaborador`, `Producto` y `Vehiculo` son referenciados por varias tablas, pero no declaran todas sus relaciones inversas.
