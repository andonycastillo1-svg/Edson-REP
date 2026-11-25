# Expediente, asignaciones y bajas laborales

La rama parte de `main` después del PR #104. Reutiliza la paleta y los componentes corporativos. Los listados llevan a pantallas de detalle y conservan los filtros al cambiar de página.

## Pantallas

| Pantalla | Cambio |
| --- | --- |
| Colaboradores y expediente | El expediente ocupa una página con nombre, código, puesto, región y estado. Secciones de resumen, custodia e incidencias, historial paginado y ficha Excel. |
| Asignaciones y devoluciones | Búsqueda por nombre o código, 10 colaboradores por defecto y opciones de 25 o 50. El listado agrupa los totales por colaborador y muestra la custodia y los documentos en páginas de detalle. |
| Hojas firmadas faltantes | Una fila por entrega pendiente, con fecha, persona y acceso autorizado a la documentación. |
| Historial de movimientos | Pantalla separada con fechas, colaborador, producto, tipo y bodega. Pagina 10, 25 o 50 filas. Agrupa los documentos de devolución y aplica los filtros a la exportación disponible. |
| Baja laboral | RR. HH. registra renuncia o despido. La bodega comprueba cantidades y condición de cada artículo dentro de su ámbito, con registro separado de los faltantes. |
| Compras | El servidor busca y pagina las facturas. Se mantienen la exportación y los dos accesos de registro de compras. |

El flujo completo de retorno, vida útil y reemplazo está en la [guía de entregas y custodia](ENTREGAS_CUSTODIA_Y_REVISION.md).

## Datos y compatibilidad

La migración `2026_11_25_000003_create_bajas_laborales.php` crea `bajas_laborales` y agrega referencias opcionales en `asignacion_movimientos` e `incidencias_inventario`. Una cuenta inactiva histórica requiere una baja verificada y registrada por personal autorizado antes de iniciar una devolución. Los documentos y movimientos anteriores conservan sus relaciones. Si hay bajas registradas, la migración bloquea su reversión destructiva.

Los clientes que llamen las rutas de devolución deben enviar el identificador de baja vigente. El servidor obtiene el motivo del registro de RR. HH. Las devoluciones simultáneas conservan los controles de repetición que ya usaban las asignaciones. Si la revisión cubre ámbitos autorizados por delegaciones diferentes, registra por separado los artículos cubiertos por cada autorización.

Cuando una custodia histórica agrupa varias unidades, una unidad verificada durante un reemplazo se procesa sin cerrar las demás. Si se desconoce qué ocurrió con el artículo anterior, se conserva el saldo y su historial. La verificación posterior de un retorno laboral exige una baja vigente con fecha efectiva.

## Pruebas

- PHPUnit en SQLite en memoria pasó 151 pruebas y 3157 aserciones, incluidos los casos de renuncia, despido, inactivación general, faltantes, retorno físico, reintentos, documentos, filtros, reemplazos y Excel.
- MySQL 8.4.13 en una base local desechable. Se comprobó la actualización desde el PR #104 con registros históricos y la concurrencia en los modos de inventario por unidad y por lotes. Se verificaron los filtros de asignaciones, hojas pendientes e historial también con MySQL.
- Chromium 156 completó 61 comprobaciones de ruta y ancho, a 1366 y 390 px, con seis perfiles de prueba y sin errores de JavaScript detectados. Se revisaron tanto los módulos intervenidos como la navegación de usuarios, permisos, delegaciones, cortes, inspecciones, consolidados, bodegas, compras, traslados y catálogos. La sección de vehículo se revisó en el código, ya que el módulo está deshabilitado en la configuración de prueba.

## Capturas

| Escritorio | Móvil |
| --- | --- |
| [Expediente](capturas/asignaciones-bajas/expediente-escritorio.png) | [Productos asignados](capturas/asignaciones-bajas/expediente-movil.png) |
| [Listado de asignaciones](capturas/asignaciones-bajas/asignaciones-escritorio.png) | [Hojas firmadas pendientes](capturas/asignaciones-bajas/firmas-movil.png) |
| [Detalle del artículo](capturas/asignaciones-bajas/detalle-articulo.png) | [Baja en RR. HH.](capturas/asignaciones-bajas/baja-rrhh-movil.png) |
| [Historial de movimientos](capturas/asignaciones-bajas/historial-escritorio.png) | [Verificación en bodega](capturas/asignaciones-bajas/verificacion-bodega-movil.png) |

## Actualización local

En una base local de desarrollo configurada para Edson-REP, instala las dependencias y aplica la migración antes de abrir las pantallas nuevas.

```sh
composer install
npm ci
php artisan migrate
npm run build
php artisan view:cache
php vendor/bin/phpunit --do-not-cache-result
```

Para repetir la prueba de concurrencia, configura una base MySQL local desechable `edson_*_qa` con `APP_ENV=testing`, migra y ejecuta el script en ambos modos. Los detalles del entorno se encuentran en la [guía de pruebas de custodia](ENTREGAS_CUSTODIA_Y_REVISION.md#pruebas-automatizadas).

```sh
INVENTARIO_SEGUIMIENTO_UNIDADES=true php tests/Integration/entregas_mysql.php
INVENTARIO_SEGUIMIENTO_UNIDADES=false php tests/Integration/entregas_mysql.php
```
