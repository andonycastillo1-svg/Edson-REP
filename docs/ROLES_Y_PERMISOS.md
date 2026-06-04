# Roles y permisos

## Middleware detectado

- `role`: alias de `App\Http\Middleware\EnsureUserRole`. Valida coincidencia exacta entre `auth()->user()->role_id` y el parámetro recibido.
- `auto.logout`: alias de `App\Http\Middleware\AutoLogoutOnInactivity`. Cierra sesión por inactividad usando `AUTO_LOGOUT_MINUTES` o `session.lifetime`.

## `role_id = 1`

- Comentario en rutas: Admin.
- Seeder: `Administrador`.
- Dashboard: `admin.dashboard`.
- Prefijo: `/admin`.
- Acceso detectado a usuarios, bodegas, inventario, entradas, compras, colaboradores, vehículos, asignaciones de vehículos, productos/refacciones de vehículos, traslados, asignaciones de inventario, devoluciones, PDFs y archivos firmados.

## `role_id = 2`

- Comentario en rutas: Operador.
- Seeder: `Encargado`.
- Dashboard: `operador.dashboard`.
- Prefijo: `/operador`.
- Acceso detectado a bodegas `index/show`, exportación de inventario, compras, asignaciones de inventario, devoluciones y traslados.
- `BodegaAccessService` limita visibilidad/modificación según bodega asignada y tipo de bodega.

## `role_id = 3`

- Comentario en rutas: Coordinador.
- Seeder: `Coordinador`.
- Dashboard: `coordinador.dashboard`.
- Ruta detectada: `/coordinador/dashboard` con `auth` y `auto.logout`.
- No se detectó grupo `role:3` ni módulos adicionales. **Pendiente de confirmar** alcance funcional.

## `role_id = 4`

- Comentario en rutas: RRHH.
- Seeder: `Consultas`.
- Dashboard: `rrhh.dashboard`.
- Prefijo: `/rrhh`.
- Acceso detectado a alertas RRHH, exportación de alertas, finalizar alertas, usuarios, colaboradores, detalle y fichas técnicas.

## Hallazgo

Los comentarios de rutas y los nombres de `RolesSeeder` no coinciden completamente para roles 2 y 4 (`Operador` vs `Encargado`, `RRHH` vs `Consultas`). No se modifica; queda documentado.
