# Asignaciones, custodia y revisión de incidencias

## Asignar y documentar una salida

1. Abre **Nueva asignación**, elige al colaborador y selecciona el producto. El sistema registra una unidad por código. Puedes incluir varios códigos en la misma hoja. Si operas en una bodega asignada, el formulario la selecciona.
2. Guarda la asignación. La operación habitual registra la entrega directa al colaborador con la fecha y el usuario de la salida. Si otra persona transporta los productos, selecciona **Salida por medio de un tercero**. Su nombre es opcional y esa salida queda registrada con la recepción sin acreditar. El registro posterior de la recepción es opcional en el detalle.
3. Abre la hoja de asignación desde el detalle. Carga el PDF firmado cuando esté disponible. Cada PDF queda como una versión privada del grupo. La fotografía es opcional y el estado documental se consulta por separado en **Hojas firmadas faltantes**.

## Reemplazar un producto

En la nueva asignación, selecciona la custodia anterior del mismo código. Si la vida útil sigue vigente o se desconoce, registra un motivo activo del catálogo. Una primera asignación no genera revisión. Si la vida útil terminó, un reemplazo normal puede registrarse sin motivo adicional ni alerta.

Cuando tengas permiso de devolución sobre el artículo anterior, puedes registrar en la misma asignación si se recuperó y en qué condición. Una unidad recibida en buen estado entra como inventario usado. Las dañadas o recibidas para baja quedan fuera del saldo asignable. La pérdida, el robo o un cierre sin retorno físico conservan la incidencia y el responsable sin generar entradas. Si todavía no se verificó el retorno, el sistema conserva la custodia anterior y su saldo con el vínculo al reemplazo.

## Registrar una devolución completa

En el expediente del colaborador, abre **Registrar devolución**. El permiso y el alcance deben cubrir todas las bodegas y custodias incluidas. Clasifica las cantidades de cada artículo como recibidas en buen estado, dañadas o no devueltas. Al confirmar, el sistema cierra toda la custodia y genera el PDF. El expediente y **Historial de movimientos** conservan el acceso autorizado al documento y a sus versiones firmadas.

La inactivación es una acción administrativa independiente. La devolución conserva el estado laboral y registra al responsable, las condiciones y los movimientos con sus bodegas de origen.

## Consultar y revisar incidencias

En **Incidencias de RR. HH.**, se conserva la revisión según el motivo configurado. Puede limitarse al registro del motivo, solicitar revisión o incluir una referencia proporcional con datos disponibles. La referencia no aplica descuentos automáticamente. La devolución completa conserva las incidencias y el seguimiento según la condición registrada.

El expediente separa **Resumen**, **Productos asignados** e **Incidencias**. Desde Productos asignados se abre el historial paginado. Conserva la descarga de la ficha Excel y el acceso a respaldos según permisos.

## Prioridad de inventario y vida útil

La salida normal selecciona usados en buen estado antes que nuevos. Entre usados, elige primero la menor vida restante conocida, incluido cero. Un usuario con el permiso **Entregar inventario nuevo por excepción** puede elegir nuevo al indicar el motivo. El permiso requiere una asignación expresa y se conserva la bodega autorizada.

Para inventario nuevo, PEPS utiliza la fecha real de ingreso, conservada durante los traslados. Registra esa fecha en compras si documentas una recepción anterior. Si omites el campo, la compra registra el ingreso con la fecha y hora de la operación. Para saldos históricos sin una fecha verificable se aplica un orden estable antes de los ingresos fechados. La fecha queda desconocida.

El seguimiento individual mantiene los períodos por unidad cuando está habilitado. En modo agregado, el saldo nuevo o usado se separa en lotes con sus datos acreditados, de forma transparente para el almacenista. El saldo histórico sin detalle se incorpora a un lote al operar por primera vez con él, con los datos que el saldo histórico permite acreditar. Los lotes no identifican unidades físicas de forma individual. Si faltan fechas de uso acreditadas, la vida restante es desconocida. La vida restante acreditada permanece constante durante el almacenaje y sigue consumiéndose desde la recepción acreditada de la reasignación.

Usa `php artisan inventario:contrastar-saldos` para conciliar existencias por unidad y `php artisan inventario:contrastar-saldos --lotes` para contrastar saldos materializados por lote. Habilitar el seguimiento individual sobre otro entorno requiere conciliar primero su stock, lotes y períodos históricos con las unidades físicas.

## Migración y recordatorios

La devolución completa utiliza el esquema existente. El documento generado y las versiones firmadas se guardan en el disco privado de documentos.

En un despliegue autorizado, ejecuta `php artisan migrate --force` y comprueba la tarea con `php artisan schedule:list`. El cron debe ejecutar Laravel cada minuto.

```cron
* * * * * cd /ruta/edson-rep && /ruta/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

La tarea `php artisan inventario:recordar-entregas` se ejecuta cada 15 minutos y envía un aviso por grupo y destinatario autorizado cuando hayan transcurrido 24 horas y falte el PDF firmado. Los avisos respetan la configuración y la supervisión asignada. El comando de ejecución directa registra notificaciones en el entorno activo.

## Cómo validar en un entorno de prueba

1. Registra dos entradas del mismo código con fechas de ingreso distintas y trasládalas a una bodega secundaria. Guarda una salida y verifica que salga la entrada más antigua. Registra un reemplazo con el retorno confirmado en buen estado y comprueba que la próxima salida prefiera el usado.
2. Guarda una segunda entrega al mismo colaborador. Comprueba que el almacén descontó solo la unidad nueva, que la anterior conserva su custodia y que RR. HH. recibe la revisión que corresponde. Comprueba que una condición registrada durante el reemplazo use los permisos operativos y quede vinculada a la alerta correspondiente.
3. Clasifica todos los artículos de un colaborador, con retornos físicos y faltantes. Contrasta los saldos y verifica que la hoja distinga los resultados.
4. Registra una salida por tercero. Carga dos versiones del PDF desde el detalle. Ejecuta dos veces la tarea sobre casos con más de 24 horas y comprueba que solo exista un aviso donde falte la hoja.
5. Revisa las entregas, incidencias y respaldos en el expediente. Usa un rol personalizado con reglas individuales para verificar la excepción de inventario nuevo y los límites de bodega.

## Pruebas automatizadas

```sh
npm run build
php artisan view:cache
php vendor/bin/phpunit --do-not-cache-result
```

Para la prueba concurrente usa una base MySQL local de pruebas con nombre `edson_*_qa`, `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_URL=` y `DB_HOST=127.0.0.1`. Configura una clave de aplicación y credenciales de prueba, migra la base y ejecuta ambas variantes.

```sh
INVENTARIO_SEGUIMIENTO_UNIDADES=true php tests/Integration/entregas_mysql.php
INVENTARIO_SEGUIMIENTO_UNIDADES=false php tests/Integration/entregas_mysql.php
```
