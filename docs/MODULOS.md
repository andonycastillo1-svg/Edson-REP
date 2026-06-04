# Módulos

## Usuarios y roles

Controlador `UsuarioController`; modelos `User` y `Role`; tablas `users` y `roles`. Permite listar, crear, editar, actualizar y eliminar usuarios según rol. RRHH reutiliza el controlador con restricciones internas de roles permitidos.

## Bodegas e inventario

Controladores `BodegaController` e `InventarioController`; modelos `Bodega`, `Inventario`, `Movimiento`. Permite CRUD de bodegas para admin, consulta de bodegas para operador, exportación de inventario y entradas manuales. `InventarioStockService` incrementa/descuenta stock y `BodegaAccessService` valida alcance por bodega.

## Compras

Controlador `CompraController`; tablas `proveedores`, `compras`, `compra_detalles`, `compra_archivos`. Registra compras, proveedores/productos nuevos o existentes, PDFs adjuntos e incrementa stock en bodega principal.

## Traslados / operaciones

Controlador `OperacionTrasladoController`; modelos `Operacion`, `OperacionDetalle`. Gestiona solicitudes de traslado, aprobación, rechazo, hoja de traslado y archivo Excel/CSV adjunto.

## Colaboradores

Controlador `ColaboradorController`; modelo `Colaborador`. Administra colaboradores activos/inactivos, cambia estado, muestra detalle y genera fichas técnicas CSV individuales o masivas.

## Asignaciones de inventario

Controlador `AsignacionInventarioController`; modelos `AsignacionInventario`, `AsignacionMovimiento`, `AsignacionInventarioArchivo`, `AsignacionEstadoHistorial`. Permite asignar productos a colaboradores, generar PDFs, subir PDFs firmados, devolver productos y registrar movimientos/historial.

## Vehículos

Controlador `VehiculoController`; modelo `Vehiculo`. CRUD de vehículos con VIN como clave primaria.

## Asignaciones de vehículos

Controlador `AsignacionVehiculoController`; modelo `AsignacionVehiculo` y archivos `AsignacionVehiculoArchivo`. Permite asignar/desasignar vehículos, generar PDFs y subir/consultar PDFs firmados.

## Productos/refacciones de vehículos

Controlador `VehiculoProductoController`; modelo `VehiculoProductoAsignacion`. Permite asignar productos/refacciones a vehículos, cerrarlos por regreso/consumo/daño/baja, devolver stock y generar alertas RRHH si hay mal uso.

## Alertas RRHH

Controlador `RrhhDashboardController`; modelo `AlertaReemplazo`. Lista, filtra, exporta y finaliza alertas de descuentos potenciales. Estados detectados: `pendiente`, `finalizado`.

## PDFs y archivos

Existen vistas para PDFs de asignaciones, devoluciones, vehículos, productos de vehículo y traslados. También existen flujos de carga de PDFs firmados y adjuntos de compras.
