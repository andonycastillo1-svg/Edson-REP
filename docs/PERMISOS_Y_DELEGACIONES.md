# Permisos, relaciones y procesos operativos

## Configurar una cuenta

Desde **Configuración → Seguridad → Cuentas**, abre la cuenta que corresponda. El acceso a esta edición requiere un superadministrador. La edición operativa de RR. HH. y Administración conserva sus propios controles.

1. En **Información general**, confirma el cargo principal y el estado de la cuenta.
2. En **Permisos**, busca por acción o módulo. **Heredar** aplica la regla anterior o la del cargo, **Permitir** otorga una excepción y **Denegar** la retira. Guarda los cambios con la contraseña de tu cuenta. La bitácora guarda el responsable y los valores anterior y nuevo.
3. En **Relaciones**, selecciona las regiones y marca **Bodegas para operar inventario** para la cuenta. Para entregas, devoluciones e importaciones marca también las regiones de los colaboradores correspondientes. **Bodegas para consulta** habilita la relación para consultar existencias con su permiso. **Responsabilidad de requerimientos de central** habilita el alcance de cortes y planificación con sus propios permisos. Para coordinadores, las regiones adicionales complementan su responsabilidad principal.
4. En **Delegaciones**, selecciona titular, funciones, alcance, inicio y fin. Elige la consulta del módulo si se necesita abrir registros y documentos, y añade las acciones concretas que correspondan. El sistema indica si falta una acción requerida. El inicio es inclusivo y el fin exclusivo, en la zona horaria de la aplicación. La persona autorizadora confirma con su contraseña.

### Permisos disponibles

| Área | Acciones configurables | Alcance aplicado |
| --- | --- | --- |
| Inspecciones | Consultar, registrar y exportar | Regiones activas asignadas. La captura exige un colaborador activo de la región. La consulta del supervisor se limita a su autoría y sus regiones. La responsabilidad de central conserva el alcance global del flujo. |
| Cortes y consolidados | Preparar cortes, crear, enviar, consultar, exportar, revisar, ajustar, agregar personas, aprobar o devolver y reabrir según el permiso | Preparar y enviar requiere responsabilidad de central. Revisar y decidir requiere ser el coordinador seleccionado con alcance regional, o contar con delegación válida de esa persona. |
| Necesidades de compra | Consultar, exportar, guardar el plan, registrar constancias y cerrar compras | Responsabilidad de central y estados válidos del requerimiento. La planificación y las constancias conservan el inventario. |
| Facturas | Consultar, registrar, adjuntar o abrir documentación, exportar, vincular a requerimientos y conciliar la distribución | Bodega central. La factura nueva registra su bodega. Las facturas anteriores sin bodega conservan el acceso histórico habilitado. |
| Traslados | Consultar, solicitar, aprobar, rechazar, consultar documentación y exportar | Solicitar exige origen y destino autorizados con las nuevas relaciones. La decisión corresponde al destino y a una persona diferente de quien solicitó. |
| Entregas y devoluciones | Consultar, asignar, vincular a requerimientos, confirmar recepción, resolver custodias, autorizar entregas por excepción, documentar y exportar | Bodega y región del colaborador. Los registros históricos conservan el alcance de la cuenta que los gestionó o del almacenista relacionado. |
| Importaciones | Preparar, confirmar y conciliar entregas por Excel. Descargar hojas con autorización de exportación | Central y todas las regiones que contiene el archivo. Cada cuenta conserva sus propios lotes. Para usar un consolidado se requiere además permiso para vincular entregas. |
| Existencias | Consulta y exportación de bodegas autorizadas, además de consulta de movimientos con permiso individual | El permiso se aplica a la bodega asignada o vinculada. Las delegaciones de bodega habilitan consulta y exportación del alcance explícito. |
| Catálogos y administración | Mantener catálogos y procesos, configurar regiones, gestionar relaciones y permisos. En RR. HH., distinguir regularización y cierre de incidencias | La seguridad exige superadministración y confirmación. RR. HH. conserva sus roles de alta y el límite sobre cuentas creadas bajo su responsabilidad. |

Las nuevas opciones con una acción previa definida heredan esa acción salvo regla explícita. Ningún usuario recibe un permiso nuevo por la migración. Las inspecciones autorizadas a coordinadores, operadores u otros cargos guardan como actor a la cuenta autenticada.

Cada solicitud recalcula vigencia, permisos permanentes y relaciones actuales del titular. Para delegar compras selecciona central. Para delegar salidas o devoluciones selecciona la bodega y las regiones. Las importaciones por Excel requieren además la acción de asignar. Para delegar la resolución de traslados, selecciona las bodegas receptoras. Si solo se selecciona aprobar, la persona sustituta necesita la acción de consulta para abrir su bandeja. Cada operación debe caber en un solo alcance. Los lotes quedan bajo la cuenta que los preparó y se vuelven a validar al confirmar. La bitácora registra la delegación que autorizó una operación y la identidad real de quien la realizó. Las funciones de seguridad administrativa conservan su acceso protegido. Se impiden las delegaciones a sí mismo y los solapamientos en cadena o recíprocos.

### Compatibilidad y controles de stock

El administrador y el operador heredados conservan el alcance con que ya trabajan. La bodega del perfil operativo mantiene su función para ese operador. Para las custodias existentes del operador se conserva la autoría y, cuando tiene regiones asignadas, se aplica su alcance regional. El supervisor conserva la consulta de las custodias de su almacenista relacionado. La autorización antigua para solicitar traslados conserva el uso de otras bodegas como destinatarias de la solicitud. La aceptación aplica el movimiento amparado por esa solicitud desde su origen, y solo puede confirmarse en el destino. El saldo del origen se revalida bajo bloqueo.

Una relación nueva requiere un permiso separado para operar. La autorización de recepción, de rechazo y de conciliación se conserva para los ámbitos heredados con las reglas de compatibilidad. En los cargos que se incorporan por permisos individuales, estas opciones se pueden conceder por separado. Ninguna delegación ni permiso administrativo desde RR. HH. amplía su ámbito de usuarios. Las compras están vinculadas al saldo por la entrada registrada, las devoluciones aplican sus condiciones de retorno y una importación repetida conserva la protección por huella.

## Procesos de inspección y responsables de corte

Administra **Cortes y Reconexión** y **Nuevos Suministros** en **Catálogos operativos → Procesos de inspección**. Puedes crear procesos, cambiar su nombre o desactivarlos. El nuevo nombre se usa en capturas posteriores. Las anteriores guardan el texto original y las que preceden a la migración figuran como **Histórica sin proceso**. El filtro admite ambos casos y las exportaciones incluyen el proceso de origen.

En **Inspecciones → Nuevo corte**, revisa la selección. El sistema sugiere un coordinador si solo hay uno activo y autorizado. Cuando hay varios, selecciona uno por región y actualiza la vista previa. Cada consolidado conserva el responsable confirmado y sus inspecciones de origen, además del proceso indicado en ellas. La revisión y aprobación continúan con las cantidades existentes. El proceso **Cortes y Reconexión** se usa en la captura. Los cortes de requerimientos siguen siendo períodos elegidos por el operador.

## Actualización local

Con el entorno local configurado, ejecuta `composer install`, `npm ci`, `php artisan migrate` y `npm run build`. La migración `2026_11_25_000001_add_operational_access_and_inspection_processes.php` agrega catálogos, relaciones, delegaciones y referencias. La migración `2026_11_25_000002_scope_inventory_operations.php` agrega la responsabilidad de operar inventario por bodega y registra la bodega en las nuevas facturas. Las referencias históricas sin bodega se conservan como tales. Los dos procesos iniciales se crean en el catálogo. Las inspecciones, existencias y responsables ya registrados conservan sus valores. Las nuevas tablas usan claves foráneas y protegen el historial frente a una reversión con datos.

Para verificar la operación, ejecuta `vendor/bin/phpunit` tras compilar Vite y revisa los recorridos de roles con cuentas ficticias. Las pruebas de integración concurrente se ejecutan en MySQL local y desechable conforme a la guía del flujo. La [auditoría independiente de base de datos](AUDITORIA_BASE_DE_DATOS.md) documenta el esquema y los riesgos de depuración.

## Capturas

- [Reglas individuales y búsqueda de permisos](capturas/permisos-operativos/01-permisos-individuales.png)
- [Relaciones regionales y de bodega](capturas/permisos-operativos/02-relaciones-regionales.png)
- [Delegación activa y sus funciones](capturas/permisos-operativos/03-delegacion-activa.png)
- [Proceso de inspección en móvil](capturas/permisos-operativos/04-proceso-en-movil.png)
- [Selección de coordinador en un corte](capturas/permisos-operativos/05-eleccion-coordinador.png)
