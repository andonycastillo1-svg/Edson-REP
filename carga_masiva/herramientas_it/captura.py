"""Lectura de una instantánea JSONL de MySQL. La consulta nunca usa SELECT *."""
import json
from collections import defaultdict
from pathlib import Path

TABLAS = {
    "regiones": "id nombre activa coordinador_id",
    "bodegas": "id nombre tipo ubicacion",
    "colaboradores": "codigo nombre puesto estado region_id",
    "productos": "codigo nombre descripcion categoria unidad_medida vida_util_meses",
    "roles": "id nombre ambito_operativo activo",
    "usuarios": "id name email role_id acceso_activo bodega_id coordinador_id",
    "supervisores": "supervisor_id almacenista_id",
    "region_usuario": "user_id region_id",
    "coordinadores": "user_id region_id",
    "bodega_usuario": "user_id bodega_id opera_inventario opera_requerimientos",
    "reglas": "user_id permiso permitido",
    "saldos": "bodega_id producto_codigo stock_tipo cantidad",
    "custodias": "id colaborador_codigo producto_codigo bodega_id cantidad_asignada fecha stock_tipo",
    "proveedores": "id nombre",
    "facturas": "id proveedor_id no_factura fecha_compra forma_pago",
    "factura_lineas": "compra_id producto_codigo cantidad precio_unitario",
    "campos": "id modulo clave etiqueta obligatorio formulario activo archivado_en",
    "claves_personalizadas": "campo_id registro",
    "procesos": "nombre activo",
}
ORIGEN = {
    "usuarios": "users", "supervisores": "almacenista_supervisores",
    "region_usuario": "region_usuario_operativa", "coordinadores": "region_coordinador_operativo",
    "bodega_usuario": "bodega_usuario_operativa", "reglas": "sistema_permisos_usuario",
    "saldos": "inventarios", "custodias": "asignaciones_inventarios",
    "facturas": "compras", "factura_lineas": "compra_detalles",
    "campos": "sistema_campos", "claves_personalizadas": "sistema_campo_valores",
    "procesos": "procesos_inspeccion",
}
COMPATIBILIDAD = sorted({ORIGEN.get(tabla, tabla) for tabla in TABLAS} | {
    "movimientos", "asignacion_movimientos", "inventario_existencias", "inventario_lotes_operativos",
    "entregas_inventario", "asignacion_periodos", "sistema_ajustes", "region_coordinador_historial",
    "asignacion_estado_historiales", "compra_consolidado", "compra_detalle_consolidado", "migrations",
})
ARCHIVO_ESQUEMA = Path(__file__).with_name("esquema_mysql.json")


def proyeccion(clase, columnas, origen):
    separadas = columnas.split()
    campos = ", ".join(f"'{col}', `{col}`" for col in separadas)
    return f"SELECT JSON_OBJECT('grupo', '{clase}', 'datos', JSON_OBJECT({campos})) FROM {origen};"


def consulta():
    nombres = ", ".join(f"'{x}'" for x in COMPATIBILIDAD)
    salidas = [
        "SET NAMES utf8mb4;",
        "SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ;",
        "START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY;",
        """SELECT JSON_OBJECT('grupo', 'meta', 'datos', JSON_OBJECT(
            'base', DATABASE(), 'servidor', @@server_uuid, 'version', VERSION(),
            'fecha', UTC_TIMESTAMP(), 'readonly', 1));""",
        proyeccion("columnas",
            "TABLE_NAME COLUMN_NAME COLUMN_TYPE IS_NULLABLE COLUMN_DEFAULT EXTRA COLLATION_NAME",
            f"information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({nombres})"),
        proyeccion("indices", "TABLE_NAME INDEX_NAME NON_UNIQUE COLUMN_NAME SEQ_IN_INDEX SUB_PART",
            f"information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({nombres})"),
        proyeccion("relaciones", "TABLE_NAME CONSTRAINT_NAME COLUMN_NAME REFERENCED_TABLE_NAME REFERENCED_COLUMN_NAME",
            f"information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({nombres}) "
            "AND REFERENCED_TABLE_NAME IS NOT NULL"),
        proyeccion("reglas_foraneas", "TABLE_NAME REFERENCED_TABLE_NAME CONSTRAINT_NAME UPDATE_RULE DELETE_RULE",
            f"information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN ({nombres})"),
        proyeccion("tablas", "TABLE_NAME ENGINE",
            f"information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({nombres})"),
        proyeccion("disparadores", "EVENT_OBJECT_TABLE ACTION_TIMING EVENT_MANIPULATION TRIGGER_NAME",
            f"information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE IN ({nombres})"),
        proyeccion("migraciones", "migration", "migrations"),
        proyeccion("general", "valor", "sistema_ajustes WHERE clave = 'general'"),
    ]
    for clase, columnas in TABLAS.items():
        filtro = " WHERE estado = 'Activa'" if clase == "custodias" else ""
        salidas.append(proyeccion(clase, columnas, ORIGEN.get(clase, clase) + filtro))
    return "\n".join([*salidas, "COMMIT;", "SELECT JSON_OBJECT('grupo', 'fin', 'datos', JSON_OBJECT('completo', 1));", ""])


def leer(ruta):
    datos = defaultdict(list)
    with Path(ruta).open(encoding="utf-8-sig") as archivo:
        for numero, linea in enumerate(archivo, 1):
            if linea.strip():
                try:
                    item = json.loads(linea)
                    grupo, registro = item["grupo"], item["datos"]
                    if not isinstance(grupo, str) or not isinstance(registro, dict):
                        raise ValueError()
                    datos[grupo].append(registro)
                except (ValueError, TypeError, KeyError) as exc:
                    raise ValueError(f"Instantánea inválida en línea {numero}. Usa mysql --batch --raw --skip-column-names.") from exc
    if len(datos["meta"]) != 1 or datos["meta"][0].get("readonly") != 1:
        raise ValueError("Falta la cabecera de la instantánea de solo lectura.")
    if datos["fin"] != [{"completo": 1}]:
        raise ValueError("La instantánea está incompleta. Comprueba la salida de MySQL y vuelve a extraerla.")
    return datos


def general(datos):
    valor = next(iter(datos["general"]), {}).get("valor") or {}
    return json.loads(valor) if isinstance(valor, str) else valor


def firma(datos):
    # Comparar firmas semánticas. Los nombres de los índices pueden variar entre instalaciones.
    indices = defaultdict(list)
    for fila in datos["indices"]:
        if not int(fila["NON_UNIQUE"]):
            indices[fila["TABLE_NAME"], fila["INDEX_NAME"]].append(fila)
    unicos = []
    for (tabla, nombre), filas in indices.items():
        partes = sorted(filas, key=lambda fila: int(fila["SEQ_IN_INDEX"]))
        unicos.append([tabla, "PK" if nombre == "PRIMARY" else "UQ",
                       [[fila["COLUMN_NAME"], fila["SUB_PART"]] for fila in partes]])
    reglas = {(r["TABLE_NAME"], r["CONSTRAINT_NAME"]): (r["UPDATE_RULE"], r["DELETE_RULE"])
              for r in datos["reglas_foraneas"]}
    relaciones = []
    for fila in datos["relaciones"]:
        relaciones.append({**{k: v for k, v in fila.items() if k != "CONSTRAINT_NAME"},
                           "reglas": reglas.get((fila["TABLE_NAME"], fila["CONSTRAINT_NAME"]))})
    grupos = {
        "columnas": datos["columnas"],
        "unicos": unicos,
        "relaciones": relaciones,
        "tablas": datos["tablas"],
        "disparadores": datos["disparadores"],
        "migraciones": [fila["migration"] for fila in datos["migraciones"]],
    }
    return {clave: sorted(filas, key=lambda fila: json.dumps(fila, sort_keys=True)) for clave, filas in grupos.items()}


def diferencias(datos):
    esperado = json.loads(ARCHIVO_ESQUEMA.read_text(encoding="utf-8"))
    actual = firma(datos)
    resultado = []
    for grupo, filas in esperado.items():
        referencia = {json.dumps(fila, ensure_ascii=False, sort_keys=True) for fila in filas}
        recibido = {json.dumps(fila, ensure_ascii=False, sort_keys=True) for fila in actual[grupo]}
        resultado.extend((grupo, "Falta o cambió", fila) for fila in sorted(referencia - recibido))
        resultado.extend((grupo, "Adicional o diferente", fila) for fila in sorted(recibido - referencia))
    if not str(datos["meta"][0].get("version", "")).split(".")[0] == "8":
        resultado.append(("motor", "Versión sin verificar", str(datos["meta"][0].get("version"))))
    return resultado
