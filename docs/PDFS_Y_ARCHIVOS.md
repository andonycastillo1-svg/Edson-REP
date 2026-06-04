# PDFs y archivos

## PDFs generados

| Documento | Ruta | Controlador | Vista |
|---|---|---|---|
| Asignación de inventario | `*.asignaciones.pdf` | `AsignacionInventarioController@pdf` | `admin/asignaciones/pdf.blade.php` |
| Hoja de devolución | `*.asignaciones.hoja_devolucion` | `AsignacionInventarioController@hojaDevolucion` | `admin/asignaciones/hoja_devolucion.blade.php` |
| Asignación de vehículo | `admin.vehiculos.asignaciones.pdf_asignacion` | `AsignacionVehiculoController@pdfAsignacion` | `admin/vehiculos/asignaciones/pdf_asignacion.blade.php` |
| Desasignación de vehículo | `admin.vehiculos.asignaciones.pdf_desasignacion` | `AsignacionVehiculoController@pdfDesasignacion` | `admin/vehiculos/asignaciones/pdf_desasignacion.blade.php` |
| Producto/refacción de vehículo | `admin.vehiculos.productos.pdf_asignacion` / `pdf_devolucion` | `VehiculoProductoController` | `admin/vehiculos/productos/pdf.blade.php` |
| Hoja de traslado | `*.operaciones.traslados.hoja` | `OperacionTrasladoController@hoja` | `admin/traslados/hoja.blade.php` |

## Archivos firmados

### Inventario

- Cargar PDF firmado de asignación: `*.asignaciones.upload_pdf_firmado`.
- Cargar PDF firmado de devolución: `*.asignaciones.upload_pdf_devolucion_firmado`.
- Ver archivo firmado: `*.asignaciones.ver_pdf_firmado`.
- Campo: `pdf_firmado`.
- Validación detectada: PDF máximo 10 MB.
- Tabla: `asignacion_inventario_archivos`.

### Vehículos

- Cargar PDF firmado: `admin.vehiculos.asignaciones.subir_pdf_firmado`.
- Ver PDF firmado: `admin.vehiculos.asignaciones.ver_pdf_firmado`.
- Tabla: `asignacion_vehiculo_archivos`.
- Tipos detectados: `asignacion_firmada`, `devolucion_firmada`.

### Compras

- Campo: `pdfs[]`.
- Validación detectada: archivos PDF máximo 10 MB.
- Storage: `compras/{compraId}` en disco `public`.
- Tabla: `compra_archivos`.
- Vista de detalle: `admin/compras/show.blade.php`.

### Traslados

- Campo detectado: `archivo_excel`.
- Formatos indicados en vista: `.xlsx`, `.xls`, `.csv`.
- Columnas: `archivo_excel_path`, `archivo_excel_nombre`.
- Ruta de consulta: `*.operaciones.traslados.archivo`.

## Exportaciones

- Alertas RRHH: CSV en `RrhhDashboardController@export`.
- Fichas técnicas de colaboradores: CSV en `ColaboradorController`.
- Inventario por bodega: `BodegaController@exportInventario`.
