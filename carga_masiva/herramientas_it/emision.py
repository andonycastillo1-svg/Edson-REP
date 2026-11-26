"""Emite SQL MySQL revisable. Este módulo carece de conexión y nunca lo ejecuta."""
import hashlib
import json

from validar import clave

MAESTROS = {"regiones": "nombre", "bodegas": "nombre", "productos": "codigo", "colaboradores": "codigo"}
HISTORICOS = {"inventario", "custodias", "facturas"}


def canon(datos):
    return json.dumps(datos, ensure_ascii=False, separators=(",", ":"), sort_keys=True)


def huella(datos):
    return hashlib.sha256(canon(datos).encode("utf-8")).hexdigest()


def texto(valor):
    if valor is None:
        return "NULL"
    if type(valor) is int:
        return str(valor)
    contenido = str(valor).encode("utf-8")
    return f"CONVERT(0x{contenido.hex()} USING utf8mb4) COLLATE utf8mb4_unicode_ci" if contenido else "''"


def identificador(valor):
    return "`" + valor.replace("`", "``") + "`"


def objeto(esquema, tabla):
    return f"{identificador(esquema)}.{identificador(tabla)}"


def mismos(campos):
    comparaciones = []
    for col, valor in campos.items():
        nombre = identificador(col)
        if isinstance(valor, str):
            comparaciones.append(f"BINARY {nombre} <=> BINARY {texto(valor)}")
        else:
            comparaciones.append(f"{nombre} <=> {texto(valor)}")
    return " AND ".join(f"({c})" for c in comparaciones)


def abortar(mensaje, nivel="        "):
    return f"{nivel}SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{mensaje}';"


def escribir_maestro(item, base):
    tabla = item["entidad"]
    columna = MAESTROS[tabla]
    nombre = objeto(base, tabla)
    nuevo = item["propuesto"]
    antes = item["antes"]
    llave = f"{identificador(columna)} = {texto(item['clave'][0])}"
    guardia_base = f"SELECT COUNT(*) INTO coincidencias FROM {nombre} WHERE {llave} FOR UPDATE;"
    stmts = [guardia_base, "IF coincidencias > 1 THEN",
             abortar("Clave ambigua. Repetir la captura y la revision.", "  "), "END IF;"]
    existe_igual = f"EXISTS (SELECT 1 FROM {nombre} WHERE {llave} AND {mismos(nuevo)})"
    stmts.append(f"IF NOT (coincidencias = 1 AND {existe_igual}) THEN")
    if antes:
        campos = {k: antes.get(k) for k in nuevo}
        if "id" in antes:
            campos["id"] = antes["id"]
        guardia = mismos(campos)
        stmts.extend([f"  IF coincidencias <> 1 OR NOT EXISTS (SELECT 1 FROM {nombre} WHERE {llave} AND {guardia}) THEN",
                      abortar("El registro cambio desde la revision. Actualizar la instantanea.", "    "), "  END IF;"])
    else:
        campos = objeto(base, "sistema_campos")
        valores = objeto(base, "sistema_campo_valores")
        stmts.extend([
            f"  IF coincidencias <> 0 OR EXISTS (SELECT 1 FROM {campos} WHERE modulo = {texto(tabla)} AND activo = 1"
            " AND formulario = 1 AND obligatorio = 1 AND archivado_en IS NULL FOR UPDATE)"
            f" OR EXISTS (SELECT 1 FROM {valores} v JOIN {campos} c ON c.id = v.campo_id"
            f" WHERE c.modulo = {texto(tabla)} AND v.registro = {texto(item['clave'][0])} FOR UPDATE) THEN",
            abortar("Alta en conflicto o requiere campos personalizados. Repetir la revision.", "    "), "  END IF;",
        ])
    if tabla == "colaboradores":
        region = {"id": nuevo["region_id"], "nombre": item["referencias"]["region"]}
        if not antes or antes["region_id"] != nuevo["region_id"]:
            region["activa"] = 1
        stmts.extend([f"  IF NOT EXISTS (SELECT 1 FROM {objeto(base, 'regiones')} WHERE {mismos(region)} FOR UPDATE) THEN",
                      abortar("Cambio la region de referencia. Repetir la revision.", "    "), "  END IF;"])
    if antes:
        pares = ", ".join(f"{identificador(k)} = {texto(v)}" for k, v in nuevo.items() if k != columna)
        stmts.append(f"  UPDATE {nombre} SET {pares}, updated_at = CURRENT_TIMESTAMP WHERE {llave};")
    else:
        columnas = ", ".join(identificador(k) for k in nuevo)
        valores = ", ".join(texto(v) for v in nuevo.values())
        stmts.append(f"  INSERT INTO {nombre} ({columnas}, created_at, updated_at) VALUES ({valores}, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);")
    stmts.append("END IF;")
    return stmts


def generar(revision, esquema, aplicar_catalogos=False):
    meta = revision.snapshot["meta"][0]
    base = meta["base"]
    origen = huella([base, meta["servidor"]])
    datos = [i for i in revision.plan if i["destino"] != "SOLO_REVISIÓN"]
    datos.sort(key=lambda i: (list(MAESTROS).index(i["entidad"]) if i["entidad"] in MAESTROS else len(MAESTROS),
                              i["entidad"], canon(i["clave"])))
    lote = huella([origen, aplicar_catalogos, [
        [i["entidad"], i["clave"], i["destino"], i["antes"], i["propuesto"], i["referencias"]]
        for i in datos
    ]])
    lotes, filas, historicos = (objeto(esquema, t) for t in ("edson_lotes", "edson_filas", "edson_referencias"))
    rutina = objeto(esquema, "edson_preparar_" + lote[:20])
    bloqueo = f"edson-carga-{origen[:40]}"
    migraciones = sorted(r["migration"] for r in revision.snapshot["migraciones"])
    o = [
        "-- Edson-REP. Requerir revisión de IT y respaldo antes de importar en phpMyAdmin.",
        f"-- Lote {lote}. Catálogos directos {'habilitados por IT' if aplicar_catalogos else 'deshabilitados'}.",
        "-- Las filas operativas se guardan solo en la base de preparación.",
        "SET NAMES utf8mb4;",
        f"""CREATE TABLE IF NOT EXISTS {lotes} (
    lote BINARY(32) PRIMARY KEY,
    origen BINARY(32) NOT NULL,
    base_origen VARCHAR(64) NOT NULL,
    preparado_por VARCHAR(255) NOT NULL,
    preparado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;""",
        f"""CREATE TABLE IF NOT EXISTS {filas} (
    lote BINARY(32) NOT NULL,
    ordinal INT UNSIGNED NOT NULL,
    entidad VARCHAR(40) NOT NULL,
    destino VARCHAR(40) NOT NULL,
    documento JSON NOT NULL,
    PRIMARY KEY (lote, ordinal),
    FOREIGN KEY (lote) REFERENCES {lotes}(lote)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;""",
        f"""CREATE TABLE IF NOT EXISTS {historicos} (
    origen BINARY(32) NOT NULL,
    entidad VARCHAR(40) NOT NULL,
    clave_historica BINARY(32) NOT NULL,
    huella_datos BINARY(32) NOT NULL,
    lote BINARY(32) NOT NULL,
    PRIMARY KEY (origen, entidad, clave_historica),
    FOREIGN KEY (lote) REFERENCES {lotes}(lote)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;""",
        f"DROP PROCEDURE IF EXISTS {rutina};",
        "DELIMITER $$",
        f"CREATE PROCEDURE {rutina}() SQL SECURITY INVOKER",
        "bloque: BEGIN",
        "  DECLARE bloqueo_obtenido INT DEFAULT 0;",
        "  DECLARE coincidencias BIGINT DEFAULT 0;",
        "  DECLARE EXIT HANDLER FOR SQLEXCEPTION",
        "  BEGIN",
        "    ROLLBACK;",
        f"    IF bloqueo_obtenido = 1 THEN DO RELEASE_LOCK('{bloqueo}'); END IF;",
        "    RESIGNAL;",
        "  END;",
        f"  IF NOT (@@server_uuid <=> {texto(meta['servidor'])}) THEN",
        abortar("El servidor no coincide con la instantanea.", "    "),
        "  END IF;",
        f"  SELECT GET_LOCK('{bloqueo}', 10) INTO bloqueo_obtenido;",
        "  IF NOT (bloqueo_obtenido <=> 1) THEN",
        abortar("Hay otra carga en revision. Reintenta despues.", "    "),
        "  END IF;",
        "  SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;",
        "  START TRANSACTION;",
        f"  IF EXISTS (SELECT 1 FROM {lotes} WHERE lote = 0x{lote}) THEN",
        "    COMMIT;",
        f"    DO RELEASE_LOCK('{bloqueo}');",
        "    LEAVE bloque;",
        "  END IF;",
        f"  IF (SELECT COUNT(*) FROM {objeto(base, 'migrations')}) <> {len(migraciones)} "
        f"OR EXISTS (SELECT 1 FROM {objeto(base, 'migrations')} WHERE migration NOT IN ({', '.join(texto(m) for m in migraciones)})) THEN",
        abortar("Las migraciones cambiaron desde la instantanea.", "    "),
        "  END IF;",
        f"  INSERT INTO {lotes} (lote, origen, base_origen, preparado_por) VALUES (0x{lote}, 0x{origen}, {texto(base)}, USER());",
    ]
    ordinal = 0
    for i in datos:
        entidad = i["entidad"]
        if entidad in HISTORICOS:
            llave = huella([clave(x) for x in i["clave"]])
            propuesta = huella(i["propuesto"])
            filtro = f"origen = 0x{origen} AND entidad = {texto(entidad)} AND clave_historica = 0x{llave}"
            o += [
                f"  IF EXISTS (SELECT 1 FROM {historicos} WHERE {filtro} AND huella_datos <> 0x{propuesta}) THEN",
                abortar("La referencia historica ya se preparo con otros datos. Conciliar antes de reenviar.", "    "),
                f"  ELSEIF NOT EXISTS (SELECT 1 FROM {historicos} WHERE {filtro}) THEN",
                f"    INSERT INTO {historicos} VALUES (0x{origen}, {texto(entidad)}, 0x{llave}, 0x{propuesta}, 0x{lote});",
            ]
            ordinal += 1
            o.append(f"    INSERT INTO {filas} VALUES (0x{lote}, {ordinal}, {texto(entidad)}, {texto(i['destino'])}, {texto(canon(i))});")
            o.append("  END IF;")
            continue
        if i["destino"] == "SQL_CATÁLOGO" and aplicar_catalogos:
            o.extend("  " + linea for linea in escribir_maestro(i, base))
            destino = "CATÁLOGO_APLICADO"
        else:
            destino = "PREPARACIÓN"
        ordinal += 1
        o.append(f"  INSERT INTO {filas} VALUES (0x{lote}, {ordinal}, {texto(entidad)}, {texto(destino)}, {texto(canon(i))});")
    o += ["  COMMIT;", f"  DO RELEASE_LOCK('{bloqueo}');", "END$$",
          "DELIMITER ;", f"CALL {rutina}();", f"DROP PROCEDURE {rutina};", ""]
    return lote, "\n".join(o)
