# Inspecciones, consolidados, compras y asignaciones masivas

El supervisor registra los requerimientos de cada colaborador. Bodega central agrupa las inspecciones y envía el consolidado al responsable de las regiones seleccionadas. Coordinación revisa las necesidades por persona. Bodega central usa los totales aprobados para planificar compras y vincula las facturas y entregas que correspondan.

## Configuración inicial

1. En **Catálogos operativos → Regiones**, crea las regiones activas. En **Colaboradores**, asigna una región y verifica el puesto de cada persona. El formulario de inspección filtra por región y toma el puesto del registro seleccionado.
2. En **Usuarios**, abre el perfil de Coordinador y selecciona **Administrar regiones**. Un coordinador puede atender varias regiones. Cada región activa puede tener un responsable. Confirma los cambios de responsable en el formulario. El historial muestra el cambio, el usuario que lo realizó y la fecha. Las relaciones del supervisor con coordinación y su almacenista conservan sus usos previos.
3. En **Catálogos operativos → Artículos del formulario**, vincula los artículos con códigos reales del catálogo y ordénalos. Puedes cambiar nombres y orden de secciones. Si falta un producto, usa **Dar de alta un producto**. El alta crea el código y las existencias se registran cuando se recibe el inventario.
4. **Conos y reflectivos** incluye cuatro artículos iniciales. La migración vincula automáticamente una coincidencia inequívoca por nombre. Si falta el código, activa el artículo después de asociarlo. Carga desde el catálogo real los artículos de las demás secciones.
5. En **Motivos de asignación**, administra las etiquetas y las opciones vigentes. Desgaste solo registra el motivo. Robo abre seguimiento sin importe. Robo injustificado abre seguimiento con una referencia proporcional cuando se conocen el costo histórico y la vida restante de la custodia anterior. RR. HH. registra su decisión en el seguimiento.

Los permisos operativos se combinan con las excepciones individuales y la bodega asignada. Un operador de bodega secundaria conserva sus asignaciones manuales y traslados. Coordinación consulta inspecciones de sus regiones activas y consolidados bajo su responsabilidad. Administración puede consultar, configurar y reabrir según sus permisos.

## Inspección y consolidación

- El supervisor selecciona fecha, región y colaborador. Registra cantidades enteras desde cero, observaciones y hasta ocho fotos privadas. **Guardar y continuar** conserva la fecha y región y prepara el formulario para otra persona. **Guardar y finalizar** vuelve al listado. El detalle también permite iniciar otra inspección.
- Bodega central filtra por fechas, región, supervisor o coordinador. Al seleccionar los requerimientos, el formulario muestra el responsable calculado. El envío agrupa regiones con un único coordinador activo y vincula cada inspección una vez. Bodega central conserva la consulta de cantidades y el envío.
- En **Por colaborador**, coordinación ajusta cantidades, agrega productos e incorpora colaboradores activos de sus regiones. Cada cambio requiere una justificación y guarda el responsable, el momento y las cantidades anterior y nueva. **Resumen general** totaliza esas filas para compras. Las inspecciones originales conservan sus cantidades, observaciones y fotos.
- Coordinación puede aprobar o devolver el consolidado. Central puede reenviarlo para otra revisión. Administración puede reasignar un pendiente con justificación cuando todas sus regiones tienen el mismo nuevo responsable. Las aprobaciones conservan la identidad del coordinador. La reapertura requiere que la aprobación carezca de constancias, facturas y asignaciones vinculadas.

## Necesidades de compra

1. En **Necesidades de compra**, selecciona los consolidados aprobados y consulta **Resumen por producto**. Se suma el requerimiento aprobado y se muestra el saldo central actual una sola vez por código. El pendiente estima las unidades adicionales a comprar, y la compra total prevista suma lo ya recibido y el pendiente. Con un plan guardado, el pendiente se calcula sobre su cantidad decidida. El seguimiento por consolidado muestra sus cantidades y facturas.
2. Si usarás existencias de central para cubrir parte de un requerimiento, puedes guardar esas cantidades en **Ajustar plan de compra**. Este control es opcional para registrar facturas y conserva la planificación separada del saldo actual. El stock del plan sirve como referencia para el cierre y no reserva unidades.
3. Solicita la autorización de Gerencia fuera del sistema. Al registrar la factura, usa el campo **Autorizado por** y adjunta sus PDF. Distribuye las cantidades entre los consolidados que correspondan. Puedes agregar más facturas de otros proveedores o fechas. Cada factura conserva su consulta tradicional y las unidades no distribuidas ingresan a stock general.
4. **Cerrar compras** es una acción manual. Se habilita cuando se cubre lo aprobado con las compras recibidas y, cuando corresponde, el saldo considerado en el plan. Las facturas históricas se deben conciliar antes. El cierre conserva el estado técnico y su historial. Las asignaciones y las entregas continúan por su flujo habitual.
5. Las constancias y sus documentos previos están disponibles en **Constancias históricas**. Para facturas históricas sin reparto, abre el detalle y confirma solo las cantidades respaldadas. El sistema conserva los vínculos y añade las unidades al seguimiento cuando se confirma el desglose.

Solo registrar la factura aplica la entrada de inventario. La planificación, la consulta, el cierre y la conciliación conservan las existencias.

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

En una asignación manual o una importación masiva, bodega central puede seleccionar un consolidado aprobado o con compras cerradas que tenga un desglose verificado. Cada salida conserva el vínculo con el colaborador y producto aprobados. El cálculo cuenta las unidades entregadas desde el requerimiento, incluso cuando después se devuelven. Si la cantidad aprobada es insuficiente, el registro se revierte. El detalle por colaborador muestra las unidades asignadas y las facturas distribuidas al producto del consolidado. Las facturas documentan la compra para el requerimiento agrupado. La unidad física de un inventario compartido conserva la trazabilidad disponible en el módulo de bodega.

## Exportaciones Excel

**Exportar Excel** descarga un `.xlsx` con todos los resultados del filtro activo. Las consultas de usuarios, colaboradores, compras, bodegas, asignaciones, devoluciones, traslados y vehículos verifican los permisos de exportación configurados. Las consultas de inspecciones, consolidados, necesidades y regiones conservan los permisos operativos de cada pantalla. El Centro de datos mantiene CSV y ofrece Excel con los campos autorizados. Para incluir existencias por bodega en el catálogo de productos, la cuenta requiere el permiso de exportación de inventario.

Los archivos de los listados nuevos se escriben por lotes y fijan los encabezados y filtros. Los textos se almacenan como texto en Excel, incluso si empiezan con un símbolo de fórmula. Usuarios exporta identificación, rol y relaciones operativas autorizadas. Los archivos privados y las credenciales quedan fuera de los listados.

Abre cada hoja para imprimirla o guardarla como PDF desde el navegador. También puedes descargar todas juntas en un ZIP de hojas HTML imprimibles. Conserva junto a ellas el archivo de logotipo del ZIP. Los PDFs firmados y las fotos se adjuntan después desde el detalle de cada entrega. Los campos personalizados obligatorios que se usan en la importación requieren un valor predeterminado.

## Migración y validación

La migración `2026_11_20_000002_create_flujo_operativo.php` agrega regiones, coordinación, inspecciones y fotografías, consolidados e historial, constancias, vínculos de compras, motivos e importaciones. Comprueba si falta `asignaciones_inventarios.sustituida_por_id` y la agrega cuando corresponde. Conserva los valores históricos existentes y los archivos. Para una instalación autorizada, el comando es `php artisan migrate --force`.

La migración aditiva `2026_11_23_000001_track_consolidated_purchases.php` agrega el historial de planes por línea, la distribución por línea de factura y la fecha de confirmación. Las vinculaciones previas quedan disponibles para conciliación.

La migración `2026_11_23_000002_add_internal_purchase_plan_to_consolidados.php` agrega la planificación opcional. Conserva las constancias, las compras y las relaciones anteriores.

La migración `2026_11_24_000001_widen_requirement_quantities.php` amplía las cantidades de inspecciones y consolidados a enteros de 64 bits. La columna histórica `inspeccion_articulos.maximo` conserva sus valores y deja de intervenir en la captura.

La migración `2026_11_24_000002_add_regional_requirement_tracking.php` agrega el coordinador actual y el historial de regiones, las observaciones de inspección, el detalle del consolidado por persona y las referencias de entregas e importaciones. Para actualizar una instalación local, descarga la rama, ejecuta `composer install`, `npm ci`, `npm run build` y `php artisan migrate`. Luego configura los responsables de las regiones desde Administración de usuarios. La actualización aprovecha Laravel, PhpSpreadsheet y las extensiones PHP que ya requiere el proyecto.

Los consolidados históricos mantienen sus cantidades agregadas, documentos y responsables. Coordinación puede verificar el desglose de un pendiente cuando coincide con las inspecciones originales. Los históricos con diferencias sin respaldo individual quedan identificados como pendientes de verificación. Si un cambio regional divide un consolidado pendiente entre varios coordinadores, la reasignación queda bloqueada hasta que Administración resuelva la distribución.

Prueba en un entorno aislado.

1. Da de alta dos coordinadores, un supervisor y colaboradores de dos regiones. Busca y registra una inspección con foto. Verifica las restricciones desde el otro equipo.
2. Consolida dos inspecciones, modifica una cantidad con nota, aprueba y comprueba los valores originales, las diferencias y el historial.
3. Revisa existencias y faltantes. Guarda un plan opcional y verifica que no cambie el saldo. Registra una compra vinculada, repite la factura y comprueba que el movimiento se registró una sola vez.
4. Importa un Excel inválido y otro válido. Comprueba la vista previa, el descuento en central, las hojas, el ZIP y el reintento. Registra una asignación manual y un traslado desde las rutas habituales.
5. Prueba los tres motivos y consulta el caso de robo injustificado como RR. HH. La referencia proporcional queda como dato de revisión.

La prueba concurrente `php tests/Integration/flujo_mysql.php` usa MySQL local y una base desechable `edson_*_qa` con `APP_ENV=testing`. Registra dos previsualizaciones equivalentes con usuarios distintos e intenta confirmar ambas en paralelo.

La prueba `php tests/Integration/compras_consolidadas_mysql.php` usa el mismo entorno local desechable. Envía dos facturas en paralelo para el mismo pendiente y repite una factura de manera concurrente. Comprueba las distribuciones, los movimientos y el saldo.

La prueba `php tests/Integration/entregas_mysql.php` verifica además que dos salidas concurrentes de un requerimiento con una unidad aprobada registren una sola asignación y un único descuento.

## Capturas

Las pantallas se capturaron en Chromium con datos ficticios.

- [Administración de regiones por coordinador](capturas/flujo-regional/01-regiones.png)
- [Inspecciones consecutivas en móvil](capturas/flujo-regional/02-inspeccion-movil.png)
- [Envío del consolidado al responsable regional](capturas/flujo-regional/03-envio-coordinacion.png)
- [Revisión de necesidades por colaborador](capturas/flujo-regional/04-revision-colaborador.png)
- [Resumen general de cantidades](capturas/flujo-regional/05-resumen-general.png)
- [Asignación vinculada a un requerimiento aprobado](capturas/flujo-regional/06-asignacion-vinculada.png)
- [Inspección del supervisor en móvil](capturas/flujo-operativo/01-inspeccion-movil.png)
- [Revisión y ajustes del coordinador](capturas/flujo-operativo/02-revision-coordinacion.png)
- [Registro de factura y reparto entre consolidados](capturas/flujo-operativo/03-registro-compra.png)
- [Resumen de necesidades y compras parciales](capturas/flujo-operativo/04-necesidades-de-compra.png)
- [Necesidades de compra en móvil](capturas/flujo-operativo/05-necesidades-movil.png)
- [Vista previa de asignaciones desde Excel](capturas/flujo-operativo/06-excel-vista-previa.png)
