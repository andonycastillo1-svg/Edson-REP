# Documentación general del sistema

Documentación generada a partir del código real del repositorio Laravel. Cuando algo no está definido explícitamente en rutas, controladores, modelos, servicios o migraciones, se marca como **Pendiente de confirmar**.

## Qué hace el sistema

El proyecto es un sistema Laravel para administrar inventario, bodegas, compras, traslados, colaboradores, asignaciones de inventario, vehículos/flota, productos o refacciones instaladas/asignadas a vehículos y alertas RRHH por posibles descuentos cuando un producto es reemplazado o dañado antes de completar su vida útil.

## Módulos principales detectados

- Autenticación, perfil de usuario y cierre de sesión.
- Dashboard general con redirección por `role_id`.
- Usuarios y roles.
- Bodegas e inventario por bodega.
- Entradas manuales de inventario.
- Compras, proveedores, detalles y PDFs adjuntos.
- Operaciones / traslados entre bodegas.
- Colaboradores y fichas técnicas.
- Asignaciones de inventario a colaboradores.
- Devoluciones de inventario asignado.
- Historial de estados de asignaciones.
- Vehículos y asignaciones de vehículos.
- Productos/refacciones de vehículos.
- PDFs generados y archivos firmados.
- Alertas RRHH de descuentos potenciales.

## Flujo general

1. El usuario entra por `/login` y autentica sesión.
2. `/dashboard` redirige según `role_id`: 1 admin, 2 operador, 3 coordinador, 4 RRHH.
3. Las rutas internas usan middleware `auth` y `auto.logout`; algunos grupos usan `role:<id>`.
4. Admin puede gestionar la mayoría de módulos administrativos.
5. Operador trabaja con bodegas, compras, asignaciones y traslados según rutas detectadas.
6. RRHH gestiona colaboradores, usuarios y alertas de descuento.
7. El inventario cambia por compras, entradas manuales, traslados, asignaciones, devoluciones y cierres de productos de vehículo.
8. El sistema genera vistas tipo PDF y permite subir archivos firmados en varios flujos.

## Áreas de código analizadas

- `routes/web.php` y `routes/auth.php`.
- `app/Http/Controllers/Admin`.
- `app/Http/Controllers/RrhhDashboardController.php`.
- `app/Models`.
- `app/Services`.
- `database/migrations`.
- `resources/views/admin` y vistas relacionadas.
