"""Prueba integral. Requiere una base desechable migrada edson_carga_qa_suite."""
import argparse
import csv
import json
import subprocess
import sys
import tempfile
from pathlib import Path

from openpyxl import load_workbook

HERRAMIENTAS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(HERRAMIENTAS))

import captura  # noqa: E402
from esquema import HOJAS  # noqa: E402

BASE = "edson_carga_qa_suite"
STAGE = f"{BASE}_stage"
PLANTILLAS = HERRAMIENTAS.parent / "plantillas_para_usuarios"
VALORES = {
    "regiones": [("Nuevo", "Valle QA", "Activa")],
    "bodegas": [("Nuevo", "Regional QA", "Regional", "Ala norte")],
    "productos": [("Nuevo", "CAS-QA", "Casco d'obra QA", None, "Protección", "Unidad", 18)],
    "colaboradores": [("Actualizar", "000045", "Ana QA", "Coordinación de campo", "Norte QA", "Activo"),
                      ("Nuevo", "000048", "Luis QA", "Técnico", "Norte QA", "Activo")],
    "usuarios": [("Nuevo", "op@example.test", "Almacén QA", "Encargado", "Activa"),
                 ("Nuevo", "sup@example.test", "Supervisión QA", "Supervisor", "Activa")],
    "bodega_asignada": [("op@example.test", "Bodega Central QA")],
    "supervision": [("sup@example.test", "op@example.test", None)],
    "regiones_usuarios": [("sup@example.test", "Norte QA"), ("sup@example.test", "Valle QA")],
    "permisos": [("sup@example.test", "Inspecciones | Registrar inspecciones", "Permitir", "QA")],
    "inventario": [("Bodega Central QA", "BOTA-QA", "Bota de seguridad QA", 4, "Nuevo", "2026-10-10",
                    "QA-0008", None, None, None)],
    "custodias": [("000045", "Ana QA", "Norte QA", "BOTA-QA", "Bota de seguridad QA", "Bodega Central QA",
                   1, "Desconocido", None, "2026-10-10", None, "ACTA-QA", None)],
    "facturas": [("Suministro QA", "FAC-001", "2026-10-01", None, "Sí", "ARCH-QA", None),
                 ("Suministro QA", "FAC-EXISTE", "2026-10-01", None, "Sí", "EXISTENTE-QA", None)],
    "factura_lineas": [("Suministro QA", "FAC-001", "BOTA-QA", 1, "72.51"),
                      ("Suministro QA", "FAC-EXISTE", "BOTA-QA", 1, "72.51")],
}


def preparar_excel(destino, contenido):
    destino.mkdir()
    abiertos = {}
    for tipo, filas in contenido.items():
        slug, hoja, *_ = HOJAS[tipo]
        nombre = f"Plantilla_{slug}.xlsx"
        if nombre not in abiertos:
            abiertos[nombre] = load_workbook(PLANTILLAS / nombre)
        for fila, valores in enumerate(filas, 6):
            for col, valor in enumerate(valores, 1):
                abiertos[nombre][hoja].cell(fila, col, valor)
    for nombre, libro in abiertos.items():
        libro.save(destino / nombre)
        libro.close()
    return destino


def run(cliente):
    def mysql(sql, error=None):
        r = subprocess.run([cliente, BASE], input=sql, capture_output=True, text=True, encoding="utf-8")
        if error:
            assert r.returncode and error in r.stderr, r.stderr
        elif r.returncode:
            raise RuntimeError(r.stderr)
        return r.stdout.strip()

    assert mysql("SELECT DATABASE(); SELECT COUNT(*) FROM colaboradores;") == f"{BASE}\n0", "Se exige una base de prueba nueva"
    mysql(f"""
SET NAMES utf8mb4;
CREATE DATABASE {STAGE} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
INSERT INTO roles(id,nombre,activo) VALUES (2,'Encargado',1),(3,'Supervisor',1);
INSERT INTO regiones(nombre,activa) VALUES ('Norte QA',1),('Retirada QA',0);
INSERT INTO bodegas(nombre,tipo) VALUES ('Bodega Central QA','Principal');
INSERT INTO productos(codigo,nombre,categoria,unidad_medida,vida_util_meses)
VALUES ('BOTA-QA','Bota de seguridad QA','Protección','Par',12);
INSERT INTO colaboradores(codigo,nombre,puesto,estado,region_id)
SELECT '000045','Ana QA','Técnica','Activo',id FROM regiones WHERE nombre='Norte QA';
INSERT INTO inventarios(producto_codigo,bodega_id,stock_tipo,cantidad) VALUES ('BOTA-QA',1,'nuevo',3);
INSERT INTO asignaciones_inventarios(colaborador_codigo,producto_codigo,bodega_id,cantidad_asignada,fecha,estado)
VALUES ('000045','BOTA-QA',1,1,'2026-10-01','Activa');
INSERT INTO proveedores(nombre) VALUES ('Suministro QA');
INSERT INTO compras(proveedor_id,bodega_id,no_factura,fecha_compra,total_factura)
VALUES (1,1,'FAC-EXISTE','2026-10-01',72.51);
INSERT INTO compra_detalles(compra_id,producto_codigo,cantidad,precio_unitario,valor_total)
VALUES (1,'BOTA-QA',1,72.51,72.51);
""")
    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp)

        def snap(nombre):
            ruta = root / f"{nombre}.jsonl"
            ruta.write_text(mysql(captura.consulta()) + "\n", encoding="utf-8")
            return ruta

        def convertir(entrada, snapshot, salida, *, aprobar=False, fracaso=False, accion="convertir"):
            destino = root / salida
            otros = ["--preparacion", STAGE] if accion == "convertir" else []
            if aprobar:
                otros += ["--aplicar-catalogos"]
            r = subprocess.run([sys.executable, str(HERRAMIENTAS / "carga.py"), accion, "--entrada", str(entrada),
                                "--instantanea", str(snapshot), "--salida", str(destino), *otros],
                               capture_output=True, text=True)
            assert r.returncode == (2 if fracaso else 0), r.stdout + r.stderr
            sql = destino / "carga_revisable.sql"
            if fracaso:
                assert not sql.exists()
            return destino, sql

        origen = snap("inicio")
        entrada = preparar_excel(root / "validos", VALORES)
        revisión, _ = convertir(entrada, origen, "lectura", accion="validar")
        plan = json.loads((revisión / "plan.json").read_text())
        estados = {i["estado"] for i in plan}
        assert {"NUEVO", "ACTUALIZAR", "EXISTENTE", "POSIBLE_DUPLICADO", "CONCILIAR"} <= estados
        assert next(i for i in plan if i["entidad"] == "custodias")["propuesto"]["entrega"] is None
        assert next(i for i in plan if i["entidad"] == "inventario")["referencias"]["diferencia_vs_instantánea"] == 1
        print("OK comparación con catálogos, precio histórico, saldo y fecha desconocida")

        contador_operativo = """
SELECT cantidad FROM inventarios WHERE id=1;
SELECT COUNT(*) FROM movimientos;
SELECT COUNT(*) FROM asignaciones_inventarios;
SELECT COUNT(*) FROM compras;
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM alertas_reemplazos_rrhh;
SELECT COUNT(*) FROM asignacion_inventario_archivos;
"""
        estado_anterior = mysql(contador_operativo)
        _, sql = convertir(entrada, origen, "staging")
        mysql(sql.read_text())
        assert mysql("SELECT COUNT(*) FROM productos;") == "1"
        assert mysql(contador_operativo) == estado_anterior
        mysql(sql.read_text())
        assert mysql(f"SELECT COUNT(*) FROM {STAGE}.edson_lotes;") == "1"
        print("OK preparación predeterminada y reejecución idéntica")

        _, sql = convertir(entrada, origen, "autorizado", aprobar=True)
        mysql(sql.read_text())
        mysql(sql.read_text())
        assert mysql(f"SELECT COUNT(*) FROM {STAGE}.edson_referencias;") == "3"
        assert mysql(f"SELECT COUNT(*) FROM {STAGE}.edson_lotes;") == "2"
        assert mysql("SELECT codigo,puesto FROM colaboradores ORDER BY codigo;") == (
            "000045\tCoordinación de campo\n000048\tTécnico")
        assert mysql("SELECT nombre FROM productos WHERE codigo='CAS-QA';") == "Casco d'obra QA"
        assert mysql(contador_operativo) == estado_anterior
        print("OK catálogo aprobado, idempotencia entre lotes y ausencia de efectos operativos")

        actual = snap("actual")
        edit = preparar_excel(root / "desfasados", {"productos": [
            ("Nuevo", "AA-ROLLBACK", "Prueba", None, None, None, 2),
            ("Actualizar", "BOTA-QA", "Nombre propuesto", None, None, None, 12),
        ]})
        _, sql = convertir(edit, actual, "desfasado", aprobar=True)
        mysql("UPDATE productos SET nombre='Cambio intermedio' WHERE codigo='BOTA-QA';")
        mysql(sql.read_text(), "El registro cambio")
        assert mysql("SELECT COUNT(*) FROM productos WHERE codigo='AA-ROLLBACK';") == "0"
        assert mysql(f"SELECT COUNT(*) FROM {STAGE}.edson_lotes;") == "2"
        mysql("UPDATE productos SET nombre='Bota de seguridad QA' WHERE codigo='BOTA-QA';")
        conflicto = preparar_excel(root / "conflicto", {
            "inventario": [VALORES["inventario"][0][:3] + (9,) + VALORES["inventario"][0][4:]],
            "productos": [("Nuevo", "AA-ROLLBACK", "Prueba", None, None, None, 2)],
        })
        _, sql = convertir(conflicto, actual, "referencia_en_conflicto", aprobar=True)
        mysql(sql.read_text(), "La referencia historica ya se preparo con otros datos")
        assert mysql("SELECT COUNT(*) FROM productos WHERE codigo='AA-ROLLBACK';") == "0"
        assert mysql(contador_operativo) == estado_anterior
        print("OK detección de cambios intermedios y reversión ante conflicto histórico")

        erroneos = preparar_excel(root / "erroneos", {
            "colaboradores": [("Nuevo", "000009", None, "Campo", "Norte QA", "Activo"),
                              ("Nuevo", 128, "Ceros", "Campo", "Norte QA", "Activo")],
            "usuarios": [("Nuevo", "mail-inválido", "Nombre", "Supervisor", "Activa"),
                         ("Nuevo", "dup@example.test", "Uno", "Supervisor", "Activa"),
                         ("Nuevo", "dup@example.test", "Dos", "Supervisor", "Activa")],
            "regiones_usuarios": [("sup@example.test", "Retirada QA")],
            "inventario": [VALORES["inventario"][0], VALORES["inventario"][0],
                           ("No existe", "NO-EXISTE", None, 2, "Nuevo", "2026-10-10", "QA", None, None, None),
                           ("Bodega Central QA", "BOTA-QA", None, -2, "Inventada", "2026-99-10", "QA", None, None, None)],
            "custodias": [VALORES["custodias"][0], VALORES["custodias"][0],
                          ("NOEXISTE", None, None, "BOTA-QA", None, "Bodega Central QA",
                           1, "Desconocido", None, "2026-10-10", None, "OTRO", None)],
            "bodegas": [("Nuevo", "Regional QA", "Regional", "Difiere")],
        })
        carpeta, _ = convertir(erroneos, actual, "errores", fracaso=True)
        with (carpeta / "informe.csv").open(encoding="utf-8-sig") as stream:
            fallos = list(csv.DictReader(stream))
        columnas = {f["columna"] for f in fallos}
        for esperada in ("Nombre completo *", "Código de colaborador *", "Correo electrónico *", "Cantidad *",
                         "Fecha del conteo *", "Condición *", "Referencia de respaldo *", "Nombre de bodega *"):
            assert esperada in columnas, (esperada, columnas)
        assert any("colaboradores" in r["problema"] and "valor no existe" in r["problema"] for r in fallos)
        assert any("repetido" in r["problema"] for r in fallos)
        print("OK errores con ubicación y acción, bloqueo total de SQL")

        mysql("ALTER TABLE bodegas ADD columna_qa VARCHAR(10) NULL;")
        incompatible = snap("incompatible")
        carpeta, _ = convertir(edit, incompatible, "compatibilidad", fracaso=True)
        assert "columna_qa" in (carpeta / "informe.csv").read_text(encoding="utf-8-sig")
        print("OK detección de diferencia real del esquema MySQL")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--cliente", required=True, help="Ejecutable mysql o un envoltorio. Recibe el nombre de la base como argumento.")
    args = parser.parse_args()
    run(args.cliente)
