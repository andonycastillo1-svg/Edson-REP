# Rediseño corporativo de Edson-REP

El rediseño parte de `main` en `9305b07`. Las pantallas comparten la identidad de Grupo NetSolutions, la navegación por perfil y las convenciones de formularios, tablas y estados. Las rutas, los permisos del servidor, los datos y los cálculos operativos se conservan.

## Cómo se aplicó

Se identificaron los 141 archivos Blade de la versión inicial. Las pantallas web usan los layouts corporativos y los componentes del [sistema de diseño](../design-system/edson-rep/MASTER.md). Los diseños de impresión usan su propia hoja de estilo compatible con el documento.

| Familia de vistas | Cambios de interfaz |
| --- | --- |
| Acceso, cuenta y errores | Identidad corporativa, formularios de acceso y recuperación, foco y errores de validación, y páginas de estado. |
| Paneles y navegación | Menú persistente en escritorio y menú plegable en móvil. Accesos filtrados por los permisos existentes para Administración, Operador central o regional, Supervisión, Coordinación y RR. HH. |
| Administración, configuración y personal | Usuarios, seguridad, roles, auditoría, notificaciones, catálogos, regiones, campos configurables y expedientes. |
| Inventario y compras | Bodegas, existencias, historial, movimientos, productos, filtros, proveedores y facturas. El modal de compra permite volver al campo con error conservando la captura. |
| Operaciones | Asignaciones, devoluciones, entregas, traslados, vehículos, refacciones, documentación y consultas de RR. HH. |
| Requerimientos | Inspección, consolidación, revisión, necesidades de compra, seguimiento de facturas e importación de Excel. Se conservan sus controles y estados operativos. |
| Documentos | Hojas de entrega, devolución, traslado, vehículos y refacciones. Se armonizan los colores y la jerarquía; se conserva el contenido de cada formato. |

Las combinaciones de texto principal, texto secundario, acción azul y navegación se comprobaron con la fórmula de contraste WCAG. Obtuvieron 14,21:1, 6,44:1, 8,11:1 y 8,33:1 respectivamente.

## Capturas comparativas

Las capturas usan datos ficticios y la misma configuración para el antes y el después.

| Pantalla | Antes | Después |
| --- | --- | --- |
| Inicio de sesión | [Antes](capturas/rediseno-corporativo/01-acceso-antes.webp) | [Después](capturas/rediseno-corporativo/01-acceso-despues.webp) |
| Panel de administración | [Antes](capturas/rediseno-corporativo/02-panel-antes.webp) | [Después](capturas/rediseno-corporativo/02-panel-despues.webp) |
| Inventario de bodega | [Antes](capturas/rediseno-corporativo/03-inventario-antes.webp) | [Después](capturas/rediseno-corporativo/03-inventario-despues.webp) |
| Necesidades de compra | [Antes](capturas/rediseno-corporativo/04-necesidades-antes.webp) | [Después](capturas/rediseno-corporativo/04-necesidades-despues.webp) |
| Inspección en móvil | [Antes](capturas/rediseno-corporativo/05-inspeccion-movil-antes.webp) | [Después](capturas/rediseno-corporativo/05-inspeccion-movil-despues.webp) |
| Compras por factura | [Antes](capturas/rediseno-corporativo/06-compras-antes.webp) | [Después](capturas/rediseno-corporativo/06-compras-despues.webp) |

## Verificación

- `npm run build` y `php artisan view:cache` completados.
- Suite PHPUnit completa en SQLite en memoria. 105 pruebas y 2024 aserciones.
- Chromium con seis perfiles y 107 comprobaciones de páginas y accesos. Los resultados de autorización coincidieron con la versión anterior. El recorrido incluyó anchuras de 375, 768, 1280 y 1440 px, movimiento reducido y cierre de sesión desde el menú móvil.
- Interacciones comprobadas en navegador. Inicio de sesión con error recuperable, menú por teclado, filtros de facturas e inventario, paginación, modales de compra y expediente, cancelación de una confirmación, conservación de lo capturado tras un error y visualización de una hoja de traslado.

## Consideraciones para revisión

Las tablas operativas con muchas columnas pueden requerir desplazamiento dentro de su propio contenedor. Se revisó Chromium con distintas anchuras y orientación horizontal. La compatibilidad con dispositivos físicos, otros motores de navegador y configuraciones de impresoras queda fuera de esa verificación.
