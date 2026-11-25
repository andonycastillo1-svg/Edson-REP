# Estandarización de la interfaz de Edson-REP

La revisión parte de `e339b376f545484023399e8c6c2c3509078ae2fe` en `main`. UI/UX Pro Max orientó los cambios de contraste, jerarquía, foco, tamaño de los controles y distribución móvil. La [paleta corporativa y las pautas de componentes](../design-system/edson-rep/MASTER.md) se aplican desde los estilos compartidos.

## Auditoría por módulo

| Módulo | Hallazgo y corrección |
| --- | --- |
| Inicio de sesión, recuperación, autenticación y pantallas de error | Los controles y mensajes usan la base visual compartida y el foco visible. El acceso mantiene el logotipo y la estructura de autenticación vigente. |
| Paneles de administrador, operador, almacenista, supervisor, coordinador y RR. HH. | Había tarjetas y enlaces repetidos con jerarquías distintas. La barra lateral y los paneles comparten los accesos operativos filtrados por permisos. El coordinador conserva la consulta de consolidados pendientes y RR. HH. el indicador de incidencias. |
| Administración, usuarios, roles, permisos, relaciones, delegaciones, campos y auditoría | Los formularios, acciones secundarias, tablas y estados usan los mismos estilos. Se mantienen las agrupaciones y las comprobaciones que habilitan cada vista. |
| Bodegas, productos, existencias y movimientos | Las tarjetas mostraban acciones y colores distintos según la bodega. Ahora comparten estructura y muestran tipo, ubicación, relación con el usuario y acciones autorizadas. El inventario usa filtros y paginación comunes. La revisión de traslados tiene un acceso en el encabezado. |
| Compras, proveedores, facturas y necesidades de compra | Dos instancias del componente de datos generaban «Consultar datos». El listado concentra búsqueda, tamaño de página y exportación. «Registrar compra» abre la página completa y «Compra rápida» abre el mismo formulario como diálogo. El detalle separa productos, distribución y adjuntos. |
| Asignaciones, entregas, devoluciones e incidencias | El acceso aislado a «Hojas firmadas faltantes» pasó a un filtro del módulo. El indicador cuenta entregas visibles sin hoja firmada, aunque una entrega contenga varios productos. Se conservan los detalles, las resoluciones y la consulta de evidencias. |
| Colaboradores y alertas de RR. HH. | El directorio usaba estilos distintos para activos e inactivos. Ambos estados comparten tabla y acciones. «Seleccionar visibles», «Limpiar selección» y «Descargar fichas en Excel» se agrupan con un indicador de selección. La acción de inactivar sigue visible y requiere confirmación. |
| Traslados | El listado, filtros y detalle reemplazan las reglas de estilo locales por tablas, tarjetas y etiquetas comunes. La confirmación diferencia aprobar y rechazar. Los accesos para hoja y Excel adjunto siguen sujetos a permiso. |
| Inspecciones, cortes, consolidados, coordinación, regiones y catálogos operativos | El menú lateral concentra la navegación que se repetía en los encabezados móviles. El flujo de cada pantalla y los controles vigentes usan la misma paleta, botones y formularios. |
| Importación y exportación de Excel, datos de los módulos y consultas históricas | Los accesos y operaciones se conservan. «Datos de los módulos» sigue disponible en el menú cuando hay autorización. Los enlaces de edición de campos indican el módulo al que pertenecen. |
| Notificaciones | La campana comparte familia de iconos y componentes de botón y estado. El indicador usa el número real de notificaciones pendientes y una etiqueta para lectores de pantalla. |
| Vehículos, asignaciones de vehículos y productos del vehículo | Las vistas históricas adoptan los tokens que comparten los módulos activos. El módulo conserva su configuración de deshabilitado y las hojas PDF mantienen el formato de impresión. |

## Verificación

- Compilación correcta con `npm run build` y `php artisan view:cache`. PHPUnit aprobó 146 pruebas y 2948 aserciones. Se agregaron comprobaciones de los permisos de compra en panel y listado, las bodegas autorizadas como origen de traslados y el conteo de una sola hoja por entrega.
- Chromium con datos ficticios y perfiles de administrador, operador central, almacenista regional, supervisor, coordinador y RR. HH. en anchos de 1366 y 390 px. Se revisaron acceso y cierre de sesión, menú móvil, accesos restringidos, filtros, paginación, líneas de formularios, detalle de factura, descargas de Excel, cierre de diálogos con Escape, retorno del foco, confirmación de inactivación, seguimiento de hojas y selección de colaboradores.
- Las pantallas de vehículos y los documentos imprimibles históricos se revisaron en el código. Las pruebas automatizadas cubren la consulta de documentos privados, los permisos y los flujos operativos existentes.
- La densidad de las tablas depende de los campos configurados por la organización. En móvil, un listado con muchas columnas requiere desplazamiento dentro de su tabla. Los usuarios con permisos adicionales pueden ver más tarjetas en su panel.

## Capturas comparativas

Las capturas usan los mismos datos ficticios y anchos de pantalla antes y después.

| Pantalla | Antes | Después |
| --- | --- | --- |
| Panel del operador, escritorio | [Ver captura](capturas/interfaz-unificada/operador-panel-antes.png) | [Ver captura](capturas/interfaz-unificada/operador-panel-despues.png) |
| Bodegas, escritorio | [Ver captura](capturas/interfaz-unificada/bodegas-antes.png) | [Ver captura](capturas/interfaz-unificada/bodegas-despues.png) |
| Compras, escritorio | [Ver captura](capturas/interfaz-unificada/compras-antes.png) | [Ver captura](capturas/interfaz-unificada/compras-despues.png) |
| Colaboradores de RR. HH., escritorio | [Ver captura](capturas/interfaz-unificada/colaboradores-antes.png) | [Ver captura](capturas/interfaz-unificada/colaboradores-despues.png) |
| Seguimiento de hojas firmadas, móvil | [Ver captura](capturas/interfaz-unificada/seguimiento-movil-antes.png) | [Ver captura](capturas/interfaz-unificada/seguimiento-movil-despues.png) |
