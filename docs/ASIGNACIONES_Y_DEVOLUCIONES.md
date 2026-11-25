# Expediente, asignaciones y devolución completa

## Flujo de trabajo

1. En **Recursos Humanos → Colaboradores → Ver expediente**, abre **Registrar devolución**.
2. Revisa el código, la región, la fecha y el responsable. La pantalla incluye toda la custodia abierta, incluso los artículos anteriores cuyo retorno sigue pendiente, y muestra su bodega de origen.
3. Distribuye cada cantidad pendiente entre **Recibido en buen estado**, **Recibido dañado** y **No devuelto**. Se comprueban las cantidades de todas las filas en una sola operación.
4. Confirma. Las unidades recuperadas en buen estado vuelven como usadas, con su vida útil restante. Las dañadas entran a saldo no asignable. Las unidades no devueltas se registran en el historial y como incidencia sin entrada de inventario.
5. Se abre el PDF de la devolución completa. El expediente y el historial de movimientos permiten descargarlo después, de acuerdo con el alcance autorizado.

La inactivación del colaborador se administra por separado. El registro de la devolución no cambia su estado laboral. El permiso individual **Registrar devoluciones** y las relaciones o delegaciones vigentes deben cubrir toda la custodia. Cuando hay varias bodegas, un mismo alcance efectivo debe cubrir todo el grupo. Las autorizaciones que solo cubren parte de la custodia bloquean la confirmación.

## Compatibilidad y almacenamiento

El PR retira la migración `2026_11_25_000003_create_bajas_laborales.php` antes de integrarla a `main`. Se reutilizan las tablas y relaciones existentes. El PDF generado se archiva en el disco privado, vinculado al grupo de movimientos. Los PDFs históricos y sus versiones firmadas siguen disponibles con sus permisos. La confirmación y el archivo del PDF forman parte de la misma operación.

Las integraciones deben enviar la custodia completa a `POST /devoluciones/colaborador/{codigo}`, con `solicitud_id` y las cantidades por condición e identificador de asignación. Las rutas anteriores de devolución parcial rechazan la operación. La ruta anterior de devolución total usa la misma validación. Un permiso operativo de devolución no concede acceso a compras, traslados ni otras tareas de inventario.

Si se probó la primera revisión del PR en una base local, esa base puede conservar la tabla de bajas. La actualización actual prescinde de ella y no la elimina. Para validar desde cero, usa un entorno local nuevo con el esquema de `main`.

## Resultados

- PHPUnit completó 151 pruebas y 3108 aserciones, incluidas devoluciones mixtas, permisos, PDF y reversión de la transacción si falla el almacenamiento del documento.
- MySQL 8.4.13 verificó el esquema de `main`, los filtros, la concurrencia y la conciliación de inventario por unidad y por lotes. La devolución simultánea produjo un solo PDF y solo los artículos recuperados sumaron existencias.
- Chromium 156 verificó el flujo con cuatro perfiles a 1366 y 390 px. Se comprobó la descarga histórica del mismo PDF, la denegación de accesos fuera de ámbito y la navegación por asignaciones, hojas pendientes, historial, Compras e importaciones. El texto del PDF incluyó los artículos recuperados y los no devueltos.

## Capturas

| Pantalla | Evidencia |
| --- | --- |
| Expediente en escritorio | [Ver captura](capturas/asignaciones-devoluciones/expediente-escritorio.png) |
| Verificación en escritorio | [Ver captura](capturas/asignaciones-devoluciones/devolucion-escritorio.png) |
| Verificación en móvil | [Ver captura](capturas/asignaciones-devoluciones/devolucion-movil.png) |
| Historial del expediente en móvil | [Ver captura](capturas/asignaciones-devoluciones/historial-devolucion-movil.png) |
| Acta en PDF | [Ver captura](capturas/asignaciones-devoluciones/hoja-devolucion.png) |

## Actualización y pruebas locales

```sh
composer install
npm ci
php artisan migrate
npm run build
php artisan view:cache
php vendor/bin/phpunit --do-not-cache-result
```

La actualización instala Dompdf desde `composer.lock` para generar PDFs en el servidor. El disco privado `documentos` debe ser escribible por Laravel. Para repetir las pruebas MySQL usa una base desechable local `edson_*_qa` con `APP_ENV=testing` y el esquema migrado.

```sh
INVENTARIO_SEGUIMIENTO_UNIDADES=true php tests/Integration/entregas_mysql.php
INVENTARIO_SEGUIMIENTO_UNIDADES=false php tests/Integration/entregas_mysql.php
```

La [guía de custodia](ENTREGAS_CUSTODIA_Y_REVISION.md) documenta los flujos de asignaciones, reemplazos y devoluciones.
