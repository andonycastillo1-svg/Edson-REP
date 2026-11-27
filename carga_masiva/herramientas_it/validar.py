"""Valida hojas, compara con el entorno y construye un plan revisable."""
import re
import unicodedata
import zipfile
from collections import Counter, defaultdict
from dataclasses import dataclass
from datetime import date, datetime
from decimal import Decimal, InvalidOperation
from xml.etree.ElementTree import ParseError

from openpyxl import load_workbook
from openpyxl.utils.exceptions import InvalidFileException

import captura
from esquema import HOJAS, LISTAS, STOCK
from plantillas import ENCABEZADO, PRIMERA, ULTIMA, PERMISOS, listas_del_entorno


AMB_RED = {2: "Operador", 3: "Supervisor", 5: "Coordinador"}


def clave(valor):
    texto = unicodedata.normalize("NFKD", str(valor or "").strip().casefold())
    return "".join(c for c in texto if not unicodedata.combining(c))


@dataclass
class Fila:
    archivo: str
    hoja: str
    numero: int
    grupo: str
    datos: dict

    def columna(self, campo):
        return next((c.encabezado for c in HOJAS[self.grupo][4] if c.clave == campo), campo)


def convertir(celda, campo):
    valor = celda.value
    if valor is None or isinstance(valor, str) and not valor.strip():
        if campo.requerido:
            raise ValueError("Este campo es obligatorio.")
        return None
    if celda.data_type in {"f", "e"}:
        raise ValueError("La celda contiene una fórmula o un error. Pega el valor como dato.")
    if campo.tipo == "fecha":
        if isinstance(valor, datetime):
            if valor.time() != datetime.min.time():
                raise ValueError("Ingresa la fecha sin hora.")
            valor = valor.date()
        if not isinstance(valor, date):
            if not isinstance(valor, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}", valor.strip()):
                raise ValueError("Escribe la fecha como AAAA-MM-DD.")
            valor = date.fromisoformat(valor.strip())
        if not 1900 <= valor.year or valor > date.today():
            raise ValueError("La fecha debe ser válida y no puede ser posterior a hoy.")
        return valor.isoformat()
    if campo.tipo.startswith("entero"):
        minimo = 0 if campo.tipo == "entero" else 1
        maximo = campo.limite if campo.limite != 150 else 4294967295
        if isinstance(valor, bool) or not re.fullmatch(r"\d+(?:\.0+)?", str(valor).strip()):
            raise ValueError("Ingresa un número entero.")
        numero = int(Decimal(str(valor).strip()))
        if not minimo <= numero <= maximo:
            raise ValueError(f"El número debe estar entre {minimo} y {maximo}.")
        return numero
    if campo.tipo == "precio":
        texto = str(valor).strip()
        if not re.fullmatch(r"\d{1,10}(?:\.\d{1,2})?", texto):
            raise ValueError("Ingresa un precio no negativo con hasta dos decimales.")
        return str(Decimal(texto).quantize(Decimal("0.01")))
    if not isinstance(valor, str):
        raise ValueError("Ingresa texto. Para un código numérico, pega como texto y comprueba los ceros.")
    texto = valor.strip()
    if len(texto) > campo.limite or any(ord(c) < 32 and c not in "\n\t" for c in texto):
        raise ValueError(f"El texto admite hasta {campo.limite} caracteres.")
    if campo.tipo == "correo":
        if not re.fullmatch(r"[^@\s]+@[^@\s.]+(?:\.[^@\s.]+)+", texto):
            raise ValueError("Ingresa un correo electrónico válido.")
        texto = texto.lower()
    if campo.lista in LISTAS:
        opcion = next((x for x in LISTAS[campo.lista] if clave(x) == clave(texto)), None)
        if opcion is None:
            raise ValueError("El valor no pertenece a la lista permitida.")
        texto = opcion
    return texto


def archivos_excel(origen):
    rutas = sorted(origen.glob("*.xlsx")) if origen.is_dir() else [origen]
    return [p for p in rutas if not p.name.startswith("~$")]


def leer_filas(origen, informar):
    por_hoja = {h[1]: (g, h[4]) for g, h in HOJAS.items()}
    filas = []
    rutas = archivos_excel(origen)
    if not rutas:
        informar(None, "Archivo", "No se encontraron archivos .xlsx.", "Entrega una plantilla completada.")
    for ruta in rutas:
        origen_fila = Fila(ruta.name, "", 0, "", {})
        libro = None
        try:
            if ruta.suffix.lower() != ".xlsx" or ruta.stat().st_size > 15 * 1024 * 1024:
                raise ValueError("Se requiere un .xlsx de hasta 15 MB.")
            with zipfile.ZipFile(ruta) as z:
                if sum(x.file_size for x in z.infolist()) > 96 * 1024 * 1024 or len(z.infolist()) > 300:
                    raise ValueError("El archivo contiene más datos internos de los esperados.")
            libro = load_workbook(ruta, read_only=True, data_only=False, keep_links=False)
            compatibles = [(n, por_hoja[n]) for n in libro.sheetnames if n in por_hoja]
            if not compatibles:
                raise ValueError("No contiene hojas de captura reconocidas.")
            extras = set(libro.sheetnames) - set(por_hoja) - {"INSTRUCCIONES", "EJEMPLOS", "CATÁLOGOS"}
            if extras:
                raise ValueError("Contiene hojas sin reconocer: " + ", ".join(sorted(extras)))
            for nombre, (grupo, campos) in compatibles:
                ws = libro[nombre]
                encabezados = next(ws.iter_rows(min_row=ENCABEZADO, max_row=ENCABEZADO, values_only=True), ())
                esperados = tuple(c.encabezado for c in campos)
                origen_fila = Fila(ruta.name, nombre, ENCABEZADO, grupo, {})
                if encabezados[:len(campos)] != esperados or any(x is not None for x in encabezados[len(campos):]):
                    informar(origen_fila, "Encabezados", "La estructura de columnas cambió.",
                             "Copia los datos en una plantilla oficial conservando las columnas.")
                    continue
                if (ws.max_row or 0) > ULTIMA or (ws.max_column or 0) > len(campos):
                    informar(origen_fila, "Rango", "La hoja supera el rango de captura.",
                             "Reparte las filas entre copias oficiales y elimina columnas añadidas.")
                    continue
                for numero, celdas in enumerate(ws.iter_rows(min_row=PRIMERA, max_col=len(campos)), PRIMERA):
                    if not any(c.value is not None and str(c.value).strip() for c in celdas):
                        continue
                    fila = Fila(ruta.name, nombre, numero, grupo, {})
                    completa = True
                    for c, campo in zip(celdas, campos, strict=True):
                        try:
                            fila.datos[campo.clave] = convertir(c, campo)
                        except (ValueError, InvalidOperation, OverflowError) as exc:
                            informar(fila, campo.clave, str(exc), "Revisa la instrucción de la columna y corrige el dato.")
                            completa = False
                    if completa:
                        filas.append(fila)
        except (OSError, ValueError, KeyError, zipfile.BadZipFile, ParseError, InvalidFileException) as exc:
            informar(origen_fila, "Archivo", str(exc), "Abre la plantilla oficial y guarda los datos como .xlsx.")
        finally:
            if libro is not None:
                libro.close()
    if not filas:
        informar(None, "Captura", "No hay filas de captura válidas.", "Completa las hojas de captura. EJEMPLOS no se procesa.")
    return filas


class Revision:
    def __init__(self, instantanea):
        self.snapshot = instantanea
        self.problemas = []
        self.plan = []
        self.vistos = set()
        self.catalogos = {}
        for grupo, campo in (("regiones", "nombre"), ("bodegas", "nombre"), ("productos", "codigo"),
                              ("colaboradores", "codigo"), ("usuarios", "email"), ("roles", "nombre"),
                              ("proveedores", "nombre")):
            indice = defaultdict(list)
            for fila in instantanea[grupo]:
                indice[clave(fila[campo])].append(fila)
            self.catalogos[grupo] = indice
        self.roles_id = {r["id"]: r for r in instantanea["roles"]}
        self.regiones_id = {r["id"]: r for r in instantanea["regiones"]}
        self.general = captura.general(instantanea)
        centrales = sorted(r["id"] for r in instantanea["bodegas"] if r["tipo"] == "Principal")
        self.central = self.general.get("bodega_principal") or (centrales[0] if centrales else None)
        self.categorias = {clave(x): x for x in listas_del_entorno(instantanea)["categorias"]}
        self.permisos = {clave(v["etiqueta"]): (k, v) for k, v in PERMISOS.items()}
        self.por_grupo = defaultdict(list)
        self.facturas = self.indice_facturas()
        self.filas_factura = defaultdict(list)
        self.exigidos = {r["modulo"] for r in instantanea["campos"]
                         if r["activo"] and r["formulario"] and r["obligatorio"] and not r["archivado_en"]}
        ids_campos = {r["id"]: r["modulo"] for r in instantanea["campos"]}
        self.claves_extra = {(ids_campos.get(r["campo_id"]), clave(r["registro"])) for r in instantanea["claves_personalizadas"]}

    def informe(self, fila, campo, mensaje, sugerencia, gravedad="ERROR"):
        self.problemas.append({
            "gravedad": gravedad, "archivo": fila.archivo if fila else "",
            "hoja": fila.hoja if fila else "", "fila": fila.numero if fila else "",
            "columna": fila.columna(campo) if fila and fila.grupo else campo,
            "problema": mensaje, "corrección_sugerida": sugerencia,
        })

    def aviso(self, fila, campo, texto, correccion):
        self.informe(fila, campo, texto, correccion, "REVISAR")

    def indice_facturas(self):
        proveedores = {r["id"]: r["nombre"] for r in self.snapshot["proveedores"]}
        return {(clave(proveedores.get(r["proveedor_id"])), clave(r["no_factura"])): r
                for r in self.snapshot["facturas"]}

    def buscar(self, grupo, dato):
        filas = self.catalogos[grupo].get(clave(dato), ())
        return filas[0] if len(filas) == 1 else None

    def referencia(self, fila, campo, grupo, *, activo=None, ambito=None):
        valor = fila.datos.get(campo)
        if valor is None:
            return None
        original = self.buscar(grupo, valor)
        buscar_en = {"regiones": "nombre", "bodegas": "nombre", "usuarios": "email", "roles": "nombre",
                     "proveedores": "nombre", "colaboradores": "codigo", "productos": "codigo"}[grupo]
        if original is None:
            self.informe(fila, campo, f"El valor no existe o es ambiguo en {grupo}.",
                         "Usa el nombre o código exacto del catálogo. Si procede, envía su alta en el mismo paquete.")
            return None
        if valor != original[buscar_en] and grupo != "usuarios":
            self.informe(fila, campo, "El valor no coincide exactamente con el catálogo.",
                         f"Usa {original[buscar_en]}.")
        if activo is not None and not original.get(activo):
            self.informe(fila, campo, "La referencia está inactiva.", "Usa una referencia activa o solicita su reactivación.")
        if ambito is not None and self.ambito(original) != ambito:
            self.informe(fila, campo, "El rol de la cuenta no corresponde a esta relación.",
                         f"Selecciona una cuenta del ámbito {AMB_RED[ambito]}.")
        return original

    def ambito(self, usuario):
        rol = self.roles_id.get(usuario.get("role_id"), {})
        return rol.get("id") if rol.get("id") in {1, 2, 3, 4} else rol.get("ambito_operativo")

    def unica(self, fila, clave_fila, campo):
        marcador = (fila.grupo, tuple(clave(x) for x in clave_fila))
        if marcador in self.vistos:
            self.informe(fila, campo, "Registro repetido dentro del envío.",
                         "Conserva una sola fila por clave o utiliza folios diferentes para respaldos distintos.")
            return False
        self.vistos.add(marcador)
        return True

    def registrar(self, fila, llave, antes, datos, estado, destino, *, razones=(), referencias=None):
        self.plan.append({
            "entidad": fila.grupo, "clave": list(llave), "estado": estado, "destino": destino,
            "archivo": fila.archivo, "hoja": fila.hoja, "fila": fila.numero,
            "antes": antes or {}, "propuesto": datos, "referencias": referencias or {},
            "revisión": list(razones),
        })

    def maestros(self, fila):
        d, g = fila.datos, fila.grupo
        campo_clave = {"regiones": "region", "bodegas": "bodega", "colaboradores": "colaborador",
                       "productos": "producto", "usuarios": "correo"}[g]
        llave = d[campo_clave]
        if not self.unica(fila, [llave], campo_clave):
            return
        if len(self.catalogos[g].get(clave(llave), ())) > 1:
            self.informe(fila, campo_clave, "Varios registros del entorno comparten este identificador.",
                         "Pide a IT resolver la ambigüedad antes de cargar.")
            return
        existente = self.buscar(g, llave)
        if g == "usuarios" and existente:
            llave = existente["email"]
        nombre_id = {"regiones": "nombre", "bodegas": "nombre", "colaboradores": "codigo",
                     "productos": "codigo", "usuarios": "email"}[g]
        if existente and existente[nombre_id] != llave:
            self.informe(fila, campo_clave, "El identificador difiere en escritura del registro existente.",
                         f"Usa {existente[nombre_id]}. No se renombra la clave de un registro.")
            return
        if d["accion"] in {"Actualizar", "Verificar"} and existente is None:
            self.informe(fila, campo_clave, "No hay un registro existente con esta clave.",
                         "Corrige la clave o elige Nuevo para un alta aprobada.")
            return
        resultado = self.maestro_datos(fila, existente, llave)
        if resultado is None:
            return
        nuevo, referencia = resultado
        if existente:
            nuevo = {k: existente.get(k) if v is None and k not in {"region_id"} else v for k, v in nuevo.items()}
        estado = "NUEVO" if existente is None else (
            "EXISTENTE" if all(existente.get(k) == v for k, v in nuevo.items()) else "ACTUALIZAR")
        if estado == "ACTUALIZAR" and d["accion"] == "Nuevo":
            self.informe(fila, campo_clave, "La clave ya existe con datos diferentes.",
                         "Compara los datos y, si se autorizó el cambio, selecciona Actualizar.")
            return
        razones = []
        if d["accion"] == "Verificar" and estado == "ACTUALIZAR":
            self.aviso(fila, campo_clave, "La comparación detectó diferencias.", "Revisa antes y propuesto en el plan.")
            self.registrar(fila, [llave], existente, nuevo, "DIFERENCIAS", "SOLO_REVISIÓN", referencias=referencia)
            return
        if g == "usuarios":
            razones.append("Alta, activación, rol y credenciales a través de Seguridad de Edson-REP.")
        if g == "bodegas" and (nuevo["tipo"] == "Principal" and not existente or
                              existente and nuevo["tipo"] != existente["tipo"]):
            razones.append("Revisar en Edson-REP el tipo y la designación protegida de bodega principal.")
        if g == "regiones" and existente and not nuevo["activa"]:
            razones.append("Verificar impacto sobre responsables, delegaciones y expedientes antes de inactivar.")
        if g in self.exigidos and not existente:
            razones.append("El entorno exige campos personalizados en el alta. Completar en el formulario autorizado.")
        if not existente and (g, clave(llave)) in self.claves_extra:
            self.informe(fila, campo_clave, "Hay campos personalizados históricos asociados a la clave.",
                         "Pide a IT comprobar el origen de esos valores antes de reutilizarla.")
            return
        destino = "SOLO_REVISIÓN" if estado == "EXISTENTE" else "PREPARACIÓN" if razones else "SQL_CATÁLOGO"
        if g == "colaboradores" and nuevo["region_id"] is None:
            destino = "PREPARACIÓN"
            razones.append("Resolver la región cuando esté disponible en el entorno.")
        if g == "bodegas" and destino == "PREPARACIÓN":
            self.aviso(fila, "tipo", razones[0], "Verifica la designación y el historial en Edson-REP.")
        self.registrar(fila, [llave], existente, nuevo, estado, destino, razones=razones, referencias=referencia)
        if destino != "SOLO_REVISIÓN":
            id_ = (existente or {}).get("id")
            meta = {**(existente or {}), **nuevo, "_pendiente": not bool(existente) or destino == "PREPARACIÓN"}
            if "id" in (existente or {}) or g in {"regiones", "bodegas", "usuarios"}:
                meta["id"] = id_
            if g == "colaboradores":
                meta["_region"] = referencia["region"]
            self.catalogos[g][clave(llave)] = [meta]

    def maestro_datos(self, fila, existente, llave):
        d, g = fila.datos, fila.grupo
        if g == "regiones":
            return {"nombre": llave, "activa": int(d["estado"] == "Activa")}, {}
        if g == "bodegas":
            return {"nombre": llave, "tipo": d["tipo"], "ubicacion": d["ubicacion"]}, {}
        if g == "productos":
            categoria = d["categoria"]
            if categoria and clave(categoria) not in self.categorias:
                self.aviso(fila, "categoria", "Categoría fuera de las sugerencias actuales.",
                           "Confirma con Compras la categoría propuesta. Edson-REP admite categorías libres.")
            return dict(codigo=llave, nombre=d["nombre"], descripcion=d["descripcion"],
                        categoria=self.categorias.get(clave(categoria), categoria), unidad_medida=d["unidad"],
                        vida_util_meses=d["vida_meses"]), {}
        if g == "usuarios":
            rol = self.referencia(fila, "rol", "roles", activo="activo")
            if rol is None:
                return None
            return dict(email=llave, name=d["nombre"], role_id=rol["id"], acceso_activo=int(d["estado"] == "Activa")), {"rol": rol["nombre"]}
        region = self.referencia(fila, "region", "regiones")
        if region is None:
            return None
        if not region["activa"] and (not existente or existente["region_id"] != region["id"]):
            self.informe(fila, "region", "La región está inactiva.", "Usa una región activa o conserva la región anterior.")
        return dict(codigo=llave, nombre=d["nombre"], puesto=d["puesto"], estado=d["estado"], region_id=region["id"]), {"region": region["nombre"]}

    def relacion(self, fila):
        g, d = fila.grupo, fila.datos
        referencias = {}
        ultimo = next(reversed(d))
        if g == "supervision":
            correo = "supervisor"
            supervisor = self.referencia(fila, correo, "usuarios", activo="acceso_activo", ambito=3)
            almacenista = self.referencia(fila, "almacenista", "usuarios", activo="acceso_activo", ambito=2)
            coord = self.referencia(fila, "coordinador", "usuarios", activo="acceso_activo", ambito=5)
            if not supervisor or not almacenista or d["coordinador"] and not coord:
                return
            referencias = {k: r.get("id") for k, r in [("supervisor", supervisor), ("almacenista", almacenista), ("coordinador", coord or {})]}
            id_ = supervisor["id"]
            prev = next((r for r in self.snapshot["supervisores"] if r["supervisor_id"] == id_), None)
            igual = bool(prev and prev["almacenista_id"] == almacenista["id"]
                         and (not coord or supervisor.get("coordinador_id") == coord["id"]))
            antes = {**(prev or {}), "coordinador_id": supervisor.get("coordinador_id")}
            llave = [d[correo]]
        else:
            correo = "correo"
            usuario = self.referencia(fila, correo, "usuarios", activo="acceso_activo",
                                      ambito={"coordinacion": 5, "bodega_asignada": 2}.get(g))
            if not usuario:
                return
            id_ = usuario["id"]
            referencias = {"usuario": id_}
            llave = [d["correo"]]
            antes, igual = {}, False
            if "region" in d:
                reg = self.referencia(fila, "region", "regiones", activo="activa")
                if not reg:
                    return
                referencias["region"] = reg["id"]
                llave += [d["region"]]
                if g == "coordinacion" and d["tipo"] == "Principal":
                    if not self.unica(fila, ["principal", d["region"]], "region"):
                        return
                    antes = {"coordinador_actual": reg.get("coordinador_id")}
                    igual = id_ is not None and reg.get("coordinador_id") == id_
                    if antes["coordinador_actual"] and not igual:
                        self.aviso(fila, "region", "Ya existe coordinación principal para la región.",
                                   "Verifica la reasignación y los consolidados pendientes en Edson-REP.")
                else:
                    if g == "coordinacion" and id_ is not None and reg.get("coordinador_id") == id_:
                        self.informe(fila, "tipo", "La cuenta ya es coordinadora principal.",
                                     "Usa Principal para verificarla o indica otra persona adicional.")
                    prev = "coordinadores" if g == "coordinacion" else "region_usuario"
                    igual = any(r["user_id"] == id_ and r["region_id"] == reg["id"] for r in self.snapshot[prev])
                if "tipo" in d:
                    llave += [d["tipo"]]
            if "bodega" in d:
                bodega = self.referencia(fila, "bodega", "bodegas")
                if not bodega:
                    return
                referencias["bodega"] = bodega["id"]
                if g == "bodega_asignada":
                    antes = {"bodega_actual": usuario.get("bodega_id")}
                    igual = bodega["id"] is not None and antes["bodega_actual"] == bodega["id"]
                else:
                    if d["tipo"] == "Requerimientos de central" and (self.central is None or bodega["id"] != self.central):
                        self.informe(fila, "bodega", "Esta relación requiere la bodega central configurada.",
                                     "Confirma la bodega central y vuelve a capturar el dato.")
                    llave += [d["bodega"], d["tipo"]]
                    antes = next((r for r in self.snapshot["bodega_usuario"]
                                  if r["user_id"] == id_ and r["bodega_id"] == bodega["id"]), {})
                    columna = {"Operación de inventario": "opera_inventario",
                               "Requerimientos de central": "opera_requerimientos"}.get(d["tipo"])
                    igual = bool(antes and (columna is None or antes[columna]))
            if g == "permisos":
                permiso = self.permisos.get(clave(d["permiso"]))
                if not permiso or self.ambito(usuario) not in permiso[1]["ambitos"]:
                    self.informe(fila, "permiso", "La acción no existe o está fuera del ámbito del rol.",
                                 "Selecciona una acción disponible en la cuenta en Edson-REP.")
                    return
                referencias["permiso"] = permiso[0]
                llave += [permiso[0]]
                antes = next((r for r in self.snapshot["reglas"] if r["user_id"] == id_ and r["permiso"] == permiso[0]), {})
                if d["regla"] == "Permitir" and permiso[0].startswith(("compras.", "asignaciones.", "traslados.")):
                    self.aviso(fila, "permiso", "La acción también exige relaciones de operación y permisos complementarios.",
                               "Contrasta la bodega, regiones y acciones que muestra la cuenta en Edson-REP.")
                igual = antes.get("permitido") == {"Heredar": None, "Permitir": 1, "Denegar": 0}[d["regla"]]
        if self.unica(fila, llave, ultimo):
            self.registrar(fila, llave, antes, d, "EXISTENTE" if igual else "PROPUESTO",
                           "SOLO_REVISIÓN" if igual else "PREPARACIÓN", referencias=referencias,
                           razones=["Aplicar en Edson-REP las relaciones y autorizaciones aprobadas."])

    def inventario(self, fila):
        d = fila.datos
        bodega = self.referencia(fila, "bodega", "bodegas")
        producto = self.referencia(fila, "producto", "productos")
        if not bodega or not producto:
            return
        llave = [d[k] for k in ("bodega", "producto", "condicion", "corte", "referencia")]
        if not self.unica(fila, llave, "referencia"):
            return
        if d["nombre_producto"] and d["nombre_producto"] != producto["nombre"]:
            self.informe(fila, "nombre_producto", "El nombre no corresponde al código.",
                         f"Verifica el artículo. En el catálogo aparece {producto['nombre']}.")
        if d["ingreso"] and d["ingreso"] > d["corte"]:
            self.informe(fila, "ingreso", "El ingreso es posterior al conteo.", "Revisa la fecha del respaldo.")
        if d["vida_restante"] is not None and d["condicion"] != "Usado en buen estado":
            self.informe(fila, "vida_restante", "El dato solo aplica a usado en buen estado.",
                         "Deja la vida restante en blanco para esa condición.")
        self.vida(fila, producto)
        saldo = next((r["cantidad"] for r in self.snapshot["saldos"] if r["bodega_id"] == bodega["id"]
                      and r["producto_codigo"] == producto["codigo"] and r["stock_tipo"] == STOCK[d["condicion"]]), 0)
        if saldo:
            self.aviso(fila, "cantidad", f"El sistema ya registra {saldo} unidades en esta condición.",
                       "Compara el total físico por condición. Usa la diferencia investigada y evita sumar el conteo.")
        self.registrar(fila, llave, {"saldo_actual_condición": saldo}, d, "CONCILIAR", "PREPARACIÓN",
                       referencias={"bodega": bodega["id"], "producto": producto["codigo"],
                                    "condición": STOCK[d["condicion"]]},
                       razones=["Conteo físico absoluto. Excluir custodia y conciliar saldo, lotes y movimientos."])

    def vida(self, fila, producto):
        vida = fila.datos.get("vida_restante")
        meses = producto.get("vida_util_meses")
        if vida is not None and meses is not None and vida > meses * 31:
            self.aviso(fila, "vida_restante", "La vida indicada supera la referencia del catálogo.",
                       "Verifica la duración original, la unidad y el respaldo del lote o custodia.")

    def custodia(self, fila):
        d = fila.datos
        bodega = self.referencia(fila, "bodega", "bodegas")
        producto = self.referencia(fila, "producto", "productos")
        persona = self.referencia(fila, "colaborador", "colaboradores")
        if not bodega or not producto or not persona:
            return
        llave = [d[k] for k in ("colaborador", "producto", "bodega", "referencia")]
        if not self.unica(fila, llave, "referencia"):
            return
        if d["nombre_producto"] and d["nombre_producto"] != producto["nombre"]:
            self.informe(fila, "nombre_producto", "El nombre no corresponde al código.",
                         f"Compara el código con {producto['nombre']}.")
        if d["nombre_colaborador"] and d["nombre_colaborador"] != persona["nombre"]:
            self.informe(fila, "nombre_colaborador", "El nombre no corresponde al código.",
                         f"Compara el código con {persona['nombre']}.")
        region = self.regiones_id.get(persona.get("region_id"), {}).get("nombre") or persona.get("_region")
        if d["region_referencia"] and d["region_referencia"] != region:
            self.informe(fila, "region_referencia", "La región informada difiere del expediente.",
                         "Confirma los datos con RR. HH. o solicita la actualización del colaborador.")
        if d["entrega"] and d["entrega"] > d["corte"]:
            self.informe(fila, "entrega", "La entrega es posterior a la verificación.", "Corrige la fecha del respaldo.")
        self.vida(fila, producto)
        if persona["estado"] != "Activo":
            self.aviso(fila, "colaborador", "El colaborador figura como inactivo.",
                       "Confirma si el bien sigue bajo su responsabilidad antes de aplicar ningún cambio.")
        if not d["entrega"]:
            self.aviso(fila, "entrega", "La fecha histórica es desconocida y queda vacía en preparación.",
                       "Busca respaldo. Conserva el caso en preparación si falta una fecha exigida por el registro operativo.")
        existentes = [r for r in self.snapshot["custodias"] if r["bodega_id"] == bodega["id"]
                      and r["colaborador_codigo"] == persona["codigo"] and r["producto_codigo"] == producto["codigo"]]
        estado = "RECONSTRUIR"
        if existentes:
            estado = "POSIBLE_DUPLICADO"
            self.aviso(fila, "referencia", "Ya existe custodia abierta de este código, colaborador y bodega.",
                       "Contrasta fechas, cantidades y actas. Vincula la custodia existente si corresponde.")
        self.registrar(fila, llave, {"custodias_coincidentes": existentes}, d, estado, "PREPARACIÓN",
                       referencias={"bodega": bodega["id"], "producto": producto["codigo"], "colaborador": persona["codigo"]},
                       razones=["Custodia histórica. Requiere conciliación especializada. No repetir una entrega física."])

    def facturas_paquete(self):
        cabeceras = {}
        for fila in self.por_grupo["facturas"]:
            d = fila.datos
            llave = tuple(clave(d[i]) for i in ("proveedor", "factura"))
            if self.unica(fila, llave, "factura"):
                cabeceras[llave] = fila
        lineas = defaultdict(list)
        for fila in self.por_grupo["factura_lineas"]:
            d = fila.datos
            llave = tuple(clave(d[i]) for i in ("proveedor", "factura"))
            if llave not in cabeceras:
                self.informe(fila, "factura", "No hay cabecera de esta factura en el envío.",
                             "Agrega o corrige la fila en FACTURAS HISTÓRICAS.")
            if self.unica(fila, [*llave, d["producto"]], "producto"):
                self.referencia(fila, "producto", "productos")
                if len(lineas[llave]) == 100:
                    self.informe(fila, "producto", "El comprobante supera 100 líneas.",
                                 "Consulta con IT el tratamiento del comprobante.")
                lineas[llave].append(fila)
        for llave, fila in cabeceras.items():
            d = fila.datos
            detalle = sorted(lineas[llave], key=lambda r: r.datos["producto"])
            if not detalle:
                self.informe(fila, "factura", "La factura carece de líneas.", "Completa DETALLE HISTÓRICO.")
            proveedores = self.catalogos["proveedores"].get(llave[0], [])
            if len(proveedores) > 1 or proveedores and proveedores[0]["nombre"] != d["proveedor"]:
                self.informe(fila, "proveedor", "El nombre de proveedor es ambiguo o difiere del catálogo.",
                             "Verifica el nombre exacto con Compras.")
            total = sum(Decimal(l.datos["precio"]) * l.datos["cantidad"] for l in detalle)
            if total > Decimal("9999999999.99"):
                self.informe(fila, "factura", "El importe excede la capacidad del modelo de compras.",
                             "Verifica las cantidades, moneda y precios con Contabilidad.")
            existente = self.facturas.get(llave)
            estado = "ARCHIVO_HISTÓRICO"
            coincidencias = []
            if existente:
                coincidencias = [r for r in self.snapshot["factura_lineas"] if r["compra_id"] == existente["id"]]
                actuales = {(r["producto_codigo"], r["cantidad"], Decimal(str(r["precio_unitario"]))) for r in coincidencias}
                propuestas = {(r.datos["producto"], r.datos["cantidad"], Decimal(r.datos["precio"])) for r in detalle}
                estado = "EXISTENTE" if existente["fecha_compra"] == d["fecha"] and actuales == propuestas and (
                    not d["pago"] or d["pago"] == existente["forma_pago"]) else "POSIBLE_DUPLICADO"
                if estado == "POSIBLE_DUPLICADO":
                    self.aviso(fila, "factura", "El proveedor y número ya existen, con diferencias en fecha, pago o detalle.",
                               "Concilia el comprobante. Conserva la entrada y el historial ya registrados.")
            if d["contabilizado"] != "Sí":
                self.aviso(fila, "contabilizado", "La incorporación al saldo requiere confirmación.",
                           "Compara la factura con el corte de inventario antes de decidir si se necesita una entrada.")
            self.registrar(fila, [d["proveedor"], d["factura"]], {"factura": existente, "líneas": coincidencias},
                           d | {"líneas": [r.datos for r in detalle], "total_documentado": str(total)},
                           estado, "SOLO_REVISIÓN" if estado == "EXISTENTE" else "PREPARACIÓN",
                           referencias={"proveedor": proveedores[0]["id"] if proveedores else None},
                           razones=["Evidencia histórica. No representa una entrada de mercadería."])

    def validar_requisitos_cuenta(self):
        for fila in self.por_grupo["usuarios"]:
            d = fila.datos
            cuenta = self.buscar("usuarios", d["correo"])
            if not cuenta or cuenta.get("id") is not None:
                continue
            grupo, columna = {2: ("bodega_asignada", "correo"), 3: ("supervision", "supervisor")}.get(
                self.ambito(cuenta), (None, None))
            if grupo and not any(clave(r.datos[columna]) == clave(d["correo"]) for r in self.por_grupo[grupo]):
                self.informe(fila, "rol", "Falta una relación obligatoria para el alta de esta cuenta.",
                             f"Completa la hoja {HOJAS[grupo][1]} con este correo.")

    def revisar_totales(self):
        campos = ("bodega", "producto", "condicion", "corte")
        totales = defaultdict(int)
        primera = {}
        corte_bodega = defaultdict(set)
        for fila in self.por_grupo["inventario"]:
            d = fila.datos
            k = tuple(clave(d[c]) for c in campos)
            totales[k] += d["cantidad"]
            primera.setdefault(k, fila)
            corte_bodega[k[0]].add(d["corte"])
        for k, total in totales.items():
            fila = primera[k]
            if total > 4294967295:
                self.informe(fila, "cantidad", "El conteo total desborda el campo de saldo.",
                             "Verifica el total por bodega, código, condición y corte.")
            if len(corte_bodega[k[0]]) > 1:
                self.informe(fila, "corte", "La bodega tiene varias fechas de conteo en el mismo envío.",
                             "Entrega por separado cada corte físico para que los saldos se puedan conciliar.")
        for fila in self.por_grupo["custodias"]:
            d = fila.datos
            fechas = corte_bodega[clave(d["bodega"])]
            if fechas and d["corte"] not in fechas:
                self.aviso(fila, "corte", "La verificación de custodia y el conteo tienen fechas distintas.",
                           "Concilia las salidas y devoluciones entre ambas fechas antes de usar los totales.")
        for item in self.plan:
            if item["entidad"] == "inventario":
                d = item["propuesto"]
                total = totales[tuple(clave(d[c]) for c in campos)]
                item["referencias"]["conteo_total_condición"] = total
                item["referencias"]["diferencia_vs_instantánea"] = total - item["antes"]["saldo_actual_condición"]

    def ejecutar(self, origen):
        for fila in leer_filas(origen, self.informe):
            self.por_grupo[fila.grupo].append(fila)
        for grupo in ("regiones", "bodegas", "productos", "colaboradores", "usuarios"):
            for fila in self.por_grupo[grupo]:
                self.maestros(fila)
        for grupo in ("regiones_usuarios", "coordinacion", "bodegas_usuarios", "bodega_asignada", "supervision", "permisos"):
            for fila in self.por_grupo[grupo]:
                self.relacion(fila)
        self.validar_requisitos_cuenta()
        for fila in self.por_grupo["inventario"]:
            self.inventario(fila)
        for fila in self.por_grupo["custodias"]:
            self.custodia(fila)
        self.facturas_paquete()
        self.revisar_totales()
        self.revisar_incompatibilidades()
        return Counter(f["estado"] for f in self.plan)

    def revisar_incompatibilidades(self):
        for grupo, problema, fila in captura.diferencias(self.snapshot):
            self.informe(None, grupo, f"{problema}. {fila}",
                         "Compara el esquema con la referencia MySQL. Verifica diferencias antes de generar SQL.")
