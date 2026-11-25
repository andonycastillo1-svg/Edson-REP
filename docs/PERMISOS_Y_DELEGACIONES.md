# Permisos, relaciones y procesos operativos

## Configurar una cuenta

Desde **Configuración → Seguridad → Cuentas**, abre la cuenta que corresponda. El acceso a esta edición requiere un superadministrador. La edición operativa de RR. HH. y Administración conserva sus propios controles.

1. En **Información general**, confirma el cargo principal y el estado de la cuenta.
2. En **Permisos**, busca por acción o módulo. **Heredar** aplica la regla anterior o la del cargo, **Permitir** otorga una excepción y **Denegar** la retira. Guarda los cambios con la contraseña de tu cuenta. La bitácora guarda el responsable y los valores anterior y nuevo.
3. En **Relaciones**, configura regiones y bodegas autorizadas. Para operar requerimientos de central, selecciona también la responsabilidad de central y concede los permisos necesarios. Para coordinadores, las regiones adicionales complementan la responsabilidad principal y conservan sus vínculos históricos. Los operadores regionales mantienen su propia bodega.
4. En **Delegaciones**, selecciona el titular, las funciones, las regiones o bodegas y las horas de inicio y fin. El sistema usa la zona horaria de la aplicación. El inicio es inclusivo y el fin exclusivo. Se exige el permiso de consulta con acciones de registro, revisión o exportación. La persona que autoriza confirma con su contraseña. El sustituto usa su identidad y el historial conserva las fechas, el motivo, el alcance, la autorización y la revocación.

### Permisos disponibles

| Área | Acciones configurables | Alcance aplicado |
| --- | --- | --- |
| Inspecciones | Consultar, registrar y exportar | Regiones activas asignadas. La captura exige un colaborador activo de la región. La consulta del supervisor se limita a su autoría y sus regiones. La responsabilidad de central conserva el alcance global del flujo. |
| Cortes y consolidados | Preparar cortes, crear, enviar, consultar, exportar, revisar, ajustar, agregar personas, aprobar o devolver y reabrir según el permiso | Preparar y enviar requiere responsabilidad de central. Revisar y decidir requiere ser el coordinador seleccionado con alcance regional, o contar con delegación válida de esa persona. |
| Necesidades de compra | Consultar, exportar, guardar el plan, registrar constancias y cerrar compras | Responsabilidad de central y estados válidos del requerimiento. La planificación y las constancias conservan el inventario. |
| Facturas, traslados y asignaciones | Consulta, registro, documentos y exportaciones en los ámbitos operativos existentes. Se pueden denegar individualmente la conciliación de factura, el rechazo de traslado y la confirmación de recepción | Los controladores conservan sus restricciones por bodega, responsable, custodia o registro. Las nuevas relaciones de consulta o planificación no autorizan movimientos de inventario. |
| Existencias | Consulta y exportación de bodegas autorizadas, además de consulta de movimientos con permiso individual | El permiso se aplica a la bodega asignada o vinculada. Las delegaciones de bodega habilitan consulta y exportación del alcance explícito. |
| Catálogos y administración | Mantener catálogos y procesos, configurar regiones, gestionar relaciones y permisos. En RR. HH., distinguir regularización y cierre de incidencias | La seguridad exige superadministración y confirmación. RR. HH. conserva sus roles de alta y el límite sobre cuentas creadas bajo su responsabilidad. |

Las nuevas opciones con una acción previa definida heredan esa acción salvo regla explícita. Ningún usuario recibe un permiso nuevo por la migración. Las inspecciones autorizadas a coordinadores, operadores u otros cargos guardan como actor a la cuenta autenticada.

La delegación cubre inspecciones, revisión de consolidados y consulta o exportación de inventario por bodega. Cada solicitud recalcula vigencia, permisos permanentes y relaciones actuales del titular. Los permisos administrativos, las mutaciones de existencias y la gestión de delegaciones de RR. HH. siguen sus autorizaciones originales. Se impiden las delegaciones a sí mismo y los solapamientos en cadena o recíprocos. El retiro de un permiso permanente del titular reduce el alcance temporal de inmediato.

## Procesos de inspección y responsables de corte

Administra **Cortes y Reconexión** y **Nuevos Suministros** en **Catálogos operativos → Procesos de inspección**. Puedes crear procesos, cambiar su nombre o desactivarlos. El nuevo nombre se usa en capturas posteriores. Las anteriores guardan el texto original y las que preceden a la migración figuran como **Histórica sin proceso**. El filtro admite ambos casos y las exportaciones incluyen el proceso de origen.

En **Inspecciones → Nuevo corte**, revisa la selección. El sistema sugiere un coordinador si solo hay uno activo y autorizado. Cuando hay varios, selecciona uno por región y actualiza la vista previa. Cada consolidado conserva el responsable confirmado y sus inspecciones de origen, además del proceso indicado en ellas. La revisión y aprobación continúan con las cantidades existentes. El proceso **Cortes y Reconexión** se usa en la captura. Los cortes de requerimientos siguen siendo períodos elegidos por el operador.

## Actualización local

Con el entorno local configurado, ejecuta `composer install`, `npm ci`, `php artisan migrate` y `npm run build`. La migración `2026_11_25_000001_add_operational_access_and_inspection_processes.php` agrega catálogos, relaciones, delegaciones y referencias. Los dos procesos iniciales se crean en el catálogo. Las inspecciones, existencias y responsables ya registrados conservan sus valores. Las nuevas tablas usan claves foráneas y protegen el historial frente a una reversión con datos.

Para verificar la operación, ejecuta `vendor/bin/phpunit` tras compilar Vite y revisa los recorridos de roles con cuentas ficticias. Las pruebas de integración concurrente se ejecutan en MySQL local y desechable conforme a la guía del flujo. La [auditoría independiente de base de datos](AUDITORIA_BASE_DE_DATOS.md) documenta el esquema y los riesgos de depuración.

## Capturas

- [Reglas individuales y búsqueda de permisos](capturas/permisos-operativos/01-permisos-individuales.png)
- [Relaciones regionales y de bodega](capturas/permisos-operativos/02-relaciones-regionales.png)
- [Delegación activa y sus funciones](capturas/permisos-operativos/03-delegacion-activa.png)
- [Proceso de inspección en móvil](capturas/permisos-operativos/04-proceso-en-movil.png)
- [Selección de coordinador en un corte](capturas/permisos-operativos/05-eleccion-coordinador.png)
