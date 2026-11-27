"""Contrato de las hojas que IT procesa. Los nombres de columna son para usuarios."""
from dataclasses import dataclass

BASE = "d557269da8a2350a3c6046027a4d5ba72d4a1332"

LISTAS = {
    "cambio": ["Nuevo", "Actualizar", "Verificar"],
    "laboral": ["Activo", "Inactivo"],
    "region_estado": ["Activa", "Inactiva"],
    "cuenta": ["Activa", "Inactiva"],
    "bodega_tipo": ["Principal", "Regional"],
    "coordinacion": ["Principal", "Adicional"],
    "bodega_relacion": ["Consulta", "Operación de inventario", "Requerimientos de central"],
    "permiso_regla": ["Heredar", "Permitir", "Denegar"],
    "stock": ["Nuevo", "Usado en buen estado", "Dañado", "Baja"],
    "custodia": ["Nuevo al entregar", "Usado al entregar", "Desconocido"],
    "contabilizado": ["Sí", "No", "Por conciliar"],
    "pago": ["Al contado", "Crédito 30 días", "Crédito 60 días", "Crédito 90 días"],
}
STOCK = dict(zip(LISTAS["stock"], ["nuevo", "usado", "danado", "baja"], strict=True))


@dataclass(frozen=True)
class Campo:
    clave: str
    titulo: str
    requerido: bool = False
    tipo: str = "texto"
    limite: int = 150
    lista: str = ""
    ancho: int = 27
    ayuda: str = ""

    @property
    def encabezado(self):
        return self.titulo + (" *" if self.requerido else "")


def campo(clave, titulo, obligatorio=False, *, tipo="texto", limite=150, lista="", ancho=27, ayuda=""):
    return Campo(clave, titulo, obligatorio, tipo, limite, lista, ancho, ayuda)


ACCION = campo("accion", "Tipo de solicitud", True, lista="cambio", ancho=22,
               ayuda="Nuevo crea el registro. Actualizar propone cambios. Verificar compara sin escribir.")
REGION = campo("region", "Región", True, lista="regiones", limite=120,
               ayuda="Usa el nombre exacto del catálogo o una región nueva incluida en el mismo envío.")
BODEGA = campo("bodega", "Bodega", True, lista="bodegas", limite=100,
               ayuda="Usa el nombre exacto. Si existe más de una bodega con el mismo nombre, consulta a IT.")
PRODUCTO = campo("producto", "Código del producto", True, limite=50, lista="productos",
                 ayuda="Conserva el código original, incluidos los ceros al inicio.")
COLABORADOR = campo("colaborador", "Código de colaborador", True, limite=20,
                    ayuda="Conserva el código original, incluidos los ceros al inicio.")
CORREO = campo("correo", "Correo electrónico", True, tipo="correo", limite=255, ancho=37)
REFERENCIA = campo("referencia", "Referencia de respaldo", True, limite=150, ancho=32,
                   ayuda="Folio o referencia estable del documento real. Consérvalo al corregir el mismo registro.")
CANTIDAD = campo("cantidad", "Cantidad", True, tipo="entero_positivo", ancho=13)
VIDA = campo("vida_restante", "Vida restante acreditada en días", tipo="entero", limite=36000, ancho=24,
             ayuda="Cero significa agotada. En blanco significa desconocida. Regístrala solo si hay respaldo.")
NOTA = campo("nota", "Observaciones para IT", limite=1000, ancho=38)
PRODUCTO_NOMBRE = campo("nombre_producto", "Nombre del producto", limite=150, ancho=29,
                        ayuda="Referencia para comprobar el código. Si lo escribes, usa el nombre del catálogo.")
REGION_REFERENCIA = campo("region_referencia", "Región del colaborador", lista="regiones", limite=120,
                          ayuda="Si la conoces, úsala para contrastar el expediente.")

# clave: archivo, hoja, equipo, finalidad, columnas, ejemplo
HOJAS = {
    "colaboradores": ("Colaboradores", "COLABORADORES", "Recursos Humanos",
        "Recopila el código, el nombre, el puesto, la región y el estado de cada colaborador.",
        (ACCION, COLABORADOR, campo("nombre", "Nombre completo", True, limite=100, ancho=33),
         campo("puesto", "Puesto", True, limite=80), REGION, campo("estado", "Estado laboral", True, lista="laboral")),
        ("Nuevo", "000045", "Ana Campo Demo", "Técnica de campo", "Norte Demo", "Activo")),
    "usuarios": ("Usuarios", "USUARIOS", "Administración de usuarios",
        "Recopila identidades, rol y estado. IT administra el acceso y las credenciales por el canal autorizado.",
        (ACCION, CORREO, campo("nombre", "Nombre completo", True, limite=255, ancho=33),
         campo("rol", "Rol principal", True, lista="roles", limite=50),
         campo("estado", "Estado solicitado", True, lista="cuenta")),
        ("Nuevo", "ana@example.test", "Ana Campo Demo", "Supervisor", "Activa")),
    "regiones": ("Regiones", "REGIONES", "Administración y operaciones",
        "Confirma los nombres de las regiones y si están habilitadas para el trabajo nuevo.",
        (ACCION, REGION, campo("estado", "Estado de la región", True, lista="region_estado", ancho=24)),
        ("Nuevo", "Norte Demo", "Activa")),
    "regiones_usuarios": ("Relaciones", "REGIONES DE USUARIOS", "Administración y responsables regionales",
        "Registra una fila por correo y región autorizada. Se pueden repetir cuentas en distintas regiones.",
        (CORREO, REGION), ("ana@example.test", "Norte Demo")),
    "coordinacion": ("Relaciones", "COORDINACIÓN REGIONAL", "Administración y responsables regionales",
        "Registra la coordinación principal o adicional. Una región admite una persona principal y varias adicionales.",
        (CORREO, REGION, campo("tipo", "Responsabilidad", True, lista="coordinacion")),
        ("coord@example.test", "Norte Demo", "Adicional")),
    "bodegas_usuarios": ("Relaciones", "BODEGAS DE USUARIOS", "Administración y responsables de bodega",
        "Registra una fila por correo, bodega y relación. Se puede registrar más de una bodega por persona.",
        (CORREO, BODEGA, campo("tipo", "Tipo de relación", True, lista="bodega_relacion", ancho=31)),
        ("bodega@example.test", "Bodega Central Demo", "Operación de inventario")),
    "bodega_asignada": ("Relaciones", "BODEGA ASIGNADA", "Administración de usuarios",
        "Indica la bodega asignada en el perfil de un operador. Usa una fila por operador.",
        (CORREO, BODEGA), ("bodega@example.test", "Bodega Central Demo")),
    "supervision": ("Relaciones", "SUPERVISIÓN", "Operaciones y administración de usuarios",
        "Relaciona al supervisor con su almacenista de perfil. Indica el coordinador de perfil si corresponde.",
        (campo("supervisor", "Correo del supervisor", True, tipo="correo", limite=255, ancho=37),
         campo("almacenista", "Correo del almacenista", True, tipo="correo", limite=255, ancho=37),
         campo("coordinador", "Correo del coordinador", tipo="correo", limite=255, ancho=37)),
        ("ana@example.test", "bodega@example.test", "coord@example.test")),
    "permisos": ("Relaciones", "PERMISOS INDIVIDUALES", "Administración de seguridad",
        "Solicita excepciones por persona y acción. Las relaciones regionales y de bodega se registran en sus propias hojas.",
        (CORREO, campo("permiso", "Acción solicitada", True, lista="permisos", limite=255, ancho=55),
         campo("regla", "Regla solicitada", True, lista="permiso_regla"),
         campo("motivo", "Motivo y aprobación de la solicitud", True, limite=500, ancho=40)),
        ("ana@example.test", "Inspecciones | Registrar inspecciones", "Heredar", "Aprobación de demostración")),
    "bodegas": ("Bodegas", "BODEGAS", "Administración y responsables de bodega",
        "Recopila sedes para carga inicial o actualización. Usa Relaciones para registrar a las personas responsables.",
        (ACCION, campo("bodega", "Nombre de bodega", True, limite=100, ancho=33),
         campo("tipo", "Tipo de bodega", True, lista="bodega_tipo"),
         campo("ubicacion", "Ubicación", limite=150, ancho=36)),
        ("Nuevo", "Bodega Norte Demo", "Regional", "Edificio de demostración")),
    "productos": ("Productos", "PRODUCTOS", "Compras y responsables del catálogo",
        "Recopila el catálogo de productos. La vigencia se expresa en meses cuando está documentada.",
        (ACCION, campo("producto", "Código del producto", True, limite=50),
         campo("nombre", "Nombre del producto", True, limite=150, ancho=32),
         campo("descripcion", "Descripción", limite=10000, ancho=36),
         campo("categoria", "Categoría", lista="categorias", limite=50),
         campo("unidad", "Unidad de medida", limite=50),
         campo("vida_meses", "Vida útil en meses", tipo="entero_positivo", limite=1200, ancho=18)),
        ("Nuevo", "BOTA-04", "Bota de seguridad", "Demostración", None, "Par", 12)),
    "inventario": ("Inventario_Inicial", "INVENTARIO INICIAL", "Bodega",
        "Registra el conteo físico a una fecha de corte. Separa por bodega, código, condición y lote documentado.",
        (BODEGA, PRODUCTO, PRODUCTO_NOMBRE, CANTIDAD, campo("condicion", "Condición", True, lista="stock"),
         campo("corte", "Fecha del conteo", True, tipo="fecha"), REFERENCIA,
         campo("ingreso", "Fecha de ingreso acreditada", tipo="fecha", ancho=24), VIDA, NOTA),
        ("Bodega Norte Demo", "BOTA-04", "Bota de seguridad", 10, "Nuevo", "2026-11-25", "CONTEO-DEMO-001", None, None, None)),
    "custodias": ("Articulos_Asignados", "ARTÍCULOS ASIGNADOS", "Recursos Humanos y Bodega",
        "Registra únicamente artículos que siguen bajo responsabilidad de una persona al corte del conteo.",
        (COLABORADOR, campo("nombre_colaborador", "Nombre del colaborador", limite=100, ancho=29),
         REGION_REFERENCIA, PRODUCTO, PRODUCTO_NOMBRE, BODEGA, CANTIDAD,
         campo("condicion", "Condición en la entrega", True, lista="custodia", ancho=24),
         campo("entrega", "Fecha de entrega acreditada", tipo="fecha", ancho=24),
         campo("corte", "Fecha de verificación", True, tipo="fecha"), VIDA, REFERENCIA, NOTA),
        ("000045", "Ana Campo Demo", "Norte Demo", "BOTA-04", "Bota de seguridad", "Bodega Norte Demo", 1,
         "Desconocido", None, "2026-11-25", None, "ACTA-DEMO-004", None)),
    "facturas": ("Compras_Historicas_Opcional", "FACTURAS HISTÓRICAS", "Compras y Contabilidad",
        "Usa esta hoja solo si IT confirma que se necesita un archivo de facturas anteriores.",
        (campo("proveedor", "Proveedor", True), campo("factura", "Número de factura", True, limite=50),
         campo("fecha", "Fecha de factura", True, tipo="fecha"), campo("pago", "Forma de pago", lista="pago"),
         campo("contabilizado", "¿Ya está reflejada en existencias?", True, lista="contabilizado", ancho=32),
         REFERENCIA, NOTA),
        ("Suministros Demo", "F-DEMO-01", "2026-10-12", "Al contado", "Sí", "ARCHIVO-DEMO-001", None)),
    "factura_lineas": ("Compras_Historicas_Opcional", "DETALLE HISTÓRICO", "Compras y Contabilidad",
        "Registra una fila por proveedor, factura y código. Conserva las cantidades y precios del comprobante.",
        (campo("proveedor", "Proveedor", True), campo("factura", "Número de factura", True, limite=50),
         PRODUCTO, CANTIDAD, campo("precio", "Precio unitario", True, tipo="precio", ancho=19)),
        ("Suministros Demo", "F-DEMO-01", "BOTA-04", 3, "72.50")),
}
