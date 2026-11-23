# Inspecciones, consolidados, compras y asignaciones masivas

El supervisor registra un requerimiento. Bodega central reúne las inspecciones de un equipo y envía un consolidado. El coordinador revisa los ajustes y aprueba el conjunto. Bodega central calcula los faltantes, registra la constancia externa de Gerencia y distribuye las compras por factura. Las asignaciones se registran cuando ocurre la salida real.

## Configuración inicial

1. En **Catálogos operativos → Regiones**, crea las regiones activas. En **Colaboradores**, asigna una región y verifica el puesto de cada persona. El formulario de inspección filtra por región y toma el puesto del registro seleccionado.
2. En **Usuarios**, da de alta a cada coordinador con el ámbito **Coordinador**. Edita cada cuenta de supervisor para asignarle su coordinador. La relación con el almacenista se conserva de forma independiente.
3. En **Catálogos operativos → Artículos del formulario**, vincula los artículos con códigos reales del catálogo, ajusta sus máximos y ordénalos. Puedes cambiar nombres y orden de secciones. Si falta un producto, usa **Dar de alta un producto**. El alta crea el código y las existencias se registran cuando se recibe el inventario.
4. **Conos y reflectivos** incluye cuatro artículos iniciales con cantidades de 0 a 7. La migración vincula automáticamente una coincidencia inequívoca por nombre. Si falta el código, activa el artículo después de asociarlo. Carga desde el catálogo real los artículos de las demás secciones.
5. En **Motivos de asignación**, administra las etiquetas y las opciones vigentes. Desgaste solo registra el motivo. Robo abre seguimiento sin importe. Robo injustificado abre seguimiento con una referencia proporcional cuando se conocen el costo histórico y la vida restante de la custodia anterior. RR. HH. registra su decisión en el seguimiento.

Los permisos operativos aplican junto con las excepciones individuales y la bodega asignada. Un operador de bodega secundaria conserva sus asignaciones manuales y traslados. El ámbito de Coordinador solo consulta inspecciones y consolidados de su equipo. Administración puede consultar, configurar y reabrir según sus permisos.

## Inspección y consolidación

- El supervisor selecciona fecha, región y colaborador, registra las cantidades solicitadas y puede adjuntar hasta ocho fotos privadas. Cero significa que el artículo no se solicita. El supervisor y el puesto provienen de registros autorizados.
- Bodega central filtra las inspecciones por fechas, supervisor, coordinador o región. Selecciona requerimientos del mismo equipo y crea un borrador con nombre y rango de fechas. Cada inspección se vincula una vez para evitar contarla dos veces.
- Tras el envío, el coordinador puede iniciar la revisión, ajustar cantidades, agregar códigos del catálogo o devolver el consolidado. El sistema conserva las líneas originales y registra cantidades anteriores y nuevas, motivo, fecha y usuario. **Aprobar consolidado** aplica el conjunto y bloquea las ediciones posteriores.
- La devolución habilita correcciones y reenvío. Bodega central puede retirar un envío para corregirlo. Administración puede reabrir una aprobación con motivo mientras el consolidado no tenga constancias o compras vinculadas.

## Necesidades de compra

1. En **Necesidades de compra**, selecciona los consolidados que quieres seguir. Cada producto muestra el requerimiento aprobado, el stock considerado al guardar el plan, la compra total decidida, lo comprado y recibido y el saldo central actual.
2. En **Plan y constancia de autorización externa**, distribuye las unidades de stock a considerar y la compra total decidida por producto y consolidado. La sugerencia descuenta las unidades ya registradas y usa una sola vez el saldo disponible entre los consolidados seleccionados. Registra el correo o mensaje de WhatsApp, la fecha, quién autorizó y la referencia. Puedes adjuntar evidencia privada. Cada registro conserva su versión del plan y el stock es una referencia de planificación.
3. Desde **Registrar nueva compra** o **Compras**, registra la factura, sus productos y los PDF. Selecciona los consolidados y distribuye las unidades de cada línea. La suma distribuida se limita a la cantidad facturada y cada destino se limita a su pendiente. El resto de la factura ingresa como stock general. Cada factura sigue disponible en la consulta por número y proveedor. Puedes agregar facturas de diferentes fechas y proveedores al mismo consolidado.
4. El pendiente de compra es la cantidad decidida menos la recibida. Si falta un plan desglosado, se usa la cantidad aprobada como referencia. Puedes cerrar un consolidado cuando las facturas vinculadas estén conciliadas y la cantidad recibida más el stock considerado cubran toda la aprobación. Los cerrados permanecen disponibles en el seguimiento.
5. Las facturas históricas sin cantidades distribuidas conservan sus vinculaciones y aparecen como pendientes de conciliación. Desde el detalle de la factura, registra el desglose respaldado por sus documentos y confirma las unidades que quedan sin atribuir. Completa esa conciliación antes de guardar un plan o vincular nuevas facturas al consolidado. Las constancias antiguas conservan sus cantidades originales y puedes registrar un plan desglosado a partir de la documentación.

La entrada de inventario se aplica al registrar la factura. La planificación, la consulta y la conciliación dejan intactas las existencias.

## Importación desde Excel

Descarga la plantilla de esta carpeta o desde **Bodega central → Importar asignaciones desde Excel**. La descarga en la aplicación muestra los motivos activos y el menú desplegable. La hoja **Ejemplo** tiene códigos ficticios. Captura tus datos en **Asignaciones**.

| Campo | Cómo completarlo |
| --- | --- |
| Código de colaborador | Usa el código de una persona activa. |
| Código de producto | Usa el código exacto que figura en el inventario. |
| Cantidad | Registra una unidad por colaborador y código. |
| Fecha de asignación | Usa AAAA-MM-DD o una fecha de Excel. Usa la misma fecha en las líneas de un colaborador. |
| Aprobado por, medio y observaciones | El medio puede ser Correo o WhatsApp. Respeta los campos obligatorios de la configuración. |
| Código de motivo | Selecciona el código de un motivo activo o deja la celda vacía. |

La vista previa admite hasta 500 filas y presenta los errores por fila. Al confirmar se verifican nuevamente la bodega, los códigos, las custodias y el inventario. Cualquier error revierte todo el lote. El mismo contenido normalizado se registra una sola vez incluso si se cambia el orden de filas o lo confirma otra cuenta. Cada colaborador obtiene una sola hoja con sus productos.

Abre cada hoja para imprimirla o guardarla como PDF desde el navegador. También puedes descargar todas juntas en un ZIP de hojas HTML imprimibles. Conserva junto a ellas el archivo de logotipo del ZIP. Los PDFs firmados y las fotos se adjuntan después desde el detalle de cada entrega. Los campos personalizados obligatorios que se usan en la importación requieren un valor predeterminado.

## Migración y validación

La migración `2026_11_20_000002_create_flujo_operativo.php` agrega regiones, coordinación, inspecciones y fotografías, consolidados e historial, constancias, vínculos de compras, motivos e importaciones. Comprueba si falta `asignaciones_inventarios.sustituida_por_id` y la agrega cuando corresponde. Conserva los valores históricos existentes y los archivos. Para una instalación autorizada, el comando es `php artisan migrate --force`.

La migración aditiva `2026_11_23_000001_track_consolidated_purchases.php` agrega el historial de planes por línea, la distribución por línea de factura y la fecha de confirmación. Las vinculaciones previas quedan disponibles para conciliación.

Prueba en un entorno aislado.

1. Da de alta dos coordinadores, un supervisor y colaboradores de dos regiones. Busca y registra una inspección con foto. Verifica las restricciones desde el otro equipo.
2. Consolida dos inspecciones, modifica una cantidad con nota, aprueba y comprueba los valores originales, las diferencias y el historial.
3. Revisa existencias y faltantes. Guarda la constancia y verifica que no cambie el saldo. Registra una compra vinculada, repite la factura y comprueba que el movimiento se registró una sola vez.
4. Importa un Excel inválido y otro válido. Comprueba la vista previa, el descuento en central, las hojas, el ZIP y el reintento. Registra una asignación manual y un traslado desde las rutas habituales.
5. Prueba los tres motivos y consulta el caso de robo injustificado como RR. HH. La referencia proporcional queda como dato de revisión.

La prueba concurrente `php tests/Integration/flujo_mysql.php` usa MySQL local y una base desechable `edson_*_qa` con `APP_ENV=testing`. Registra dos previsualizaciones equivalentes con usuarios distintos e intenta confirmar ambas en paralelo.

La prueba `php tests/Integration/compras_consolidadas_mysql.php` usa el mismo entorno local desechable. Envía dos facturas en paralelo para el mismo pendiente y repite una factura de manera concurrente. Comprueba las distribuciones, los movimientos y el saldo.
