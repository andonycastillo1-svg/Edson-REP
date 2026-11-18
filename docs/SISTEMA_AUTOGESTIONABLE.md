# Configuración del sistema

## Qué se puede administrar

- **Roles y permisos** desde el centro de configuración, con roles nuevos, duplicación, desactivación, permisos efectivos, permisos por usuario y administración de cuentas.
- **Campos personalizados** para usuarios, colaboradores, productos, bodegas, compras, inventario, asignaciones, traslados, vehículos y refacciones.
- **Presentación global** mediante la vista configurable de cada módulo. Los enlaces llevan a las pantallas operativas, que conservan su estructura y sus acciones. La vista configurable permite ordenar y renombrar columnas, configurar filtros, búsqueda, paginación y exportar CSV. Los formularios operativos aplican los cambios a los campos nativos opcionales definidos en el catálogo.
- **Parámetros generales** de identidad, categorías sugeridas, vida útil predeterminada y alertas de existencias. El cambio de bodega principal se bloquea cuando hay historial operativo. El ajuste de inactividad puede acortar el tiempo configurado por infraestructura.
- **Notificaciones** operativas por evento, roles y usuarios. La selección restringe los destinatarios elegibles definidos por cada flujo. Las notificaciones de seguridad, supervisión y RR. HH. siguen la regla del módulo.
- **Bitácora** paginada de solo lectura. Un Superadministrador puede ver eventos de seguridad. Un administrador delegado puede consultar eventos operativos. Para los demás ámbitos delegados, la consulta se limita a sus propias acciones.

## Autorización y transición

`app/Sistema/CatalogoPermisos.php` traduce los nombres de las rutas protegidas a permisos funcionales. Los ámbitos 1 a 4 conservan los controles del sistema y las rutas que ya utilizaban. A un rol nuevo se le asigna expresamente uno de esos ámbitos. Empieza sin permisos, salvo al duplicar un rol. La matriz de pruebas comprueba el catálogo contra las rutas protegidas.

El ámbito impone el límite de acceso. Laravel comprueba además el registro, su bodega, su creador o la relación de supervisión según el módulo. Dentro de ese límite, la regla individual prevalece sobre el rol. Después se evalúan la regla explícita del rol y los accesos históricos de los roles 1 a 4. Un permiso nuevo o desconocido queda denegado por defecto. Los permisos nuevos para editar fichas, exportar CSV y descargar archivos personalizados se conceden de forma separada.

El Superadministrador es una autorización separada. Los administradores existentes conservan la operación ordinaria. La gestión de cuentas administrativas, de cuentas con permisos críticos y de los permisos de lectura y edición de un campo requiere un Superadministrador y confirmación de contraseña cuando se modifica. La baja de usuarios desactiva la cuenta y preserva sus registros. La interfaz no ofrece comandos de servidor ni entrada de SQL o PHP.

### Primera cuenta y recuperación

1. Aplicar la migración aditiva en el entorno autorizado y verificar que existe una cuenta administrativa activa y controlada por su titular.
2. Desde la consola autorizada, ejecutar `php artisan sistema:superadministrador ID_DE_USUARIO`. Confirmar la operación e introducir la contraseña actual de esa cuenta en la solicitud oculta. El comando habilita una cuenta cuando no existe otro Superadministrador efectivo y registra la promoción.
3. Iniciar sesión, abrir **Configuración del sistema** y comprobar la bitácora. Si se requiere una segunda cuenta, otorgar el permiso desde el panel usando una cuenta administrativa existente. Ejercitar la recuperación primero en un entorno aislado.

## Almacenamiento y compatibilidad

`sistema_campos` almacena el módulo, clave, tipo, presentación y permisos. Cada valor está asociado a una definición y al identificador canónico de su registro. `sistema_campo_historial` guarda las versiones anteriores. El catálogo fija la tabla y la clave de cada módulo. La identidad y el tipo de una definición se conservan desde su creación. Archivar retira el campo de la operación y conserva sus datos para consultas autorizadas. Las opciones seleccionadas se guardan por ID y pueden desactivarse sin romper registros anteriores.

Los valores se validan en el servidor. Las nuevas altas exigen valores a los operadores que pueden editar el campo y los registros anteriores conservan el acceso cuando falta un valor. Un campo de inventario obligatorio necesita valor predeterminado porque compras y traslados pueden crear saldos automáticamente. En el formulario de asignaciones de inventario, los campos describen la solicitud y se guardan en cada línea generada. Los campos de productos nuevos se rellenan por línea en el formulario de compras. Los adjuntos se guardan en el disco privado, incluidas versiones históricas.

Las columnas y los campos personalizados se aplican en las vistas configurables y sus exportaciones CSV. Los PDF y reportes especializados existentes mantienen su formato. Las categorías se incorporan como sugerencias y la selección preexistente continúa disponible. El presente módulo no modifica saldos ni reconstruye historial previo.

## Prueba local

1. Usar un checkout de la rama, PHP con las extensiones del proyecto, Composer, Node y una base aislada. Ejecutar `composer install`, `npm ci` y `npm run build`.
2. Para la suite, usar `APP_ENV=testing` y una `APP_KEY` generada exclusivamente para pruebas. Ejecutar `php vendor/bin/phpunit`. La configuración de las pruebas fija SQLite en memoria. Para probar manualmente con MySQL, preparar una base vacía y separada, aplicar `php artisan migrate` y crear las cuentas de prueba con credenciales propias.
3. Promover una cuenta de prueba desde la consola. Probar un rol duplicado y uno sin permisos, y luego una denegación individual. Crear un campo, guardar un valor desde el formulario real y verificar su consulta, filtro, exportación, descarga y archivado usando otra cuenta de otra bodega.

## Despliegue posterior

1. Revisar la migración `2026_11_18_000002_create_system_configuration.php`. Ensayar sobre una copia representativa y medir los bloqueos de las dos ampliaciones de tablas existentes. Verificar que los roles y ámbitos de esa copia corresponden al catálogo. La nueva migración agrega columnas y tablas y deja todas las cuentas y roles existentes activos.
2. Respaldar base y almacenamiento. En una ventana autorizada, aplicar las migraciones pendientes con `php artisan migrate --force` y desplegar el código y assets verificados. Mantener los secretos en la configuración de infraestructura y conservar las reglas de protección de documentos del despliegue del PR #86.
3. Comparar accesos históricos antes de conceder permisos nuevos. Promover la primera cuenta mediante la consola y conceder permisos adicionales expresamente. Comprobar la integridad de los movimientos y el acceso a adjuntos en el entorno de ensayo antes de abrir el servicio.
4. Para revertir, regresar al artefacto anterior durante una pausa de escrituras, conservando las tablas, columnas, bitácora y adjuntos privados. Antes de reabrir el servicio con un artefacto que desconozca el nuevo control de acceso, verificar qué cuentas o permisos se cambiaron y conservar el bloqueo de cuentas desactivadas.
