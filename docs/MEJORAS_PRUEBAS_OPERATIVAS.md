# Mejoras de las pruebas operativas de Edson-REP

Base de la revisión: `baa2f933a489e9d437a839481c32ab76fb78e8d4`. Las pruebas y las capturas usan cuentas, expedientes e inventario de demostración.

## Procedimientos

| Área | Operación |
| --- | --- |
| Coordinación | Abre el consolidado recibido. Busca colaboradores por nombre o código y productos por nombre o código. Guarda los ajustes y aprueba desde el detalle. El historial conserva los eventos de revisión y devolución anteriores. |
| Necesidades de compra | Selecciona los consolidados. El resumen distingue lo aprobado, el saldo central actual, el stock considerado en el plan, la compra total y lo recibido o pendiente. Abre el detalle de cada consolidado para ver sus facturas y registrar otra compra parcial. |
| Ajustar plan | Compara **Stock central** con **Stock a usar** y revisa **Pendiente**. Guardar el plan registra la planificación. El movimiento de entrada se genera al registrar la factura y su recepción. |
| Compras | Busca al proveedor existente o registra uno nuevo. En una compra vinculada puedes editar las unidades repartidas por consolidado. La fecha de la factura admite hasta siete días anteriores, incluido hoy. La fecha real de ingreso admite hasta el momento actual. La regla se aplica al registro de compras nuevas. |
| Asignación manual | En **Asignaciones y devoluciones → Nueva asignación**, elige **Asignación manual**. Selecciona el consolidado dentro de su corte y luego al colaborador. El formulario muestra sus líneas aprobadas pendientes. Elige **Asignación independiente** para una entrega extraordinaria. |
| Asignación masiva | Elige **Asignación masiva**, selecciona **Corte completo** y descarga el Excel. El origen de cada fila se verifica al cargar y confirmar. Puedes ajustar la cantidad o retirar una fila. Si cambió el avance desde la descarga, vuelve a obtener los pendientes. Para más de 500 unidades, descarga las partes y procésalas en orden. Si modificas las cantidades de una parte, vuelve a descargar el saldo que quede. Las plantillas anteriores siguen disponibles. |
| Entregas | El consolidado conserva las entregas terminadas en el historial. Los selectores de nuevas entregas se basan en unidades efectivamente registradas por línea aprobada. |
| Hojas firmadas | En **Asignaciones y devoluciones → Hojas firmadas**, filtra por persona, región, fechas y estado. Abre la entrega para consultar la hoja o adjuntar el PDF firmado y una fotografía, según el permiso. El primer PDF cambia el estado a **Hoja recibida**. |
| Traslados | La bodega de origen solicita el traslado. La bodega receptora decide y la hoja muestra el estado vigente de la solicitud. Desde el detalle puedes imprimirla en papel carta. |
| Expediente | Usa **Productos asignados** para consultar custodia actual, **Historial** para ver reemplazos y devoluciones e **Incidencias** para consultar los casos registrados. Productos, historial e incidencias se consultan por páginas. |
| Incidencias y Excel | Filtra por persona, región, fechas, estado o motivo y pulsa **Exportar Excel**. La hoja **Resumen** cuenta las incidencias por estado. **Detalle de incidencias** reúne fechas, datos de custodia, referencia económica y actuaciones. |

## Vida útil, incidencias y avisos

- El cálculo parte de la recepción registrada y de la duración acreditada al entregar la unidad. Para artículos usados se conserva la vida disponible registrada. Ante datos insuficientes, la pantalla identifica la vida desconocida y remite a la regularización autorizada con evidencia.
- **Cambio por daño** y **Robo injustificado** remiten el caso a RR. HH. e incluyen una referencia proporcional si constan el costo histórico y la vida pendiente. **Robo** remite a revisión. **Cambio por Energuate** registra el motivo con política sin propuesta económica. Se conserva la política de los motivos configurados previamente.
- La referencia proporcional es por unidad y se calcula con el costo histórico multiplicado por la fracción de vida pendiente bajo la convención de 30 días por mes. RR. HH. registra su evaluación y cierre en el seguimiento. El Excel marca **No calculable** cuando falta evidencia y explica los datos faltantes.
- Los avisos nuevos cubren el envío, aprobación y devolución histórica del consolidado, registro de compras vinculadas, cierre de compras, entrega de central a responsables regionales y primera recepción de la hoja firmada. El aviso de cierre invita a preparar la entrega y verificar el saldo al asignar. El recordatorio de firmas, el aviso de traslado y las actuaciones de RR. HH. se apoyan en los flujos existentes. Los avisos respetan las preferencias de la cuenta.

## Configurar la documentación regional

En **Configuración del sistema → Cuentas y permisos**, concede **Consultar y documentar entregas de central en regiones autorizadas** (`asignaciones.documentacion_regional`) a la cuenta que lo necesite. Verifica su relación con una región activa y los permisos concretos de consulta, descarga, archivos o carga de evidencias. El permiso adicional abre el alcance documental de la bodega central para esas regiones. La creación, recepción operativa y devolución de asignaciones conservan sus controles propios.

El sistema registra el usuario, fecha, entrega y tipo al adjuntar una hoja o fotografía. RR. HH. con regiones activas configuradas consulta y exporta incidencias de ese alcance. El perfil central de RR. HH. sin relación regional conserva su cobertura habitual.

## Configurar una plantilla

1. Ingresa con una cuenta de superadministración. Abre **Configuración del sistema → Plantillas de documentos**.
2. Elige **Asignación**, **Traslado** o **Devolución**.
3. Edita la empresa, encabezado, título, departamento, textos y firmas. Selecciona los campos opcionales del formato, distribución y anchura del logotipo. Para reemplazar el logotipo, usa PNG o JPG de hasta 1 MB y hasta 1800 píxeles por lado.
4. Abre **Vista previa** en otra pestaña o **Descargar muestra PDF**. Las muestras usan datos ficticios.
5. Escribe tu contraseña actual en el formulario de publicación y confirma. El historial muestra quién publicó cada versión y cuándo.
6. Cada operación queda asociada a su formato al emitir la primera hoja desde esta actualización. Los PDF archivados previamente se siguen sirviendo desde su archivo. Para registros antiguos cuya hoja nunca se archivó, se aplica la versión vigente al emitirla con esta actualización.

## Actualización y diagnóstico

| Migración aditiva | Cambio |
| --- | --- |
| `2026_11_27_000001_add_replacement_policy_snapshot.php` | Agrega la política de reemplazo a las alertas nuevas y añade los dos códigos de motivo cuando aún faltan. |
| `2026_11_27_000002_create_document_template_versions.php` | Crea el historial de plantillas y de versiones emitidas. |

Ambas migraciones preservan la información previa. Una reversión de la aplicación debe conservar el esquema y los registros nuevos para mantener la trazabilidad. En un entorno de aceptación, conviene confirmar el alcance de las cuentas de RR. HH., revisar los destinatarios de los avisos y ajustar el formato con documentos representativos de varias páginas.

El error `SQLSTATE[HY000] [2002]` corresponde a un fallo de conexión. La causa del incidente de MariaDB sigue indeterminada. Para investigarla, registra la hora exacta y correlaciónala con los logs de Laravel, del servicio MariaDB y del sistema operativo. Comprueba que el servicio seguía en ejecución y que la conexión TCP o por socket estaba disponible. Después de restablecer la conexión, compara tiempos de respuesta y planes de consulta en una copia de pruebas. La paginación limita la información cargada por el expediente. La exportación completa depende todavía del volumen del conjunto filtrado.

## Evidencia

- 159 pruebas y 3,227 aserciones en PHPUnit con SQLite en memoria, con permisos, avisos, compras, cortes, asignaciones, devoluciones, vida útil y exportaciones.
- MySQL 8.4 desechable con los cuatro scripts de integración de `tests/Integration`. Incluyen concurrencia de facturas, cortes, salidas vinculadas, devolución e importación y comparación de saldos y hojas.
- Chromium con sesiones ficticias de central, coordinación, RR. HH. y administración. Se revisó la navegación a 1440 y 390 píxeles y se verificó la ausencia de errores JavaScript en esas pantallas. Las tres hojas de muestra tienen una página tamaño carta.
- [Ejemplo Excel de RR. HH.](capturas/mejoras-operativas/ejemplo-incidencias-rrhh.xlsx)

| Pantallas | Capturas |
| --- | --- |
| Coordinación y compras | [Revisión](capturas/mejoras-operativas/revision-coordinador.jpg) · [Necesidades](capturas/mejoras-operativas/necesidades.jpg) · [Plan](capturas/mejoras-operativas/plan-ajuste.jpg) · [Factura parcial](capturas/mejoras-operativas/compra-parcial.jpg) |
| Asignaciones y traslados | [Modos](capturas/mejoras-operativas/asignacion-modos.jpg) · [Requerimiento](capturas/mejoras-operativas/asignacion-requerimiento.jpg) · [Excel por corte](capturas/mejoras-operativas/excel-corte.jpg) · [Hojas firmadas](capturas/mejoras-operativas/seguimiento-hojas.jpg) · [Solicitud de traslado](capturas/mejoras-operativas/traslado-formulario.jpg) |
| RR. HH. y configuración | [Incidencias](capturas/mejoras-operativas/incidencias-rrhh.jpg) · [Detalle](capturas/mejoras-operativas/incidencia-detalle.jpg) · [Expediente](capturas/mejoras-operativas/expediente.jpg) · [Plantillas](capturas/mejoras-operativas/plantillas.jpg) |
| Móvil | [Asignación](capturas/mejoras-operativas/movil-asignacion.jpg) · [Necesidades](capturas/mejoras-operativas/movil-necesidades.jpg) · [RR. HH.](capturas/mejoras-operativas/movil-rrhh.jpg) |
| Documentos impresos | [Asignación](capturas/mejoras-operativas/hoja-asignacion.jpg) · [Traslado](capturas/mejoras-operativas/hoja-traslado.jpg) · [Devolución](capturas/mejoras-operativas/hoja-devolucion.jpg) |
