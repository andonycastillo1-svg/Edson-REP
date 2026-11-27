"""Pruebas de los archivos distribuidos y las validaciones elementales."""
import sys
import tempfile
import unittest
from collections import defaultdict
from datetime import datetime
from pathlib import Path

from openpyxl import Workbook, load_workbook

HERRAMIENTAS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(HERRAMIENTAS))

from esquema import HOJAS  # noqa: E402
from plantillas import ENCABEZADO, PRIMERA, ULTIMA, generar  # noqa: E402
from validar import convertir, leer_filas  # noqa: E402
import captura  # noqa: E402

PLANTILLAS = HERRAMIENTAS.parent / "plantillas_para_usuarios"


class Kit(unittest.TestCase):
    def test_archivos_distribuidos(self):
        self.assertEqual(9, len(list(PLANTILLAS.glob("*.xlsx"))))
        for grupo, (slug, nombre, _, _, campos, ejemplo) in HOJAS.items():
            with self.subTest(hoja=nombre):
                wb = load_workbook(PLANTILLAS / f"Plantilla_{slug}.xlsx")
                self.assertIn("asterisco", wb["INSTRUCCIONES"]["B4"].value)
                self.assertIn("EJEMPLOS", wb.sheetnames)
                ws = wb[nombre]
                self.assertEqual(tuple(c.encabezado for c in campos), tuple(c.value for c in ws[ENCABEZADO]))
                self.assertEqual("B6", ws.freeze_panes)
                self.assertEqual(1, len(ws.tables))
                self.assertTrue(ws.protection.sheet)
                self.assertTrue(ws["A5"].protection.locked)
                self.assertFalse(ws["A6"].protection.locked)
                self.assertEqual((PRIMERA, ULTIMA), (6, ws.max_row))
                self.assertTrue(all(c.value is None for fila in ws.iter_rows(min_row=6) for c in fila))
                self.assertEqual(len(campos), len(ws.data_validations.dataValidation))
                for regla in ws.data_validations.dataValidation:
                    if regla.type == "list":
                        self.assertIn(regla.formula1, wb.defined_names)
                self.assertFalse(any("contraseña" in c.encabezado.lower() or c.clave.endswith("_id") for c in campos))
                self.assertEqual(len(campos), len(ejemplo))
                wb.close()

    def test_ejemplos_no_entran_a_validacion(self):
        issues = []
        self.assertEqual([], leer_filas(PLANTILLAS, lambda *args: issues.append(args)))
        self.assertEqual(1, len(issues))
        self.assertIn("No hay filas", issues[0][2])

    def test_columnas_dinamicas_se_emiten_desde_snapshot(self):
        datos = defaultdict(list)
        datos["regiones"] = [{"nombre": "Región de QA", "activa": 1}, {"nombre": "Retirada", "activa": 0}]
        datos["roles"] = [{"nombre": "Rol operativo QA", "activo": 1}]
        datos["bodegas"] = [{"nombre": "Bodega QA"}]
        datos["productos"] = [{"codigo": "0008", "categoria": "Categoría QA"}]
        with tempfile.TemporaryDirectory() as tmp:
            generar(tmp, datos)
            wb = load_workbook(Path(tmp) / "Plantilla_Colaboradores.xlsx")
            hoja, rango = next(wb.defined_names["Opciones_regiones"].destinations)
            self.assertEqual("Región de QA", wb[hoja][rango.split(":")[0].replace("$", "")].value)
            wb.close()

    def test_tipos_y_valores_inventados(self):
        ws = Workbook().active
        colaborador = HOJAS["colaboradores"][4][1]
        ws["A1"] = "000045"
        self.assertEqual("000045", convertir(ws["A1"], colaborador))
        ws["A1"] = 45
        with self.assertRaisesRegex(ValueError, "ceros"):
            convertir(ws["A1"], colaborador)
        ws["A1"] = "=1+1"
        with self.assertRaisesRegex(ValueError, "fórmula"):
            convertir(ws["A1"], colaborador)
        fecha = next(c for c in HOJAS["custodias"][4] if c.clave == "entrega")
        ws["A1"] = datetime(2025, 1, 1, 12, 0)
        with self.assertRaisesRegex(ValueError, "sin hora"):
            convertir(ws["A1"], fecha)
        ws["A1"] = datetime(2025, 1, 1)
        self.assertEqual("2025-01-01", convertir(ws["A1"], fecha))
        self.assertIsNone(convertir(ws["B1"], fecha))

    def test_instantanea_truncada(self):
        with tempfile.TemporaryDirectory() as tmp:
            f = Path(tmp) / "incompleta.jsonl"
            f.write_text('{"grupo":"meta","datos":{"readonly":1}}\n', encoding="utf-8")
            with self.assertRaisesRegex(ValueError, "incompleta"):
                captura.leer(f)

    def test_consulta_distribuida_esta_sincronizada(self):
        self.assertEqual(captura.consulta(), (HERRAMIENTAS / "consultar_entorno.sql").read_text(encoding="utf-8"))


if __name__ == "__main__":
    unittest.main()
