# Rutas

Fuente: `routes/web.php` y `routes/auth.php`.

## Generales

| Método | URL | Nombre | Controlador / acción |
|---|---|---|---|
| GET | `/` | — | Closure: redirige a `dashboard` o `login` |
| GET | `/dashboard` | `dashboard` | Closure: redirige por `role_id` |
| GET | `/coordinador/dashboard` | `coordinador.dashboard` | View `coordinador.dashboard` |
| GET | `/profile` | `profile.edit` | `ProfileController@edit` |
| PATCH | `/profile` | `profile.update` | `ProfileController@update` |
| DELETE | `/profile` | `profile.destroy` | `ProfileController@destroy` |

## Admin (`/admin`, `role:1`)

| Método | URL | Nombre | Controlador / acción |
|---|---|---|---|
| GET | `/admin/dashboard` | `admin.dashboard` | View `admin.dashboard` |
| Recurso | `/admin/usuarios` | `admin.usuarios.*` | `UsuarioController` (`index`, `create`, `store`, `edit`, `update`, `destroy`; `show` pendiente de confirmar) |
| Recurso | `/admin/bodegas` | `admin.bodegas.*` | `BodegaController` |
| GET | `/admin/bodegas/{bodega}/inventario-export` | `admin.bodegas.inventario.export` | `BodegaController@exportInventario` |
| GET | `/admin/bodegas/{bodega}/entrada` | `admin.bodegas.entrada` | `InventarioController@createEntrada` |
| POST | `/admin/bodegas/{bodega}/entrada` | `admin.bodegas.entrada.store` | `InventarioController@storeEntrada` |
| GET | `/admin/colaboradores/{colaborador}/detalle` | `admin.colaboradores.detalle` | `ColaboradorController@detalle` |
| GET | `/admin/colaboradores/{colaborador}/ficha-tecnica` | `admin.colaboradores.ficha_tecnica` | `ColaboradorController@fichaTecnica` |
| POST | `/admin/colaboradores/fichas-tecnicas-masivas` | `admin.colaboradores.fichas_tecnicas_masivas` | `ColaboradorController@fichasTecnicasMasivas` |
| PATCH | `/admin/colaboradores/{colaborador}/estado` | `admin.colaboradores.estado` | `ColaboradorController@cambiarEstado` |
| Recurso | `/admin/colaboradores` | `admin.colaboradores.*` | `ColaboradorController` (`show`/`destroy` pendientes de confirmar) |
| Recurso | `/admin/vehiculos` | `admin.vehiculos.*` | `VehiculoController` |
| GET | `/admin/vehiculos-asignaciones` | `admin.vehiculos.asignaciones.index` | `AsignacionVehiculoController@index` |
| GET | `/admin/vehiculos-asignaciones/create` | `admin.vehiculos.asignaciones.create` | `AsignacionVehiculoController@create` |
| POST | `/admin/vehiculos-asignaciones` | `admin.vehiculos.asignaciones.store` | `AsignacionVehiculoController@store` |
| POST | `/admin/vehiculos-asignaciones/{asignacion}/desasignar` | `admin.vehiculos.asignaciones.desasignar` | `AsignacionVehiculoController@desasignar` |
| GET | `/admin/vehiculos-asignaciones/{asignacion}/pdf-asignacion` | `admin.vehiculos.asignaciones.pdf_asignacion` | `AsignacionVehiculoController@pdfAsignacion` |
| GET | `/admin/vehiculos-asignaciones/{asignacion}/pdf-desasignacion` | `admin.vehiculos.asignaciones.pdf_desasignacion` | `AsignacionVehiculoController@pdfDesasignacion` |
| POST | `/admin/vehiculos-asignaciones/{asignacion}/pdf-firmado` | `admin.vehiculos.asignaciones.subir_pdf_firmado` | `AsignacionVehiculoController@subirPdfFirmado` |
| GET | `/admin/vehiculos-asignaciones/archivos/{archivo}/ver` | `admin.vehiculos.asignaciones.ver_pdf_firmado` | `AsignacionVehiculoController@verPdfFirmado` |
| GET | `/admin/vehiculos-productos` | `admin.vehiculos.productos.index` | `VehiculoProductoController@index` |
| POST | `/admin/vehiculos-productos` | `admin.vehiculos.productos.store` | `VehiculoProductoController@store` |
| POST | `/admin/vehiculos-productos/{asignacion}/cerrar` | `admin.vehiculos.productos.cerrar` | `VehiculoProductoController@cerrar` |
| GET | `/admin/vehiculos-productos/{asignacion}/pdf-asignacion` | `admin.vehiculos.productos.pdf_asignacion` | `VehiculoProductoController@pdfAsignacion` |
| GET | `/admin/vehiculos-productos/{asignacion}/pdf-devolucion` | `admin.vehiculos.productos.pdf_devolucion` | `VehiculoProductoController@pdfDevolucion` |
| Recurso | `/admin/compras` | `admin.compras.*` | `CompraController` (`edit`, `update`, `destroy` pendientes de confirmar) |
| GET | `/admin/operaciones/traslados` | `admin.operaciones.traslados.index` | `OperacionTrasladoController@index` |
| GET | `/admin/operaciones/traslados/create` | `admin.operaciones.traslados.create` | `OperacionTrasladoController@create` |
| POST | `/admin/operaciones/traslados` | `admin.operaciones.traslados.store` | `OperacionTrasladoController@store` |
| GET | `/admin/operaciones/traslados/{operacion}` | `admin.operaciones.traslados.show` | `OperacionTrasladoController@show` |
| POST | `/admin/operaciones/traslados/{operacion}/aprobar` | `admin.operaciones.traslados.aprobar` | `OperacionTrasladoController@aprobar` |
| POST | `/admin/operaciones/traslados/{operacion}/rechazar` | `admin.operaciones.traslados.rechazar` | `OperacionTrasladoController@rechazar` |
| GET | `/admin/operaciones/traslados/{operacion}/hoja` | `admin.operaciones.traslados.hoja` | `OperacionTrasladoController@hoja` |
| GET | `/admin/operaciones/traslados/{operacion}/archivo-excel` | `admin.operaciones.traslados.archivo` | `OperacionTrasladoController@archivo` |
| GET | `/admin/asignaciones/create` | `admin.asignaciones.create` | `AsignacionInventarioController@create` |
| GET | `/admin/asignaciones` | `admin.asignaciones.index` | `AsignacionInventarioController@index` |
| POST | `/admin/asignaciones` | `admin.asignaciones.store` | `AsignacionInventarioController@store` |
| GET | `/admin/asignaciones/colaborador/{codigo}/pdf` | `admin.asignaciones.pdf` | `AsignacionInventarioController@pdf` |
| GET | `/admin/asignaciones/devoluciones/{grupo}` | `admin.asignaciones.hoja_devolucion` | `AsignacionInventarioController@hojaDevolucion` |
| POST | `/admin/asignaciones/{asignacion}/pdf-firmado` | `admin.asignaciones.upload_pdf_firmado` | `AsignacionInventarioController@uploadPdfFirmado` |
| POST | `/admin/asignaciones/devoluciones/{grupo}/pdf-firmado` | `admin.asignaciones.upload_pdf_devolucion_firmado` | `AsignacionInventarioController@uploadPdfDevolucionFirmado` |
| GET | `/admin/asignaciones/archivos/{archivo}/ver` | `admin.asignaciones.ver_pdf_firmado` | `AsignacionInventarioController@verPdfFirmado` |
| POST | `/admin/asignaciones/{asignacion}/devolver` | `admin.asignaciones.devolver` | `AsignacionInventarioController@devolver` |
| POST | `/admin/asignaciones/devolver-lote` | `admin.asignaciones.devolver_lote` | `AsignacionInventarioController@devolverLote` |
| POST | `/admin/asignaciones/colaborador/{codigo}/devolver-todo` | `admin.asignaciones.devolver_todo_colaborador` | `AsignacionInventarioController@devolverTodoColaborador` |

## Operador (`/operador`, `role:2`)

| Método | URL | Nombre | Controlador / acción |
|---|---|---|---|
| GET | `/operador/dashboard` | `operador.dashboard` | View `operador.dashboard` |
| Recurso parcial | `/operador/bodegas` | `operador.bodegas.index/show` | `BodegaController@index/show` |
| GET | `/operador/bodegas/{bodega}/inventario-export` | `operador.bodegas.inventario.export` | `BodegaController@exportInventario` |
| Recurso | `/operador/compras` | `operador.compras.*` | `CompraController` (`edit`, `update`, `destroy` pendientes de confirmar) |
| Rutas asignaciones | `/operador/asignaciones...` | `operador.asignaciones.*` | Mismas acciones de asignaciones usadas por admin |
| Rutas traslados | `/operador/operaciones/traslados...` | `operador.operaciones.traslados.*` | Mismas acciones de traslados usadas por admin |

## RRHH (`/rrhh`, `role:4`)

| Método | URL | Nombre | Controlador / acción |
|---|---|---|---|
| GET | `/rrhh/dashboard` | `rrhh.dashboard` | `RrhhDashboardController@index` |
| GET | `/rrhh/alertas` | `rrhh.alertas.index` | `RrhhDashboardController@alertas` |
| GET | `/rrhh/alertas/export` | `rrhh.alertas.export` | `RrhhDashboardController@export` |
| POST | `/rrhh/alertas/{alerta}/finalizar` | `rrhh.alertas.finalizar` | `RrhhDashboardController@finalizar` |
| Recurso | `/rrhh/usuarios` | `rrhh.usuarios.*` | `UsuarioController` |
| Rutas colaboradores | `/rrhh/colaboradores...` | `rrhh.colaboradores.*` | `ColaboradorController` |

## Autenticación (`routes/auth.php`)

| Método | URL | Nombre | Controlador / acción |
|---|---|---|---|
| GET/POST | `/register` | `register` | `RegisteredUserController@create/store` |
| GET/POST | `/login` | `login` | `AuthenticatedSessionController@create/store` |
| GET/POST | `/forgot-password` | `password.request/email` | `PasswordResetLinkController@create/store` |
| GET/POST | `/reset-password/{token}` / `/reset-password` | `password.reset/store` | `NewPasswordController@create/store` |
| GET | `/verify-email` | `verification.notice` | `EmailVerificationPromptController` |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | `VerifyEmailController` |
| POST | `/email/verification-notification` | `verification.send` | `EmailVerificationNotificationController@store` |
| GET/POST | `/confirm-password` | `password.confirm` | `ConfirmablePasswordController@show/store` |
| PUT | `/password` | `password.update` | `PasswordController@update` |
| POST | `/logout` | `logout` | `AuthenticatedSessionController@destroy` |
