# Asignaciones, custodia y revisión de incidencias

## Asignar y documentar una salida

1. Abre **Nueva asignación**, elige al colaborador y selecciona el producto. El sistema registra una unidad por código. Puedes incluir varios códigos en la misma hoja. Si operas en una bodega asignada, el formulario la selecciona.
2. Guarda la asignación. La operación habitual registra la entrega directa al colaborador con la fecha y el usuario de la salida. Si otra persona transporta los productos, selecciona **Salida por medio de un tercero**. Su nombre es opcional y esa salida queda registrada con la recepción sin acreditar. El registro posterior de la recepción es opcional en el detalle.
3. Abre la hoja de asignación desde el detalle. Carga el PDF firmado cuando esté disponible. Cada PDF queda como una versión privada del grupo. La fotografía es opcional y el estado documental se consulta por separado en **Hojas firmadas faltantes**.

Si ya existe la custodia ordinaria del mismo código, el sistema vincula la entrega nueva con el historial. La anterior se identifica como **Custodia anterior sin retorno confirmado** y conserva su saldo hasta registrarse el hecho correspondiente. Solo la nueva unidad queda como custodia ordinaria. Cada salida válida consume una unidad de su bodega.

## Registrar lo que ocurrió con la unidad anterior

En **Mis asignaciones**, abre al colaborador y selecciona uno o varios productos. Registra la cantidad, la condición y, si se conoce, la fecha del hecho. La operación guarda al responsable y la bodega de la asignación de origen.

- **Buen estado.** La unidad vuelve a la bodega como inventario usado, con su vida restante acreditada.
- **Daño.** Se registra la entrada a inventario dañado, fuera del saldo asignable.
- **Pérdida o robo.** Se cierra la custodia y queda una incidencia para RR. HH. sin entrada a inventario. Otra incidencia requiere explicación y efecto de inventario.
- **Renuncia y despido.** Selecciona el motivo correspondiente para generar la hoja de los retornos físicos. Los retornos ordinarios quedan registrados en la bitácora. Las hojas y documentos anteriores mantienen su acceso histórico.

## Revisar una incidencia en RR. HH.

Una salida del mismo código con la anterior aún bajo custodia genera revisión por **posible reemplazo anticipado** cuando queda vida útil acreditada. Si faltan datos, aparece **Reemplazo por verificar**. Un producto vencido sigue bajo custodia y su nueva entrega no genera alerta automática por anticipación. El buen estado tampoco genera una alerta de devolución. El daño con vida vigente o desconocida, la pérdida y el robo se envían a revisión.

En **Incidencias para revisión de RR. HH.**, consulta las dos fechas, la custodia, los documentos y los movimientos del código. Guarda una nota, solicita regularización a una persona con permisos operativos sobre la custodia o finaliza con una nota. El sistema registra el autor y la fecha de cada actuación. El expediente del colaborador muestra las entregas, las custodias sin resolver, los retornos, las incidencias y los respaldos dentro del acceso autorizado.

## Prioridad de inventario y vida útil

La salida normal selecciona usados en buen estado antes que nuevos. Entre usados, elige primero la menor vida restante conocida, incluido cero. Un usuario con el permiso **Entregar inventario nuevo por excepción** puede elegir nuevo al indicar el motivo. El permiso requiere una asignación expresa y se conserva la bodega autorizada.

Para inventario nuevo, PEPS utiliza la fecha real de ingreso, conservada durante los traslados. Registra esa fecha en compras si documentas una recepción anterior. Si omites el campo, la compra registra el ingreso con la fecha y hora de la operación. Para saldos históricos sin una fecha verificable se aplica un orden estable antes de los ingresos fechados. La fecha queda desconocida.

El seguimiento individual mantiene los períodos por unidad cuando está habilitado. En modo agregado, el saldo nuevo o usado se separa en lotes con sus datos acreditados, de forma transparente para el almacenista. El saldo histórico sin detalle se incorpora a un lote al operar por primera vez con él, con los datos que el saldo histórico permite acreditar. Los lotes no identifican unidades físicas de forma individual. Si faltan fechas de uso acreditadas, la vida restante es desconocida. La vida restante acreditada permanece constante durante el almacenaje y sigue consumiéndose desde la recepción acreditada de la reasignación.

Usa `php artisan inventario:contrastar-saldos` para conciliar existencias por unidad y `php artisan inventario:contrastar-saldos --lotes` para contrastar saldos materializados por lote. Habilitar el seguimiento individual sobre otro entorno requiere conciliar primero su stock, lotes y períodos históricos con las unidades físicas.

## Migración y recordatorios

La migración `2026_11_20_000001_add_operational_assignment_tracking.php` agrega los lotes y los campos de fecha de ingreso, vínculo de custodia, motivo de devolución y seguimiento de RR. HH. Conserva los registros y archivos existentes. El esquema conserva la evidencia de operaciones registradas al revertir la aplicación.

En un despliegue autorizado, ejecuta `php artisan migrate --force` y comprueba la tarea con `php artisan schedule:list`. El cron debe ejecutar Laravel cada minuto.

```cron
* * * * * cd /ruta/edson-rep && /ruta/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

La tarea `php artisan inventario:recordar-entregas` se ejecuta cada 15 minutos y envía un aviso por grupo y destinatario autorizado cuando hayan transcurrido 24 horas y falte el PDF firmado. Los avisos respetan la configuración y la supervisión asignada. El comando de ejecución directa registra notificaciones en el entorno activo.

## Cómo validar en un entorno de prueba

1. Registra dos entradas del mismo código con fechas de ingreso distintas y trasládalas a una bodega secundaria. Guarda una salida y verifica que salga la entrada más antigua. Devuélvela en buen estado y confirma que la siguiente salida prefiera el usado.
2. Guarda una segunda entrega al mismo colaborador. Comprueba que el almacén descontó solo la unidad nueva, que la anterior conserva su custodia y que RR. HH. recibe la revisión que corresponde. Pide la regularización y registra el hecho con el usuario operativo.
3. Registra un buen retorno ordinario, un daño, una pérdida, un robo y un retorno por renuncia. Contrasta los saldos y comprueba que la hoja de devolución se genera para la renuncia.
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
