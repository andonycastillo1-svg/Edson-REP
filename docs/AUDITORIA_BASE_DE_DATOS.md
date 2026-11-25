# Auditoría técnica de la base de datos de Edson-REP

**Alcance.** Código y migraciones desde `main` en `1c34b6d`, más la migración aditiva de permisos, relaciones, procesos y delegaciones de esta rama. Se desplegó el esquema anterior y después se aplicó la migración nueva en una base local MySQL 8.4 desechable. Se leyó `information_schema` para verificar tablas, índices y claves. El informe cubre el esquema versionado. El volumen de registros, el uso por integraciones externas y el catálogo efectivo de producción quedan por confirmar.

## Conclusiones y prioridades

| Prioridad | Hallazgo y evidencia | Riesgo y acción recomendada |
| --- | --- | --- |
| Alta | `sistema_campo_valores` y `sistema_campo_historial` enlazan un módulo y una clave genérica. `Modulos::DEFINICIONES` y `ValoresCampos` resuelven el destino. | Una baja fuera de la aplicación puede dejar claves o archivos sin destino. Revisar huérfanos con SELECT por módulo y preservar evidencia antes de considerar una reparación. |
| Alta | Hay distintos niveles de inventario. `inventarios` almacena saldo, `inventario_existencias` representa unidades y `inventario_lotes_operativos` conserva lotes. `InventarioStockService`, `InventarioLifecycleService` y `LotesInventario` los usan. | Una fusión puede perder estados de unidades o crear diferencias. Conciliar agregados por condición y bodega con transacciones y banderas activas antes de evaluar cambios. |
| Alta | `consolidado_inspeccion` impone unicidad de inspección y las relaciones nuevas preservan los enlaces con restricciones de borrado. El responsable validado al enviar se guarda en `consolidados.coordinador_id`. | Cambiar unicidad o sustituir responsables históricos puede duplicar trabajo o perder trazabilidad. Conservar las restricciones y revisar registros históricos por separado. |
| Alta | `compra_consolidado` identifica facturas relacionadas y `compra_detalle_consolidado` distribuye cantidades. `DistribucionCompras` trata vínculos anteriores sin distribución como conciliaciones pendientes. | Inferir cantidades a partir del vínculo histórico puede contar una factura varias veces. Conservar ambas tablas y conciliar con respaldo documental. |
| Media | `inspeccion_articulos.maximo` conserva un dato legado. `CatalogosController::articulo` escribe cero al crear la fila para cumplir el esquema. La captura valida enteros desde cero sin leer ese máximo. | Posible columna candidata a revisión. Verificar integraciones externas, exportaciones, restricciones y compatibilidad con restauraciones antes de proponer su retirada. |
| Media | `supervisor_id` en `inspecciones` conserva la identidad de quien registró la inspección. `proceso_nombre` conserva una copia del nombre al momento de la captura. | Renombrar o deducir el actor por su cargo puede cambiar la lectura de datos históricos. Documentar la semántica de ambas columnas y seguir consultando por actor y proceso. |
| Media | Varias claves históricas usan `SET NULL` al borrar el catálogo o usuario referenciado. Otras usan `CASCADE` para detalles que dependen de una cabecera. La lista precisa figura en el anexo. | Una eliminación directa puede retirar el contexto o evidencia vinculada. Preferir desactivar y revisar las dependencias antes de ejecutar bajas permitidas. |
| Media | Documentos de fotos, facturas y entregas guardan ruta y metadatos; el contenido reside en el almacenamiento de documentos privados. | Una restauración parcial deja la evidencia inaccesible. Validar restauraciones coordinadas de archivos y base de datos. |
| Baja | `job_batches` se crea desde el esqueleto Laravel. Hace falta confirmar su uso en colas, paquetes y despliegues efectivos. | Conservar la tabla hasta revisar la configuración y telemetría autorizada. |
| Baja | Algunas tablas históricas de vehículos y refacciones conservan controladores y modelos sujetos a `config('modulos.vehiculos')`. | Mantener sus datos y archivos por la opción de reactivación y el historial. |

El análisis detectó columnas y estructuras que ameritan revisión. La evidencia disponible no justifica clasificar ninguna tabla versionada como obsoleta.

## Inventario de tablas y evidencia de uso

La columna de evidencia identifica clases, controladores o configuraciones del repositorio. En relaciones implementadas mediante Query Builder se indica el consumidor directo.

| Tabla | Clasificación | Función y relación principal | Evidencia y recomendación |
| --- | --- | --- | --- |
| `alerta_rrhh_actuaciones` | Históricas | Notas y actuaciones de RR. HH. | AlertaRrhhActuacion y RrhhDashboardController |
| `alertas_reemplazos_rrhh` | En uso | Bandeja, revisión y resolución de RR. HH. | AlertaReemplazo, DetectarReemplazos, RrhhDashboardController |
| `almacenista_supervisores` | En uso | Custodia y relación del supervisor con su almacenista habitual | User, AccesoCustodia y UsuarioController. Mantener la asignación primaria histórica |
| `asignacion_estado_historiales` | Históricas | Estado anterior y nuevo, motivo y fecha de asignación | AsignacionEstadoHistorial, manuales de operación de custodias |
| `asignacion_inventario_archivos` | Históricas | PDF de entregas y devoluciones anteriores | AsignacionInventarioArchivo, AsignacionInventarioController |
| `asignacion_movimientos` | Históricas | Valores y cantidades de las devoluciones | AsignacionMovimiento y AsignacionInventarioController. Conserva hojas de devolución |
| `asignacion_periodos` | Históricas | Períodos de custodia con seguimiento unitario | AsignacionPeriodo, InventarioLifecycleService |
| `asignacion_vehiculo_archivos` | Históricas | Evidencia documental de vehículos | AsignacionVehiculoArchivo y AsignacionVehiculoController |
| `asignaciones_inventarios` | En uso | Custodia vigente, entregas, reemplazos y cierres | AsignacionInventario, RegistrarAsignaciones, AccesoCustodia |
| `asignaciones_vehiculos` | Históricas | Custodias de vehículos y fechas | AsignacionVehiculo, AsignacionVehiculoController |
| `bodega_usuario_operativa` | En uso | Bodegas adicionales para consulta | DelegacionesOperativas e InventarioAutorizadoController. Complementa la sede operativa principal |
| `bodegas` | En uso | Sedes de almacenaje y recepción | Bodega, BodegaController, compras, inventario y traslados |
| `cache` | En uso | Caché de Laravel cuando usa conexión de base de datos | config/cache.php |
| `cache_locks` | En uso | Bloqueos de caché y middleware de limitación | config/cache.php y throttle en routes/sistema.php |
| `colaboradores` | En uso | Identidad laboral, puesto y región del colaborador | Colaborador, ColaboradorController, InspeccionController, asignaciones |
| `compra_archivos` | Históricas | Documentos privados asociados a facturas | CompraController y DocumentoPrivado |
| `compra_consolidado` | En uso | Vínculos generales de facturas con requerimientos | ConsolidadoController, PlanCompras y DistribucionCompras. Preserva vínculos sin reparto histórico |
| `compra_detalle_consolidado` | En uso | Reparto verificado de cantidad por línea | DistribucionCompras, PlanCompras y exportación. Preserva cantidades por destino |
| `compra_detalles` | En uso | Líneas, cantidades y precios de factura | CompraController, DistribucionCompras y ExportacionesOperativas |
| `compras` | En uso | Facturas por proveedor y entradas autorizadas | CompraController, DistribucionCompras. Evitar cambiar unicidad de referencia |
| `consolidado_colaboradores` | En uso | Detalle de personas y región conservada en el requerimiento | ConsolidadoColaborador, DetalleConsolidado |
| `consolidado_constancia` | Históricas | Vínculo de la constancia con los requerimientos | ConstanciaGerencia, Consolidado, PlanCompras |
| `consolidado_eventos` | Históricas | Cambios del flujo con su autor, estado y versión | ConsolidadoEvento y ConsolidacionInspecciones. El máximo id protege contra ediciones simultáneas |
| `consolidado_inspeccion` | En uso | Vínculo único que evita procesar la misma inspección dos veces | Consolidado, ConsolidacionInspecciones y DetalleConsolidado |
| `consolidado_lineas` | En uso | Resumen por producto y origen para planes y compras | ConsolidadoLinea, PlanCompras, DistribucionCompras. Complementa detalle por colaborador |
| `consolidado_necesidades` | En uso | Cantidades originales y aprobadas por colaborador y producto | ConsolidadoNecesidad, DetalleConsolidado, RequerimientosAsignacion |
| `consolidado_planes_compra` | Históricas | Cantidades consideradas en autorizaciones históricas | PlanCompras y PlanComprasController. Complementa plan actual editable del consolidado |
| `consolidados` | En uso | Agrupaciones por responsable, estados y decisión de aprobación | Consolidado y ConsolidadoController. Origen de compras y entregas |
| `constancias_gerencia` | Históricas | Evidencia histórica de autorización de compra | ConstanciaGerencia y PlanComprasController. Conserva acceso y archivo |
| `corte_inspeccion` | Históricas | Selección de inspecciones y marcador de fecha anterior al corte | CorteRequerimiento y CortesRequerimientos. Un borrador no reserva las inspecciones |
| `cortes_requerimientos` | En uso | Período manual, operador, estado y selección de responsables | CorteRequerimiento, CortesRequerimientos |
| `delegacion_bodega` | En uso | Conjunto explícito de bodegas de la delegación | DelegacionOperativa y DelegacionesOperativas |
| `delegacion_region` | En uso | Conjunto explícito de regiones de la delegación | DelegacionOperativa y DelegacionesOperativas |
| `delegaciones_operativas` | En uso | Titular, sustituto, permisos, vigencia, autorización y revocación | DelegacionOperativa, DelegacionController, DelegacionesOperativas |
| `entrega_avisos` | En uso | Registro y prevención de avisos repetidos de entrega | NotificacionService y operación de entrega. Verificar jobs de reintento |
| `entregas_inventario` | En uso | Cabecera de entrega, recepción y evidencia | EntregaInventario, RegistrarAsignaciones y HojaAsignacion |
| `failed_jobs` | Históricas | Fallos registrados por la cola | config/queue.php. Conservar para recuperación y diagnóstico |
| `importaciones_asignacion` | En uso | Validación, confirmación, conciliación y huella de lotes Excel | ImportacionAsignacion, ImportarAsignacionController, ConciliarImportacionAsignaciones |
| `incidencias_inventario` | En uso | Incidencias y evidencia de equipo asignado | IncidenciaInventario, DetectarReemplazos, RrhhDashboardController |
| `inspeccion_articulos` | En uso | Configuración de artículo y vínculo al código de inventario | CatalogosController e InspeccionController. maximo se conserva por compatibilidad |
| `inspeccion_fotos` | Históricas | Metadatos de la evidencia privada de inspección | FotoInspeccion, InspeccionController, DocumentoPrivado |
| `inspeccion_lineas` | En uso | Cantidades originales por artículo | InspeccionLinea, InspeccionController, DetalleConsolidado |
| `inspeccion_secciones` | En uso | Agrupación de artículos del formulario | CatalogosController e InspeccionController. Conserva el orden operativo |
| `inspecciones` | En uso | Evidencia de solicitud, región, colaborador, actor, fecha y proceso | Inspeccion, InspeccionController y CortesRequerimientos. supervisor_id es el actor real |
| `inventario_existencias` | En uso | Control por unidad si se activa el seguimiento | InventarioExistencia, InventarioLifecycleService. Verificar bandera y adopción antes de cualquier cambio |
| `inventario_lotes_operativos` | En uso | Control de existencias agregadas por lote | InventarioLoteOperativo y LotesInventario. El saldo y los lotes sirven para fines distintos |
| `inventarios` | En uso | Saldo transaccional por bodega, producto y condición | Inventario, InventarioStockService, BodegaController y traslados |
| `job_batches` | Pendientes de análisis | Control de lotes de trabajos del framework | config/queue.php. Revisar si un despliegue procesa lotes o paquetes que los utilicen |
| `jobs` | En uso | Cola cuando se usa el controlador de base de datos | config/queue.php |
| `migrations` | En uso | Versiones ejecutadas del esquema | Repositorio de migraciones de Laravel. Nunca limpiar para retirar funcionalidades |
| `motivos_asignacion` | En uso | Motivos activos y políticas de reemplazo | MotivoAsignacion, CatalogosController y RegistrarAsignaciones |
| `movimientos` | Históricas | Entradas, salidas y traslados con su origen | Movimiento, InventarioStockService, OperacionTrasladoController |
| `notifications` | En uso | Avisos y estado leído por usuario | NotificacionController, NotificacionService, Laravel Notifiable |
| `operacion_detalles` | En uso | Líneas de productos y cantidades trasladadas | OperacionDetalle y OperacionTrasladoController |
| `operaciones` | En uso | Solicitud y resolución de traslados y archivo de operación | Operacion y OperacionTrasladoController |
| `password_reset_tokens` | En uso | Restablecimiento de credenciales | Auth/PasswordResetLinkController, Auth/NewPasswordController, config/auth.php |
| `procesos_inspeccion` | En uso | Catálogo editable del proceso operativo | ProcesoInspeccion, CatalogosController e InspeccionController |
| `productos` | En uso | Códigos, nombre, categoría y vida útil | Producto, CatalogosController, inventario, compras y asignaciones |
| `proveedores` | En uso | Datos de proveedores de facturas | CompraController mediante Query Builder. Compras dependen del catálogo |
| `region_coordinador_historial` | Históricas | Cambios de coordinador principal por región | RegionesCoordinacion, CoordinadorRegionController |
| `region_coordinador_operativo` | En uso | Coordinadores adicionales por región | Region y RegionesCoordinacion. Complementa la titularidad principal |
| `region_usuario_operativa` | En uso | Regiones habilitadas para varias cuentas y viceversa | User, FlujoAcceso y UsuarioController |
| `regiones` | En uso | Catálogo regional y coordinador principal vigente | Region, RegionesCoordinacion, CatalogosController |
| `roles` | En uso | Cargo y ámbito operativo base | Role, CatalogoPermisos y SeguridadController |
| `sessions` | En uso | Sesión y cierre por inactividad | config/session.php y AutoLogoutOnInactivity |
| `sistema_ajustes` | En uso | Configuración general y bloqueo de cambios de seguridad | Ajustes, Seguridad, ConfiguracionController |
| `sistema_auditoria` | Históricas | Acciones y diffs de seguridad y operación | Auditoria y ConfiguracionController. Revisar política de retención y acceso |
| `sistema_campo_historial` | Históricas | Cambios en campos y valores | ValoresCampos, CampoController. Conservar evidencia administrativa |
| `sistema_campo_opciones` | En uso | Opciones para campos de catálogo | OpcionCampo, CampoController |
| `sistema_campo_valores` | En uso | Valores y archivos de registros de módulos | ValoresCampos y DatosController. Dependencia polimórfica de registros |
| `sistema_campos` | En uso | Definiciones de campos administrables | Campo, CampoController, ValoresCampos |
| `sistema_permisos_rol` | En uso | Reglas del rol | ReglaRol, CatalogoPermisos, SeguridadController |
| `sistema_permisos_usuario` | En uso | Excepciones individuales sobre el rol | ReglaUsuario y Permisos. La bitácora conserva altas y retiros |
| `sistema_superadministradores` | En uso | Distinción de privilegio administrativo protegido | Permisos, SeguridadController. Perder la fila puede bloquear la administración |
| `solicitudes_inventario` | En uso | Clave de idempotencia de solicitudes y acciones de inventario | SolicitudInventarioService. Retirar datos puede habilitar reintentos repetidos |
| `users` | En uso | Cuenta, rol, responsable de alta, bodega y coordinador | User, UsuarioController, Seguridad, autenticación Breeze |
| `vehiculo_producto_archivos` | Históricas | Documentos privados de refacciones | VehiculoProductoArchivo y VehiculoProductoController |
| `vehiculo_producto_asignaciones` | Históricas | Accesorios o refacciones entregadas y devueltas | VehiculoProductoAsignacion y VehiculoProductoController |
| `vehiculos` | Históricas | Catálogo del módulo suspendido por configuración | Vehiculo y VehiculoController. Puede reactivarse |

## Anexo de claves e índices verificados en MySQL 8.4

79 tablas, 252 índices y 154 claves foráneas. PK identifica clave primaria, UQ índice único e IX índice ordinario. Las dos reglas al final de cada clave se aplican al eliminar y actualizar, en ese orden.

| Tabla | Índices por columnas | Claves foráneas |
| --- | --- | --- |
| `alerta_rrhh_actuaciones` | IX(alerta_id) · IX(usuario_id) · PK(id) | alerta_id → alertas_reemplazos_rrhh.id (RESTRICT/NO ACTION) · usuario_id → users.id (SET NULL/NO ACTION) |
| `alertas_reemplazos_rrhh` | IX(asignacion_anterior_id) · IX(asignacion_nueva_id) · IX(colaborador_codigo,producto_codigo) · IX(registrado_por_user_id) · IX(vehiculo_producto_asignacion_id) · IX(vehiculo_vin) · PK(id) · UQ(incidencia_inventario_id) | incidencia_inventario_id → incidencias_inventario.id (RESTRICT/NO ACTION) · asignacion_anterior_id → asignaciones_inventarios.id (SET NULL/NO ACTION) · asignacion_nueva_id → asignaciones_inventarios.id (SET NULL/NO ACTION) · registrado_por_user_id → users.id (SET NULL/NO ACTION) · vehiculo_producto_asignacion_id → vehiculo_producto_asignaciones.id (SET NULL/NO ACTION) · vehiculo_vin → vehiculos.vin (SET NULL/CASCADE) |
| `almacenista_supervisores` | PK(id) · UQ(almacenista_id,supervisor_id) · UQ(supervisor_id) | almacenista_id → users.id (CASCADE/NO ACTION) · supervisor_id → users.id (CASCADE/NO ACTION) |
| `asignacion_estado_historiales` | IX(asignacion_inventario_id,estado) · IX(user_id) · PK(id) | asignacion_inventario_id → asignaciones_inventarios.id (CASCADE/NO ACTION) · user_id → users.id (SET NULL/NO ACTION) |
| `asignacion_inventario_archivos` | IX(asignacion_inventario_id,tipo_documento) · IX(grupo_devolucion,tipo_documento) · IX(grupo_entrega) · IX(subido_por_user_id) · PK(id) | grupo_entrega → entregas_inventario.id (RESTRICT/NO ACTION) · asignacion_inventario_id → asignaciones_inventarios.id (CASCADE/NO ACTION) · subido_por_user_id → users.id (SET NULL/NO ACTION) |
| `asignacion_movimientos` | IX(asignacion_inventario_id) · IX(bodega_retorno_id) · IX(estado_devolucion) · IX(grupo_devolucion) · IX(user_id) · PK(id) | asignacion_inventario_id → asignaciones_inventarios.id (CASCADE/NO ACTION) · bodega_retorno_id → bodegas.id (SET NULL/NO ACTION) · user_id → users.id (SET NULL/NO ACTION) |
| `asignacion_periodos` | IX(asignacion_inventario_id,devuelto_en) · IX(asignado_por_user_id) · IX(bodega_origen_id) · IX(bodega_retorno_id) · IX(devuelto_por_user_id) · PK(id) · UQ(inventario_existencia_id,asignado_en) | asignacion_inventario_id → asignaciones_inventarios.id (RESTRICT/NO ACTION) · inventario_existencia_id → inventario_existencias.id (RESTRICT/NO ACTION) · asignado_por_user_id → users.id (SET NULL/NO ACTION) · bodega_origen_id → bodegas.id (RESTRICT/NO ACTION) · bodega_retorno_id → bodegas.id (SET NULL/NO ACTION) · devuelto_por_user_id → users.id (SET NULL/NO ACTION) |
| `asignacion_vehiculo_archivos` | IX(asignacion_vehiculo_id,tipo_documento) · IX(subido_por_user_id) · PK(id) | asignacion_vehiculo_id → asignaciones_vehiculos.id (CASCADE/NO ACTION) · subido_por_user_id → users.id (SET NULL/NO ACTION) |
| `asignaciones_inventarios` | IX(asignacion_anterior_id) · IX(bodega_id) · IX(colaborador_codigo,producto_codigo,estado) · IX(grupo_asignacion) · IX(lote_operativo_id) · IX(motivo_asignacion_id) · IX(producto_codigo) · IX(requerimiento_id) · IX(sustituida_por_id) · IX(tipo_entrega) · IX(user_id) · PK(id) | asignacion_anterior_id → asignaciones_inventarios.id (SET NULL/NO ACTION) · lote_operativo_id → inventario_lotes_operativos.id (RESTRICT/NO ACTION) · sustituida_por_id → asignaciones_inventarios.id (RESTRICT/NO ACTION) · bodega_id → bodegas.id (RESTRICT/CASCADE) · colaborador_codigo → colaboradores.codigo (RESTRICT/CASCADE) · motivo_asignacion_id → motivos_asignacion.id (RESTRICT/NO ACTION) · producto_codigo → productos.codigo (RESTRICT/CASCADE) · requerimiento_id → consolidado_necesidades.id (RESTRICT/NO ACTION) · user_id → users.id (SET NULL/NO ACTION) |
| `asignaciones_vehiculos` | IX(asignado_por_user_id) · IX(colaborador_codigo,activa) · IX(desasignado_por_user_id) · IX(vehiculo_vin,activa) · PK(id) | asignado_por_user_id → users.id (SET NULL/NO ACTION) · colaborador_codigo → colaboradores.codigo (RESTRICT/CASCADE) · desasignado_por_user_id → users.id (SET NULL/NO ACTION) · vehiculo_vin → vehiculos.vin (RESTRICT/CASCADE) |
| `bodega_usuario_operativa` | IX(user_id) · PK(bodega_id,user_id) | bodega_id → bodegas.id (RESTRICT/NO ACTION) · user_id → users.id (RESTRICT/NO ACTION) |
| `bodegas` | PK(id) | — |
| `cache` | IX(expiration) · PK(key) | — |
| `cache_locks` | IX(expiration) · PK(key) | — |
| `colaboradores` | IX(region_id) · PK(codigo) | region_id → regiones.id (RESTRICT/NO ACTION) |
| `compra_archivos` | IX(compra_id) · PK(id) | compra_id → compras.id (CASCADE/NO ACTION) |
| `compra_consolidado` | IX(consolidado_id) · PK(compra_id,consolidado_id) | compra_id → compras.id (RESTRICT/NO ACTION) · consolidado_id → consolidados.id (RESTRICT/NO ACTION) |
| `compra_detalle_consolidado` | IX(compra_detalle_id) · PK(consolidado_linea_id,compra_detalle_id) | compra_detalle_id → compra_detalles.id (RESTRICT/NO ACTION) · consolidado_linea_id → consolidado_lineas.id (RESTRICT/NO ACTION) |
| `compra_detalles` | IX(producto_codigo) · PK(id) · UQ(compra_id,producto_codigo) | compra_id → compras.id (CASCADE/NO ACTION) · producto_codigo → productos.codigo (NO ACTION/NO ACTION) |
| `compras` | PK(id) · UQ(proveedor_id,no_factura) | proveedor_id → proveedores.id (NO ACTION/NO ACTION) |
| `consolidado_colaboradores` | IX(agregado_por) · IX(colaborador_codigo) · IX(region_id) · PK(id) · UQ(consolidado_id,colaborador_codigo) | agregado_por → users.id (RESTRICT/NO ACTION) · consolidado_id → consolidados.id (RESTRICT/NO ACTION) · region_id → regiones.id (RESTRICT/NO ACTION) · colaborador_codigo → colaboradores.codigo (RESTRICT/CASCADE) |
| `consolidado_constancia` | IX(constancia_id) · PK(consolidado_id,constancia_id) | consolidado_id → consolidados.id (RESTRICT/NO ACTION) · constancia_id → constancias_gerencia.id (RESTRICT/NO ACTION) |
| `consolidado_eventos` | IX(consolidado_id) · IX(user_id) · PK(id) | consolidado_id → consolidados.id (RESTRICT/NO ACTION) · user_id → users.id (RESTRICT/NO ACTION) |
| `consolidado_inspeccion` | IX(consolidado_id) · UQ(inspeccion_id) | consolidado_id → consolidados.id (RESTRICT/NO ACTION) · inspeccion_id → inspecciones.id (RESTRICT/NO ACTION) |
| `consolidado_lineas` | IX(producto_codigo) · PK(id) · UQ(consolidado_id,producto_codigo) | consolidado_id → consolidados.id (RESTRICT/NO ACTION) · producto_codigo → productos.codigo (RESTRICT/CASCADE) |
| `consolidado_necesidades` | IX(producto_codigo) · PK(id) · UQ(consolidado_colaborador_id,producto_codigo) | consolidado_colaborador_id → consolidado_colaboradores.id (RESTRICT/NO ACTION) · producto_codigo → productos.codigo (RESTRICT/CASCADE) |
| `consolidado_planes_compra` | IX(constancia_id) · PK(consolidado_linea_id,constancia_id) | consolidado_linea_id → consolidado_lineas.id (RESTRICT/NO ACTION) · constancia_id → constancias_gerencia.id (RESTRICT/NO ACTION) |
| `consolidados` | IX(aprobado_por) · IX(coordinador_id) · IX(corte_id) · IX(estado) · IX(operador_id) · PK(id) | aprobado_por → users.id (RESTRICT/NO ACTION) · coordinador_id → users.id (RESTRICT/NO ACTION) · corte_id → cortes_requerimientos.id (RESTRICT/NO ACTION) · operador_id → users.id (RESTRICT/NO ACTION) |
| `constancias_gerencia` | IX(user_id) · PK(id) | user_id → users.id (RESTRICT/NO ACTION) |
| `corte_inspeccion` | IX(inspeccion_id) · PK(corte_id,inspeccion_id) | corte_id → cortes_requerimientos.id (RESTRICT/NO ACTION) · inspeccion_id → inspecciones.id (RESTRICT/NO ACTION) |
| `cortes_requerimientos` | IX(estado,operador_id) · IX(operador_id) · PK(id) | operador_id → users.id (RESTRICT/NO ACTION) |
| `delegacion_bodega` | IX(bodega_id) · PK(delegacion_id,bodega_id) | bodega_id → bodegas.id (RESTRICT/NO ACTION) · delegacion_id → delegaciones_operativas.id (RESTRICT/NO ACTION) |
| `delegacion_region` | IX(region_id) · PK(delegacion_id,region_id) | delegacion_id → delegaciones_operativas.id (RESTRICT/NO ACTION) · region_id → regiones.id (RESTRICT/NO ACTION) |
| `delegaciones_operativas` | IX(autorizada_por) · IX(revocada_por) · IX(sustituto_id,revocada_en,vigente_desde,vigente_hasta) · IX(titular_id,vigente_hasta) · PK(id) | autorizada_por → users.id (RESTRICT/NO ACTION) · revocada_por → users.id (RESTRICT/NO ACTION) · sustituto_id → users.id (RESTRICT/NO ACTION) · titular_id → users.id (RESTRICT/NO ACTION) |
| `entrega_avisos` | IX(user_id) · PK(entrega_id,user_id) | entrega_id → entregas_inventario.id (RESTRICT/NO ACTION) · user_id → users.id (RESTRICT/NO ACTION) |
| `entregas_inventario` | IX(confirmada_por) · IX(salida_en) · PK(id) | confirmada_por → users.id (SET NULL/NO ACTION) |
| `failed_jobs` | PK(id) · UQ(uuid) | — |
| `importaciones_asignacion` | IX(bodega_id) · IX(consolidado_id) · IX(user_id) · PK(id) · UQ(huella_confirmada) · UQ(huella_operativa) | bodega_id → bodegas.id (RESTRICT/NO ACTION) · consolidado_id → consolidados.id (RESTRICT/NO ACTION) · user_id → users.id (RESTRICT/NO ACTION) |
| `incidencias_inventario` | IX(archivo_subido_por) · IX(asignacion_id) · IX(reemplazo_id) · IX(registrado_por) · PK(id) | archivo_subido_por → users.id (SET NULL/NO ACTION) · asignacion_id → asignaciones_inventarios.id (RESTRICT/NO ACTION) · reemplazo_id → asignaciones_inventarios.id (SET NULL/NO ACTION) · registrado_por → users.id (SET NULL/NO ACTION) |
| `inspeccion_articulos` | IX(seccion) · PK(id) · UQ(producto_codigo) | producto_codigo → productos.codigo (RESTRICT/CASCADE) · seccion → inspeccion_secciones.clave (RESTRICT/NO ACTION) |
| `inspeccion_fotos` | IX(inspeccion_id) · PK(id) | inspeccion_id → inspecciones.id (RESTRICT/NO ACTION) |
| `inspeccion_lineas` | IX(producto_codigo) · PK(id) · UQ(inspeccion_id,producto_codigo) | inspeccion_id → inspecciones.id (RESTRICT/NO ACTION) · producto_codigo → productos.codigo (RESTRICT/CASCADE) |
| `inspeccion_secciones` | PK(clave) | — |
| `inspecciones` | IX(colaborador_codigo) · IX(coordinador_id) · IX(fecha) · IX(proceso_id,fecha) · IX(region_id) · PK(id) · UQ(supervisor_id,solicitud_id) | colaborador_codigo → colaboradores.codigo (RESTRICT/CASCADE) · coordinador_id → users.id (RESTRICT/NO ACTION) · proceso_id → procesos_inspeccion.id (RESTRICT/NO ACTION) · region_id → regiones.id (RESTRICT/NO ACTION) · supervisor_id → users.id (RESTRICT/NO ACTION) |
| `inventario_existencias` | IX(bodega_id) · IX(creado_por_user_id) · IX(producto_codigo,bodega_id,condicion) · IX(producto_codigo,vida_util_restante_segundos) · IX(vehiculo_producto_asignacion_id) · PK(id) | vehiculo_producto_asignacion_id → vehiculo_producto_asignaciones.id (SET NULL/NO ACTION) · bodega_id → bodegas.id (SET NULL/NO ACTION) · creado_por_user_id → users.id (SET NULL/NO ACTION) · producto_codigo → productos.codigo (RESTRICT/CASCADE) |
| `inventario_lotes_operativos` | IX(bodega_id,producto_codigo,stock_tipo) · IX(producto_codigo) · IX(retornado_desde_id) · PK(id) | bodega_id → bodegas.id (CASCADE/NO ACTION) · retornado_desde_id → asignaciones_inventarios.id (RESTRICT/NO ACTION) · producto_codigo → productos.codigo (RESTRICT/CASCADE) |
| `inventarios` | IX(bodega_id) · IX(stock_tipo) · PK(id) · UQ(producto_codigo,bodega_id,stock_tipo) | bodega_id → bodegas.id (RESTRICT/CASCADE) · producto_codigo → productos.codigo (RESTRICT/CASCADE) |
| `job_batches` | PK(id) | — |
| `jobs` | IX(queue) · PK(id) | — |
| `migrations` | PK(id) | — |
| `motivos_asignacion` | PK(id) · UQ(codigo) | — |
| `movimientos` | IX(bodega_destino_id) · IX(bodega_origen_id) · IX(producto_codigo) · IX(user_id) · IX(vehiculo_vin) · PK(id) | bodega_destino_id → bodegas.id (SET NULL/NO ACTION) · bodega_origen_id → bodegas.id (SET NULL/NO ACTION) · producto_codigo → productos.codigo (RESTRICT/CASCADE) · user_id → users.id (SET NULL/NO ACTION) · vehiculo_vin → vehiculos.vin (SET NULL/CASCADE) |
| `notifications` | IX(notifiable_type,notifiable_id) · PK(id) | — |
| `operacion_detalles` | IX(operacion_id) · IX(producto_codigo) · PK(id) | operacion_id → operaciones.id (CASCADE/NO ACTION) · producto_codigo → productos.codigo (RESTRICT/CASCADE) |
| `operaciones` | IX(aprobado_por) · IX(bodega_destino_id,estado) · IX(bodega_origen_id) · IX(creado_por) · IX(rechazado_por) · IX(tipo,estado) · PK(id) | aprobado_por → users.id (NO ACTION/NO ACTION) · bodega_destino_id → bodegas.id (NO ACTION/NO ACTION) · bodega_origen_id → bodegas.id (NO ACTION/NO ACTION) · creado_por → users.id (NO ACTION/NO ACTION) · rechazado_por → users.id (NO ACTION/NO ACTION) |
| `password_reset_tokens` | PK(email) | — |
| `procesos_inspeccion` | PK(id) · UQ(nombre) | — |
| `productos` | PK(codigo) | — |
| `proveedores` | PK(id) · UQ(nombre) | — |
| `region_coordinador_historial` | IX(anterior_id) · IX(coordinador_id) · IX(region_id) · IX(user_id) · PK(id) | anterior_id → users.id (RESTRICT/NO ACTION) · coordinador_id → users.id (RESTRICT/NO ACTION) · region_id → regiones.id (RESTRICT/NO ACTION) · user_id → users.id (RESTRICT/NO ACTION) |
| `region_coordinador_operativo` | IX(user_id) · PK(region_id,user_id) | region_id → regiones.id (RESTRICT/NO ACTION) · user_id → users.id (RESTRICT/NO ACTION) |
| `region_usuario_operativa` | IX(region_id) · PK(user_id,region_id) | region_id → regiones.id (RESTRICT/NO ACTION) · user_id → users.id (RESTRICT/NO ACTION) |
| `regiones` | IX(coordinador_id) · PK(id) · UQ(nombre) | coordinador_id → users.id (RESTRICT/NO ACTION) |
| `roles` | PK(id) · UQ(nombre) | — |
| `sessions` | IX(last_activity) · IX(user_id) · PK(id) | — |
| `sistema_ajustes` | PK(clave) | — |
| `sistema_auditoria` | IX(actor_id,created_at) · IX(created_at,id) · IX(modulo,accion,created_at) · PK(id) | actor_id → users.id (SET NULL/NO ACTION) |
| `sistema_campo_historial` | IX(actor_id) · IX(campo_id,registro) · PK(id) | actor_id → users.id (SET NULL/NO ACTION) · campo_id → sistema_campos.id (RESTRICT/NO ACTION) |
| `sistema_campo_opciones` | IX(campo_id) · PK(id) | campo_id → sistema_campos.id (RESTRICT/NO ACTION) |
| `sistema_campo_valores` | IX(campo_id,busqueda) · PK(id) · UQ(campo_id,registro) | campo_id → sistema_campos.id (RESTRICT/NO ACTION) |
| `sistema_campos` | PK(id) · UQ(modulo,clave) | — |
| `sistema_permisos_rol` | PK(role_id,permiso) | role_id → roles.id (CASCADE/NO ACTION) |
| `sistema_permisos_usuario` | PK(user_id,permiso) | user_id → users.id (CASCADE/NO ACTION) |
| `sistema_superadministradores` | PK(user_id) | user_id → users.id (RESTRICT/NO ACTION) |
| `solicitudes_inventario` | PK(user_id,operacion,clave) | user_id → users.id (CASCADE/NO ACTION) |
| `users` | IX(bodega_id) · IX(coordinador_id) · IX(created_by) · IX(role_id) · PK(id) · UQ(email) | bodega_id → bodegas.id (SET NULL/NO ACTION) · coordinador_id → users.id (SET NULL/NO ACTION) · created_by → users.id (SET NULL/NO ACTION) · role_id → roles.id (SET NULL/NO ACTION) |
| `vehiculo_producto_archivos` | IX(subido_por) · IX(tipo_documento) · IX(vehiculo_producto_asignacion_id) · PK(id) | vehiculo_producto_asignacion_id → vehiculo_producto_asignaciones.id (CASCADE/NO ACTION) · subido_por → users.id (SET NULL/NO ACTION) |
| `vehiculo_producto_asignaciones` | IX(asignacion_vehiculo_id) · IX(asignado_por_user_id) · IX(bodega_id) · IX(cerrado_por_user_id) · IX(colaborador_responsable_codigo) · IX(producto_codigo) · IX(vehiculo_vin,activa) · IX(vehiculo_vin,estado) · PK(id) | asignacion_vehiculo_id → asignaciones_vehiculos.id (CASCADE/NO ACTION) · asignado_por_user_id → users.id (SET NULL/NO ACTION) · bodega_id → bodegas.id (RESTRICT/NO ACTION) · cerrado_por_user_id → users.id (SET NULL/NO ACTION) · producto_codigo → productos.codigo (RESTRICT/CASCADE) · vehiculo_vin → vehiculos.vin (RESTRICT/CASCADE) · colaborador_responsable_codigo → colaboradores.codigo (SET NULL/CASCADE) |
| `vehiculos` | PK(vin) · UQ(placa) | — |
