# Despliegue de las correcciones de auditoría

La rama incluye una migración aditiva y usa el esquema existente para las compras, asignaciones, devoluciones y traslados. El cambio no calcula ni corrige saldos históricos automáticamente.

## Preparación

1. Trabajar primero con una copia autorizada y aislada de MySQL y de los documentos. Usar una identidad de base de datos propia, correo de pruebas y un directorio de archivos distinto de producción. Conservar un respaldo cifrado y probar su restauración.
2. Instalar las dependencias con `composer install` y `npm ci`. Comprobar requisitos con `composer check-platform-reqs` y generar los assets con `npm run build`. Ejecutar la suite con `php vendor/bin/phpunit` usando una clave de aplicación generada para pruebas. La suite usa SQLite en memoria y aplica las migraciones mediante `migrate`. Preparar también un ensayo separado en MySQL con los mismos permisos y versión que el entorno de destino.
3. Consultar `php artisan migrate:status` en la copia. Aplicar las migraciones pendientes con `php artisan migrate --force` en la copia y volver a revisar el estado. La migración nueva `2026_11_18_000001_track_vehicle_inventory_and_request_keys` crea `solicitudes_inventario` y añade un vínculo nullable para las unidades de refacciones. Las migraciones anteriores pendientes pueden incluir el backfill histórico de existencias de julio. Estimar tiempo, espacio y bloqueos antes de aprobar su ejecución en el destino.
4. Confirmar qué cuentas deben gestionar compras. La autorización usa la bodega principal elegida por el sistema y permite al administrador o al encargado de esa bodega. Las facturas existentes conservan su asociación histórica de proveedor y número. Revisar la configuración si hay más de una bodega marcada como principal.

## Documentos y exposición histórica

- Dar al proceso PHP acceso a `storage/app/private/documentos`. Las rutas autorizadas siguen leyendo archivos históricos en `storage/app/public`. Si se copian al nuevo disco, conservar las rutas relativas, verificar los hashes y comprobar las descargas con los roles correspondientes. Las nuevas versiones de evidencias se guardan como archivos distintos y conservan el historial.
- Bloquear la entrega estática de los prefijos históricos `/storage/compras`, `/storage/traslados`, `/storage/asignaciones` y `/storage/vehiculos` en el servidor y en la CDN, si existen. La regla incluida cubre Apache cuando se aplica `public/.htaccess`. Para Nginx, incluir el bloqueo en el bloque del sitio antes de habilitar la nueva versión.

  ```nginx
  location ~* ^/storage/(compras|traslados|asignaciones|vehiculos)(/|$) {
      return 403;
  }
  ```

  Verificar con una ruta de prueba propia que la entrega estática devuelva 403 o 404 y que la ruta autorizada permita la descarga. Invalidar cualquier copia de esos documentos en cachés públicas.
- Los ZIP se retiraron de la rama. Las revisiones anteriores siguen siendo accesibles hasta completar el tratamiento del incidente. Identificar el entorno que usó la clave publicada, planificar su rotación y comprobar si hay valores cifrados que requieran un procedimiento de transición. Coordinar con el propietario la eliminación de referencias históricas y la revisión de copias, forks y cachés. Evitar publicar respaldos o claves nuevos.
- Revisar si se usó el seeder anterior. Rotar esas credenciales mediante el procedimiento de identidades y revocar las sesiones afectadas. El seeder nuevo solo se usa para inicializar un entorno vacío y lee `ADMIN_BOOTSTRAP_EMAIL` y `ADMIN_BOOTSTRAP_PASSWORD` desde su configuración. Retirar esas variables tras inicializar la cuenta.

## Conciliación del inventario

1. Mantener `INVENTARIO_SEGUIMIENTO_UNIDADES=false` hasta completar la conciliación. En ese modo las operaciones usan saldos por condición con transacciones y no actualizan el inventario individual. El estado de devolución se registra en los movimientos. Las unidades perdidas no se reincorporan a stock. Si la conciliación ocurre después de desplegar, incluir los movimientos del período de transición.
2. En una copia consistente, ejecutar `php artisan inventario:contrastar-saldos`. El informe contrasta resumen por bodega, producto y condición con las unidades, y compara las asignaciones activas de colaboradores y refacciones con sus períodos o unidades. Devuelve un estado distinto de cero cuando encuentra diferencias. La igualdad de cantidades no verifica la identidad física ni la condición de cada unidad.
3. Comparar las diferencias con recuentos físicos, recibos, traslados y actas. Registrar diferencias antes y después, evidencia, responsable y aprobación. Crear o vincular unidades históricas solo con evidencia suficiente. Mantener como desconocidos la vida útil y el período cuando falte información. No rellenar automáticamente diferencias con cantidades supuestas.
4. Durante una pausa de escrituras, repetir el contraste sobre una copia final consistente. Activar `INVENTARIO_SEGUIMIENTO_UNIDADES=true` solo tras aprobar la conciliación, verificar que no hay diferencias y repetir los flujos con datos representativos. En modo por unidad, la aplicación rechaza movimientos con saldos o vínculos incoherentes y permite investigar el caso sin cambiar su saldo.
5. Para clientes externos, usar un UUID estable en `solicitud_id` para reintentos de una misma asignación, devolución, solicitud de traslado o cierre de refacción. Los formularios actualizados ya lo incluyen. Las facturas usan como clave el proveedor y número. La aprobación y el rechazo bloquean el mismo traslado pendiente.

## Paso a producción y reversión

1. En una ventana aprobada, pausar las escrituras, respaldar MySQL y archivos y aplicar únicamente las migraciones pendientes verificadas en el ensayo. Desplegar el artefacto construido, actualizar la configuración aprobada y regenerar las cachés de Laravel según el procedimiento del entorno.
2. Antes de habilitar escrituras, verificar paneles por rol, navegación de compras, acceso a bodegas, descarga por rutas autorizadas y generación de hojas, PDFs y Excel. Confirmar stock y movimientos de una prueba controlada en el entorno de ensayo antes de repetirla en un destino autorizado.
3. Si se revierte el código, mantener las tablas y columnas aditivas y el bloqueo de URLs públicas. Preparar una versión compatible con ambos discos, ya que el código anterior no lee los nuevos archivos privados. Si un movimiento quedó confirmado, conciliarlo desde el registro operativo, en lugar de reiniciar inventarios o recuperar una copia antigua sobre datos posteriores.
