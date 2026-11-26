# Procedimiento IT

## Obtener una instantánea y emitir plantillas

Usar Python 3.10 o posterior y MySQL 8. El kit se ejecuta fuera de Edson-REP y trabaja con archivos. La cuenta para la consulta debe tener acceso de lectura a las columnas declaradas y visibilidad suficiente de las restricciones. Coordine con el DBA la comprobación de disparadores. Configure el acceso de solo lectura mediante `mysql_config_editor` u otro método corporativo. Guarde las salidas en una carpeta restringida. Contienen datos del personal y no solicitan ni exportan contraseñas, hashes o documentos.

Desde el directorio `carga_masiva`:

```sh
python -m pip install -r herramientas_it/requirements.txt
mysql --login-path=edson_lectura --database=edson --default-character-set=utf8mb4 \
  --batch --raw --skip-column-names < herramientas_it/consultar_entorno.sql > instantanea.jsonl
python herramientas_it/plantillas.py --instantanea instantanea.jsonl --salida distribucion
```

La consulta trabaja en una transacción de solo lectura. La salida debe conservar un objeto JSON por línea, en UTF‑8. La edición de base ya incluye estados, condiciones, tipos y etiquetas reales de permisos. La edición de entorno agrega nombres de regiones, roles activos y bodegas, códigos de productos y categorías efectivas. Los catálogos dependientes de MySQL no se rellenan con ejemplos. La herramienta acepta referencias a catálogos nuevos incluidos en el paquete y reporta las ambiguas. Emita solo las plantillas del departamento correspondiente.

## Validar y convertir

```sh
python herramientas_it/carga.py validar --entrada recibidos \
  --instantanea instantanea.jsonl --salida revision_01

python herramientas_it/carga.py convertir --entrada recibidos \
  --instantanea instantanea.jsonl --salida conversion_01 --preparacion edson_preparacion
```

Cada ruta de salida debe ser nueva y estar fuera de la carpeta de entrada. Incluya únicamente los Excel completados. La herramienta emite `informe.csv` con gravedad, archivo, hoja, fila, columna, problema y corrección. `plan.json` conserva claves de negocio, estado, valores previos, propuesta, referencias y destino. `resumen.json` incluye totales e identidad del entorno. Revise ambos aunque el CSV solo muestre avisos. Las filas **Verificar** y los registros ya iguales aparecen en el plan sin operaciones de escritura. Los errores críticos devuelven código 2 y bloquean la generación. Los avisos requieren revisión humana previa a ejecutar.

El modo predeterminado genera **solo preparación**. Tras aprobar las filas de catálogo y validar una instantánea actualizada, agregue `--aplicar-catalogos` con otra salida nueva para habilitar las escrituras acotadas de regiones, bodegas regionales, productos y colaboradores elegibles. Las celdas opcionales en blanco conservan el valor existente. Los cambios de clave están fuera del flujo para proteger relaciones y valores históricos.

## Orden de trabajo y ejecución

1. **Asegurar respaldo y acceso.** Identifique el entorno y su servidor. Respalde con el procedimiento corporativo los datos, esquema, rutinas y archivos privados si la operación los involucra. Verifique la restauración en una copia aislada. Conserve evidencia, instante del corte y responsable. Acuerde una ventana sin escrituras operativas para el SQL de catálogos. Mantenga la preparación en una base dedicada del mismo servidor, creada previamente por el DBA, con acceso limitado. Use la misma base de preparación para comparar referencias de cargas sucesivas.
2. **Catálogos y personas.** Ejecute primero regiones, bodegas y productos, que se usan para resolver referencias. Valide de nuevo luego de crear una región si algún colaborador dependía de ella. Registre colaboradores con un código laboral estable. El SQL limitado escribe por orden regiones → bodegas → productos → colaboradores.
3. **Cuentas y relaciones.** Cree y active los usuarios en Seguridad mediante el procedimiento de credenciales de IT. Luego aplique bodega de perfil, supervisión, regiones, coordinación y bodegas adicionales. Revise dependencias antes de permitir acciones o delegar. Conserve las relaciones previas que no fueron parte del envío. La preparación aporta correos y nombres y resuelve claves internas conocidas. Registros por crear primero llevan la referencia natural hasta que se asigne su clave interna.
4. **Conciliar existencias y custodia.** Acordar una fecha de corte, cuadrar conteo por bodega, producto y condición e identificar las unidades que ya están bajo responsabilidad ajena. Comparar conteo contra saldo, lotes y unidades. En una recepción nueva y real, use Compras o la entrada autorizada según su naturaleza. Las regularizaciones de usado, dañado, baja o diferencias requieren aprobación y procedimiento específico con trazabilidad. El saldo físico se carga una sola vez.
5. **Custodia histórica y facturas.** Vincule facturas y actas a registros existentes cuando se identifiquen. Para una custodia no representada, revise si ya se contabilizó la salida y si hay fecha, cantidad, procedencia y evidencia verificables. Documente cómo reconstruir cabecera, asignación, períodos si aplica y movimiento histórico con el modo de inventario del entorno. Ensaye el procedimiento con balances comparables. Para fechas desconocidas conserve el registro en preparación. Mantenga facturas antiguas ya reflejadas en el saldo como evidencia. Si encuentra una recepción real aún pendiente, autorice y ejecute ese flujo por separado. Una compra archivada y su entrada representan hechos distintos.
6. **Importación revisada.** Abra la base de preparación en phpMyAdmin e importe el `carga_revisable.sql` aprobado. El archivo usa rutinas y delimitadores MySQL, tablas InnoDB, un bloqueo de carga y una transacción para el lote. La cuenta ejecutora necesita crear y ejecutar rutinas y crear tablas en preparación, y lectura de origen. Solo en el modo aprobado necesita insertar y actualizar en los catálogos del origen. Verifique la compatibilidad de la versión de phpMyAdmin con la importación de rutinas. El cliente MySQL puede ejecutar el mismo archivo en pruebas. La herramienta Python jamás ejecuta el archivo.
7. **Verificar resultados.** Reextraiga una instantánea y compare entidades y referencias contra el plan aprobado. Consulte los tres registros de preparación con las consultas auxiliares. Compruebe que inventarios, movimientos, custodias, compras, evidencias y avisos no cambiaron por el lote. Después de un procedimiento de inventario por separado, concilie por condición, saldo, unidades, lotes y movimientos y valide expedientes y documentos. Revise resultados con Bodega y RR. HH. antes de liberar la ventana.

## Controles de repetición y de concurrencia

La misma carga se identifica por una huella del origen y del plan. Reimportarla no repite el lote. Una referencia histórica con el mismo contenido solo se prepara una vez incluso en otro lote. Una referencia ya preparada con datos distintos detiene la carga, incluido cualquier cambio de catálogo del mismo lote. IT debe conciliar y documentar la corrección antes de volver a proponerla. El folio debe ser estable.

En catálogo, el SQL compara el registro previo con el estado observado durante la revisión y verifica la región de referencia. Un alta en conflicto, un campo personalizado obligatorio nuevo o una edición intermedia detienen la transacción. El SQL comprueba servidor y migraciones. Use una instantánea reciente y una ventana sin escrituras porque el bloqueo propio del kit no sustituye la coordinación con la aplicación. Revise en pruebas cualquier extensión local del esquema, incluidos disparadores y cambios que no queden registrados como migraciones.

Si falla la transacción, se revierten las filas de preparación y escrituras de catálogo. Las estructuras de preparación pueden existir, pues su creación es previa a la transacción. Guarde el mensaje, obtenga otra instantánea y prepare un lote nuevo. Si la verificación posterior detecta una diferencia después de un lote confirmado, siga el procedimiento de restauración o compensación aprobado por IT. No vuelva a ejecutar una entrada real para corregir una discrepancia histórica.
