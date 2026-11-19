# Guía de administración y consulta de Edson-REP

Grupo NetSolutions

Desde el panel principal o el menú de tu cuenta, abre **Configuración del sistema** para administrar accesos y ajustes. Abre **Datos de los módulos** para consultar información y generar reportes. El sistema muestra los apartados que tu cuenta está autorizada a usar.

Cuando guardes un cambio, espera el mensaje de confirmación. Si aparece una validación, corrige el dato indicado y vuelve a guardar. Por seguridad, vuelve a escribir las contraseñas o adjuntar los archivos que deban enviarse de nuevo.

## 1. Roles y permisos

Un rol reúne los permisos generales de varias cuentas. El **Superadministrador** puede crear, duplicar y editar roles.

1. En **Configuración del sistema → Roles y permisos → Roles**, selecciona el rol que quieres revisar.
2. Abre la tarjeta del módulo o usa **Buscar permisos**. Puedes filtrar por acceso guardado y expandir o contraer los módulos.
3. En cada tarjeta, elige **Heredar** para usar el acceso base del rol, **Permitir** para conceder el permiso o **Denegar** para restringirlo. **Acceso efectivo** muestra lo que está guardado. **Sin guardar** identifica una selección pendiente.
4. Selecciona **Ir a guardar**, escribe tu contraseña actual y pulsa **Guardar rol**.

Para crear un rol, abre **Crear o duplicar un rol**, selecciona el ámbito y, si necesitas un punto de partida, copia un rol del mismo ámbito. Un rol nuevo creado sin copia empieza sin permisos.

**Ejemplo.** En las tarjetas de **Compras**, deja **Consultar compras** en Permitir y **Registrar compras** en Denegar para un rol de consulta.

**Efecto y precaución.** Un cambio en el rol afecta a las cuentas asociadas que heredan ese permiso. Revisa **Cuentas asociadas** antes de guardar. Las restricciones de ámbito y bodega siguen aplicándose. Desactivar un rol suspende el acceso operativo de las cuentas asociadas.

## 2. Cuentas y permisos individuales

Las excepciones individuales permiten ajustar el acceso de una sola cuenta. Solo el **Superadministrador** puede administrarlas desde este apartado.

1. Abre **Roles y permisos → Cuentas y excepciones**. Busca por nombre o correo y selecciona la cuenta.
2. En **Información general**, revisa el estado, el nombre, el correo y el rol. Para el ámbito Operador se solicita una bodega. Para Supervisor se solicita un almacenista.
3. En **Permisos**, localiza la autorización. **Del rol** muestra el acceso del rol y **Acceso efectivo** muestra el acceso guardado de esa cuenta. Elige **Heredar**, **Permitir** o **Denegar**.
4. Selecciona **Ir a guardar**, escribe tu contraseña actual y pulsa **Guardar permisos**.
5. En **Seguridad**, despliega **Cambiar contraseña** o **Estado de la cuenta**. Completa los datos, escribe tu propia contraseña actual y confirma la operación.

**Ejemplo.** Si una persona ya no registra devoluciones, selecciona **Denegar** en **Registrar devoluciones**. Cuando deba volver al acceso habitual, selecciona **Heredar**.

**Efecto y precaución.** La excepción prevalece sobre el rol dentro del ámbito de la cuenta. Cambiar el rol elimina sus excepciones anteriores. Desactivar la cuenta suspende el acceso y conserva los registros. Guarda o comunica una contraseña nueva por los medios autorizados por la empresa.

## 3. Superadministrador

Es un privilegio separado del rol Administrador. Permite administrar cuentas, roles, excepciones y configuraciones protegidas. Un **Superadministrador activo** puede gestionar este privilegio.

1. En **Cuentas y excepciones**, abre una cuenta administrativa activa.
2. Ve a **Seguridad → Acceso de Superadministrador**.
3. Escribe tu contraseña actual, pulsa **Convertir en Superadministrador** y confirma.
4. Para retirar el privilegio, usa **Revocar acceso de Superadministrador** en el mismo apartado.

**Ejemplo.** Habilita a una segunda persona responsable de la administración para que pueda gestionar los accesos durante una ausencia autorizada.

**Efecto y precaución.** La promoción concede acceso a funciones protegidas del sistema. Concede el privilegio únicamente a las personas responsables de administrarlas. Debe permanecer un Superadministrador activo. Revoca primero el privilegio si vas a desactivar esa cuenta o trasladarla a otro ámbito.

## 4. Campos personalizados

Sirven para agregar datos a los formularios y consultas de un módulo. Pueden administrarlos las cuentas autorizadas para **Campos personalizados**. Las reglas adicionales de lectura y edición las cambia el **Superadministrador**.

1. Abre **Campos personalizados → Nuevo campo**. Elige el módulo, escribe el nombre y selecciona el tipo de dato.
2. Para una **Lista desplegable** o **Selección múltiple**, usa **Agregar opción** y escribe cada nombre. Si quieres sugerir una respuesta inicial, elige el valor predeterminado. En una selección múltiple puedes marcar varias opciones.
3. En **Dónde aparece el campo**, marca **Mostrar en formularios**, **Mostrar en tabla**, **Habilitar filtro** o **Incluir al exportar**, según el uso previsto. Para exigir un valor en registros nuevos, marca **Obligatorio en registros nuevos** y deja **Mostrar en formularios** activo.
4. Pulsa **Guardar campo**. Vuelve a abrirlo para editar la etiqueta, las opciones o su visibilidad. Usa **Opciones avanzadas** si necesitas ajustar la posición. El sistema genera el identificador a partir del nombre cuando se deja en blanco.
5. Si el campo está temporalmente fuera de uso, desmarca **Activo**. Para retirarlo de forma permanente de la operación, usa **Archivar campo** y confirma.

**Ejemplo.** Crea **Zona de entrega** en Bodegas como lista con las opciones Norte y Sur. Actívalo en formularios, tabla y filtro para registrar la zona y buscar bodegas por ella.

**Efecto y precaución.** Los registros anteriores conservan sus valores cuando un campo se desactiva o archiva. Una opción inactiva deja de ofrecerse para registros nuevos. El módulo y el tipo se conservan después del alta. Un campo obligatorio de Inventario necesita un valor predeterminado, porque algunas operaciones crean saldos automáticamente. Revisa el formulario real y la consulta del módulo antes de exigir un campo nuevo.

## 5. Personalización de formularios y tablas

Define la vista inicial de la consulta y los campos opcionales de los formularios del módulo. Pueden usarla las cuentas autorizadas para **Formularios y tablas**.

1. Abre **Configuración del sistema → Formularios y tablas** o **Datos de los módulos → Configurar presentación**.
2. Selecciona el módulo y pulsa **Cambiar módulo**.
3. Cambia la **Etiqueta pública**, marca **Mostrar** en las columnas opcionales y activa **Permitir filtro** o **Incluir en búsqueda** donde corresponda. Las columnas esenciales indican **Siempre visible**.
4. Usa las flechas junto a cada columna para reordenarla. En **Vista inicial de la consulta**, elige cuántos registros se muestran por página y el orden inicial.
5. Si el módulo ofrece **Campos del formulario**, despliega ese apartado para ajustar la etiqueta, la ayuda, el orden, la visibilidad y la obligatoriedad de sus campos disponibles.
6. Pulsa **Guardar presentación**. Para recuperar la configuración original, usa **Restaurar valores predeterminados** y confirma.

**Ejemplo.** En Bodegas, mueve **Nombre** a la primera posición, habilita el filtro de **Tipo** y configura 20 registros por página.

**Efecto y precaución.** Los cambios se aplican a todas las cuentas del módulo. La restauración recupera la vista original de la tabla y del formulario. Cambiar etiquetas o columnas modifica la presentación de la información, mientras los registros conservan sus datos. Coordina los ajustes que usa más de un equipo.

## 6. Parámetros generales

Permiten ajustar la identidad, las sugerencias de inventario y la duración de las sesiones. Las cuentas autorizadas gestionan los parámetros generales. **Bodega principal y seguridad de sesión** requiere un **Superadministrador**.

1. En **Parámetros generales**, revisa **Identidad de la empresa** para cambiar los nombres o el logotipo.
2. En **Inventario y compras**, ajusta el nivel mínimo de aviso, la vida útil inicial de los productos nuevos y las categorías sugeridas. Escribe una categoría por línea.
3. Pulsa **Guardar parámetros generales**. Para la bodega principal o la duración de sesión, abre **Bodega principal y seguridad de sesión**, escribe tu contraseña actual y pulsa **Guardar parámetros protegidos**.

**Ejemplo.** Agrega la categoría **Herramientas manuales** para que aparezca como sugerencia en futuras compras.

**Efecto y precaución.** El nivel mínimo puede cambiar cuándo se emite un aviso de inventario bajo. La vida útil inicial se aplica a productos nuevos cuando no se indica otra duración. La bodega principal recibe entradas de compras y su cambio se bloquea si existe historial operativo. Una duración de sesión menor afecta a todas las cuentas. Usa el logotipo institucional autorizado.

## 7. Notificaciones

Permiten elegir qué avisos operativos se envían y a quién. Pueden gestionarlas las cuentas autorizadas para **Notificaciones**.

1. Abre **Notificaciones**, selecciona un **Evento** y pulsa **Abrir evento o buscar cuentas**.
2. Revisa el estado guardado **Envío activo** o **Envío desactivado**. Marca o desmarca **Enviar aviso de este evento**.
3. Marca los **Roles destinatarios** o las **Cuentas específicas**. Busca por nombre si necesitas localizar una cuenta. Guarda antes de cambiar de evento o de iniciar otra búsqueda.
4. Pulsa **Guardar regla** y vuelve al evento para comprobar la selección.

**Ejemplo.** En **Entradas de inventario y compras**, selecciona el rol Operador para dirigir el aviso a los operadores que ya tienen acceso a la bodega correspondiente.

**Efecto y precaución.** La selección limita la entrega a personas que ya son elegibles por sus permisos y bodega. Si dejas ambas listas vacías, el módulo usa sus destinatarios habituales. Los avisos obligatorios de seguridad, supervisión y revisión de Recursos Humanos mantienen sus reglas. La bitácora sigue registrando actividad.

## 8. Bitácora

La bitácora permite revisar quién realizó un cambio, cuándo y cuál fue su resultado. El **Superadministrador** también puede consultar los eventos de seguridad. Un administrador con autorización de bitácora puede consultar la actividad operativa y de configuración. Para los demás ámbitos autorizados, la consulta se limita a su propia actividad.

1. Abre **Configuración del sistema → Bitácora**.
2. Escribe una palabra en **Buscar actividad**, por ejemplo el nombre de la persona, el módulo o la acción. Puedes combinar el texto con **Usuario**, **Módulo**, **Acción** y las fechas **Desde** y **Hasta**.
3. Pulsa **Aplicar filtros**. Revisa la fecha, la persona, la actividad y el resultado.
4. Cuando esté disponible, abre **Ver cambios** para consultar el resumen anterior y posterior.

**Ejemplo.** Busca **parámetros** y selecciona el usuario y la fecha para comprobar quién guardó un cambio de configuración.

**Efecto y precaución.** Los filtros cambian solo los resultados que ves. La bitácora conserva la actividad y ofrece un resumen de los cambios disponibles. Usa únicamente los registros autorizados para el seguimiento administrativo.

## 9. Datos de los módulos

Es el centro de consulta y reportes. Cada cuenta puede abrir los módulos y registros que tiene autorizados. **Configurar presentación** aparece para quienes tienen esa autorización adicional.

1. Desde el panel, abre **Datos de los módulos → Consultar información**.
2. Selecciona un módulo en la lista o en sus tarjetas.
3. Pulsa **Ver registro** para consultar una ficha. Allí aparecen los datos visibles y, cuando están disponibles, los campos personalizados y su historial.
4. Pulsa **Volver** para recuperar la consulta. Para cambiar la presentación de todas las cuentas, abre **Configurar presentación** si está disponible.

**Ejemplo.** Abre Bodegas y consulta la ficha de la sucursal que tienes asignada para revisar su ubicación y sus campos adicionales.

**Efecto y precaución.** Este apartado es de solo lectura. Si necesitas editar campos personalizados, abre **Editar campos operativos** desde el módulo correspondiente y verifica que tu cuenta tenga permiso. Las fichas y adjuntos conservan el alcance de acceso del módulo.

## 10. Consultas, filtros y exportaciones

Estas herramientas sirven para encontrar registros y preparar reportes. El usuario puede buscar y ordenar los registros autorizados. La exportación y la descarga de adjuntos personalizados requieren sus permisos correspondientes.

1. Escribe en **Buscar registros** cuando el módulo tenga búsqueda habilitada. Abre **Filtros del módulo** para elegir criterios disponibles y pulsa **Aplicar consulta**.
2. Ordena con **Ordenar por** y **Sentido**, o pulsa el encabezado de una columna que muestra flechas. La flecha indica el sentido del orden aplicado. Usa **Restablecer consulta** para recuperar la vista inicial.
3. Si aparece **Exportar resultados (CSV)**, púlsalo para descargar los resultados autorizados de esa consulta. El archivo conserva los filtros y el orden elegidos.
4. Si el resultado ocupa varias páginas, usa la paginación para recorrerlo.

**Ejemplo.** Filtra las bodegas de tipo Regional, ordena por Nombre y descarga el CSV si tu cuenta tiene autorización.

**Efecto y precaución.** El orden elegido en la consulta es temporal. Los campos personalizados marcados para exportación pueden formar parte del CSV según el acceso de tu cuenta. Revisa los filtros antes de compartir el archivo y limita su distribución a las personas autorizadas.
