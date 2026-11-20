# Entregas de inventario y revisión de incidencias

## Operación

- **Salida.** El almacenista entrega desde su bodega. El administrador puede operar en las bodegas de su ámbito. Por cada código solo se puede registrar una unidad activa para un colaborador. Los permisos de consulta, asignación, devolución, evidencia y descarga conservan sus reglas individuales y de rol.
- **Custodia anterior.** Si el colaborador tiene el mismo código activo, resuelve todas las filas y cantidades históricas de ese código en el mismo formulario. Si alguna está fuera de tu alcance, una persona con permisos sobre esa custodia debe resolverla. Si falta stock para la nueva salida, se revierte toda la operación.
- **Recepción.** El detalle distingue modalidad directa o por tercero, momento de salida, fecha real de recepción cuando se conoce y confirmación con fecha, usuario y medio. Se puede registrar una confirmación sin fecha real y completar esa fecha después.
- **Documentación.** Carga el PDF firmado desde el detalle del grupo. Cada nuevo PDF queda como una versión. La fotografía es opcional. En los listados, los filtros **Recepciones sin confirmar** y **Hojas firmadas faltantes** muestran los dos seguimientos.
- **Devolución e incidencias.** En **Mis asignaciones**, abre el colaborador y marca uno o varios productos. Indica cantidad y condición para cada uno. El retorno reutilizable entra como usado. El dañado queda en su condición. La pérdida, el robo y las incidencias sin retorno se cierran con trazabilidad. La opción **Otra incidencia** requiere explicación y efecto de inventario.
- **Vida útil.** Empieza desde la recepción real para las entregas nuevas. El vencimiento mantiene la custodia activa. Con seguimiento por unidad, el sistema conserva la vida de cada unidad reutilizable. Con stock agregado, se traslada una vida restante cuando se conoce para todas las unidades del saldo y coincide. En un saldo mezclado, se muestra como desconocida. El sistema conserva los datos disponibles en las asignaciones históricas.
- **RR. HH.** La pantalla **Incidencias para revisión de RR. HH.** muestra daños con vida vigente o desconocida, pérdidas y robos. Un daño con vida vencida queda en inventario como incidencia. Una persona con permiso de modificación puede añadir notas y finalizar la revisión. Se conserva autor, fecha y nota de cada actuación.

## Migración y seguimiento programado

La migración `2026_11_19_000001_create_entregas_and_incidencias.php` agrega las tablas de entregas, incidencias, actuaciones y recordatorios. Extiende el estado de custodia y añade campos de fecha, vida y grupo documental. Las asignaciones y archivos históricos conservan sus vínculos. Las tablas de seguimiento se conservan al revertir el código, por lo que el método `down` detiene una reversión que borraría operaciones registradas.

En el entorno de destino, con la aprobación de despliegue correspondiente, ejecuta `php artisan migrate --force`. El usuario de ejecución de PHP requiere acceso a la base, al almacenamiento privado `documentos` y al mecanismo de caché configurado. Comprueba el registro de la tarea con `php artisan schedule:list`.

Configura el cron del usuario de la aplicación para ejecutar Laravel una vez por minuto. Usa las rutas de la instalación.

```cron
* * * * * cd /ruta/edson-rep && /ruta/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

La tarea corre cada 15 minutos. Una entrega entra al seguimiento al cumplir 24 horas desde la salida. Cada destinatario autorizado recibe como máximo un aviso por grupo, con los motivos vigentes al momento del envío. El sistema aplica la configuración **Seguimiento de recepción y hoja firmada** de notificaciones y la relación entre almacenista y supervisor. Los pendientes siguen visibles hasta resolverlos. Para ejecutar la comprobación en pruebas, usa `php artisan inventario:recordar-entregas`. Ese comando escribe registros de notificación en la base del entorno activo.

## Comprobación manual en un entorno de pruebas

1. Registra una compra con varias unidades de un código. Traslada unidades a dos bodegas y acepta los traslados. Comprueba sus saldos.
2. Como almacenista, registra una entrega directa, sin documentos, de dos **códigos diferentes**. Comprueba la salida de una unidad de cada código, la custodia activa y los estados separados de recepción y hoja.
3. Repite la misma solicitud. Intenta registrar de nuevo uno de esos códigos desde otra bodega. Comprueba que la segunda solicitud se bloquea y que los saldos se mantienen. Usa la cuenta autorizada para resolver la custodia anterior y emitir la unidad nueva desde el mismo formulario.
4. Repite el flujo con buen estado, daño, pérdida, robo y otra incidencia. Comprueba los saldos por condición, la hoja de devolución en retornos físicos y el detalle de la incidencia. Con un registro histórico de varias unidades, comprueba una devolución parcial y una resolución del saldo restante.
5. Registra una salida por tercero. Confirma luego con un medio, primero sin fecha real y después con la fecha real. Comprueba ambos registros y la vida útil. Prueba una fecha antes de la salida y una descarga con una cuenta sin acceso.
6. Carga dos PDFs firmados y, por separado, una foto. Descarga las dos versiones. Comprueba que la hoja cubre todo el grupo y que la falta de fotografía permite documentarlo.
7. Registra una salida de prueba hace más de 24 horas. Ejecuta la tarea dos veces. Comprueba al menos un caso con ambos motivos y que cada usuario autorizado recibe un solo aviso. Confirma o carga el PDF y verifica el filtro correspondiente.
8. Registra daños con vida vigente, vencida y desconocida. Comprueba las incidencias y alertas. Entra como RR. HH., revisa el respaldo, guarda una nota, finaliza y exporta el Excel. Comprueba también el expediente del colaborador y el acceso con un rol personalizado.

## Pruebas automatizadas

```sh
npm run build
php artisan view:cache
php vendor/bin/phpunit --do-not-cache-result
```

Para la prueba concurrente, usa una base MySQL local y desechable de nombre `edson_*_qa` con `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_URL=`, `DB_HOST=127.0.0.1`, credenciales de prueba configuradas y una clave de aplicación de prueba. Ejecuta `php artisan migrate` en esa base y luego `php tests/Integration/entregas_mysql.php`. El script crea datos sintéticos, ejecuta dos procesos contra la ruta de asignaciones y verifica la exclusión entre bodegas, el reintento simultáneo de un reemplazo y los saldos resultantes.
