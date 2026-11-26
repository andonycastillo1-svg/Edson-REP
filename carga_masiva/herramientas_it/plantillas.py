"""Construye exclusivamente las plantillas vacías para distribuir."""
import argparse
import json
from collections import defaultdict
from datetime import date
from pathlib import Path

from openpyxl import Workbook
from openpyxl.comments import Comment
from openpyxl.formatting.rule import FormulaRule
from openpyxl.styles import Alignment, Font, PatternFill, Protection
from openpyxl.utils import get_column_letter
from openpyxl.workbook.defined_name import DefinedName
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.worksheet.table import Table, TableStyleInfo

import captura
from esquema import HOJAS, LISTAS

ENCABEZADO = 5
PRIMERA = ENCABEZADO + 1
ULTIMA = 1005
MARINO, AZUL, TEAL, TINTA = "103652", "135979", "08738B", "18374F"
CLARO, BLANCO, AMARILLO = "EEF6FA", "FFFFFF", "FFF2D5"
PERMISOS = json.loads(Path(__file__).with_name("permisos.json").read_text(encoding="utf-8"))
LISTAS_ENTORNO = {"bodegas", "regiones", "roles", "categorias", "productos"}


def color(hexadecimal):
    return PatternFill("solid", fgColor=hexadecimal)


def escribir(ws, fila, col, valor):
    celda = ws.cell(fila, col, valor)
    if isinstance(valor, str):
        celda.data_type = "s"
    return celda


def banda(ws, titular, subtitulo, columnas):
    ws.sheet_view.showGridLines = False
    ws.sheet_properties.pageSetUpPr.fitToPage = True
    ws.merge_cells(start_row=1, start_column=1, end_row=1, end_column=columnas)
    ws.merge_cells(start_row=2, start_column=1, end_row=2, end_column=columnas)
    for fila, texto, fondo, tam in [(1, titular, MARINO, 17), (2, subtitulo, AZUL, 11)]:
        celda = escribir(ws, fila, 1, texto)
        celda.font = Font(name="Aptos", size=tam, color=BLANCO, bold=fila == 1)
        celda.fill = color(fondo)
        celda.alignment = Alignment(vertical="center", indent=1, wrap_text=True)
    ws.row_dimensions[1].height = 32
    ws.row_dimensions[2].height = 33


def proteger(ws):
    ws.protection.sheet = True
    ws.protection.autoFilter = False
    ws.protection.selectUnlockedCells = False
    ws.protection.selectLockedCells = False


def instrucciones(wb, nombre, hojas, vigente):
    ws = wb.active
    ws.title = "INSTRUCCIONES"
    banda(ws, f"Edson-REP  /  {nombre.replace('_', ' ')}", "Grupo NetSolutions · Recopilación de datos", 4)
    for col in "ABCD":
        ws.column_dimensions[col].width = 25
    mas = [
        ("Antes de comenzar", "El asterisco * indica un campo obligatorio. Escribe en las celdas claras de las hojas de captura. La primera fila de la tabla contiene los encabezados."),
        ("Listas desplegables", "Si aparece una flecha, elige el valor de la lista. Para nombres nuevos, usa la misma escritura que en el archivo de catálogo que acompaña el envío. Si una lista está vacía, solicita a IT el catálogo vigente."),
        ("Códigos y fechas", "Copia los códigos con sus ceros iniciales. Escribe fechas con año, mes y día, por ejemplo 2026-11-25, o usa una fecha de Excel. Usa un punto para los decimales en precios escritos como texto."),
        ("Nuevos y cambios", "Cuando la hoja incluya Tipo de solicitud, usa Nuevo para un alta, Actualizar para proponer cambios y Verificar para comparar con el registro actual. Los campos opcionales vacíos en una actualización conservan el valor anterior."),
        ("Varias líneas", "Usa una fila por registro y una fila por cada relación o artículo de una factura. No juntes varias regiones, bodegas o códigos en una sola celda."),
        ("Ejemplos y capacidad", "EJEMPLOS contiene datos ficticios. Completa únicamente las hojas de captura. Hay 1.000 filas por hoja. Para más filas, usa otra copia y agrega un sufijo al nombre del archivo."),
        ("Entrega", "Guarda en formato .xlsx y envía a IT por el canal interno. Conserva los nombres de las hojas, el orden de las columnas y las referencias de respaldo."),
    ]
    if nombre == "Usuarios" or nombre == "Relaciones":
        mas.append(("Acceso", "Las cuentas, relaciones y permisos se revisan por separado. IT aplica las aprobaciones en Edson-REP y gestiona las credenciales por el canal oficial. La plantilla no solicita contraseñas."))
    if nombre == "Inventario_Inicial":
        mas.append(("Conteo físico", "Incluye solo lo que está en bodega. Los bienes bajo custodia se anotan en Artículos asignados. Si separas lotes, usa un folio distinto por lote. Coordina una sola fecha de corte con RR. HH."))
    if nombre == "Articulos_Asignados":
        mas.append(("Custodia histórica", "Usa el folio que respalda cada entrega. Deja en blanco la fecha o vida restante desconocida. La fecha de verificación es la del conteo actual y jamás sustituye la fecha de entrega."))
    if nombre == "Compras_Historicas_Opcional":
        mas.append(("Uso opcional", "Completa estas dos hojas únicamente cuando IT y Contabilidad soliciten el archivo histórico. Indica si la compra ya está reflejada en existencias. Entrega los comprobantes por el canal autorizado."))
    mas.append(("Catálogos", "Listas del entorno extraídas por IT." if vigente else
        "Edición base. Incluye las opciones del sistema. IT puede emitir una copia con nombres y códigos vigentes de MySQL."))
    for hoja in hojas:
        mas.append((hoja[1], f"{hoja[2]}. {hoja[3]}"))
    for i, (titulo, parrafo) in enumerate(mas, 4):
        ws.merge_cells(start_row=i, start_column=2, end_row=i, end_column=4)
        a = escribir(ws, i, 1, titulo)
        b = escribir(ws, i, 2, parrafo)
        a.font = Font(name="Aptos", bold=True, color=AZUL, size=11)
        b.font = Font(name="Aptos", color=TINTA, size=11)
        for celda in (a, b):
            celda.fill = color(CLARO if i % 2 else BLANCO)
            celda.alignment = Alignment(vertical="center", wrap_text=True, indent=1)
        ws.row_dimensions[i].height = 47
    ws.freeze_panes = "B4"
    proteger(ws)


def listas_del_entorno(datos):
    valores = {**LISTAS, **{clave: [] for clave in LISTAS_ENTORNO},
               "permisos": sorted(meta["etiqueta"] for meta in PERMISOS.values())}
    if datos:
        valores["regiones"] = [r["nombre"] for r in datos["regiones"] if r["activa"]]
        valores["bodegas"] = [r["nombre"] for r in datos["bodegas"]]
        valores["roles"] = [r["nombre"] for r in datos["roles"] if r["activo"]]
        valores["productos"] = [r["codigo"] for r in datos["productos"]]
        valores["categorias"] = [r["categoria"] for r in datos["productos"] if r["categoria"]]
        valores["categorias"] += captura.general(datos).get("categorias") or []
    return valores


def catalogos(wb, hojas, valores):
    ws = wb.create_sheet("CATÁLOGOS")
    ws.sheet_state = "hidden"
    listas = sorted({c.lista for hoja in hojas for c in hoja[4] if c.lista})
    for columna, clave in enumerate(listas, 1):
        opciones = list(dict.fromkeys(valores[clave]))
        escribir(ws, 1, columna, clave)
        for fila, dato in enumerate(opciones, 2):
            escribir(ws, fila, columna, dato)
        letra = get_column_letter(columna)
        wb.defined_names.add(DefinedName(
            f"Opciones_{clave}", attr_text=f"'CATÁLOGOS'!${letra}$2:${letra}${max(2, len(opciones) + 1)}"))
    proteger(ws)


def validacion(campo, opciones):
    if campo.lista and opciones[campo.lista]:
        valida = DataValidation(type="list", formula1=f"Opciones_{campo.lista}", showDropDown=False,
                                errorStyle="warning" if campo.lista in LISTAS_ENTORNO else "stop")
    elif campo.tipo.startswith("entero"):
        valida = DataValidation(type="whole", operator="between", formula1=0 if campo.tipo == "entero" else 1,
                                formula2=campo.limite if campo.limite != 150 else 4294967295)
    elif campo.tipo == "fecha":
        # Excel también admite texto ISO. IT requiere una fecha válida al revisar el archivo.
        valida = DataValidation(type="custom",
            formula1="OR(ISNUMBER({0}),AND(LEN({0})=10,MID({0},5,1)=\"-\",MID({0},8,1)=\"-\"))")
    elif campo.tipo == "precio":
        valida = DataValidation(type="decimal", operator="between", formula1=0, formula2="9999999999.99")
    else:
        valida = DataValidation(type="textLength", operator="lessThanOrEqual", formula1=campo.limite)
    valida.allowBlank = True
    valida.showErrorMessage = True
    valida.errorTitle = "Revisa el dato"
    valida.error = "Usa el formato indicado o un valor vigente. Las altas de catálogo se revisan con IT."
    if campo.ayuda:
        valida.showInputMessage = True
        valida.promptTitle = campo.titulo[:32]
        valida.prompt = campo.ayuda[:230]
    return valida


def captura_hoja(wb, hoja, opciones, ordinal):
    _, titulo, _, uso, campos, _ = hoja
    ws = wb.create_sheet(titulo)
    banda(ws, titulo.title(), uso, len(campos))
    ws.freeze_panes = "B6"
    ws.sheet_properties.tabColor = TEAL
    ws.row_dimensions[ENCABEZADO].height = 42
    final = get_column_letter(len(campos))
    for i, campo in enumerate(campos, 1):
        letra = get_column_letter(i)
        ws.column_dimensions[letra].width = campo.ancho
        celda = escribir(ws, ENCABEZADO, i, campo.encabezado)
        celda.fill = color(MARINO if campo.requerido else AZUL)
        celda.font = Font(name="Aptos", size=11, bold=True, color=BLANCO)
        celda.alignment = Alignment(wrap_text=True, vertical="center", indent=1)
        if campo.ayuda:
            celda.comment = Comment(campo.ayuda, "Edson-REP")
        rango = f"{letra}{PRIMERA}:{letra}{ULTIMA}"
        valida = validacion(campo, opciones)
        if valida.type == "custom":
            valida.formula1 = valida.formula1.format(f"{letra}{PRIMERA}")
        ws.add_data_validation(valida)
        valida.add(rango)
        if campo.requerido:
            ws.conditional_formatting.add(rango, FormulaRule(
                formula=[f'AND(COUNTA($A{PRIMERA}:${final}{PRIMERA})>0,{letra}{PRIMERA}="")'],
                fill=color(AMARILLO)))
        for fila in range(PRIMERA, ULTIMA + 1):
            c = ws.cell(fila, i)
            c.protection = Protection(locked=False)
            c.font = Font(name="Aptos", size=11, color=TINTA)
            c.number_format = {"fecha": "yyyy-mm-dd", "precio": "0.00", "entero": "0",
                               "entero_positivo": "0"}.get(campo.tipo, "@")
    tabla = Table(displayName=f"Captura{ordinal}", ref=f"A{ENCABEZADO}:{final}{ULTIMA}")
    tabla.tableStyleInfo = TableStyleInfo(name="TableStyleMedium2", showRowStripes=True)
    ws.add_table(tabla)
    proteger(ws)


def ejemplos(wb, hojas):
    ws = wb.create_sheet("EJEMPLOS")
    ancho = max(len(hoja[4]) for hoja in hojas)
    banda(ws, "Ejemplos ficticios", "Consulta esta hoja para orientarte. Completa los datos reales en las hojas de captura.", ancho)
    for i in range(1, ancho + 1):
        ws.column_dimensions[get_column_letter(i)].width = max(
            (h[4][i - 1].ancho for h in hojas if len(h[4]) >= i), default=25)
    for numero, hoja in enumerate(hojas):
        _, titulo, _, _, campos, muestra = hoja
        inicio = 5 + 4 * numero
        escribir(ws, inicio, 1, titulo).font = Font(name="Aptos", color=TEAL, bold=True, size=12)
        ws.row_dimensions[inicio + 1].height = 37
        for i, (campo, valor) in enumerate(zip(campos, muestra, strict=True), 1):
            c = escribir(ws, inicio + 1, i, campo.encabezado)
            c.fill = color(AZUL)
            c.font = Font(name="Aptos", color=BLANCO, bold=True)
            c.alignment = Alignment(vertical="center", wrap_text=True)
            c = escribir(ws, inicio + 2, i, valor)
            c.fill = color(CLARO)
            c.font = Font(name="Aptos", color=TINTA)
    proteger(ws)


def generar(destino, datos=None):
    destino = Path(destino)
    destino.mkdir(parents=True, exist_ok=True)
    opciones = listas_del_entorno(datos)
    paquetes = defaultdict(list)
    for hoja in HOJAS.values():
        paquetes[hoja[0]].append(hoja)
    for nombre, hojas in paquetes.items():
        wb = Workbook()
        wb.properties.creator = "Grupo NetSolutions"
        wb.properties.title = f"Edson-REP · {nombre.replace('_', ' ')}"
        instrucciones(wb, nombre, hojas, bool(datos))
        for i, hoja in enumerate(hojas, 1):
            captura_hoja(wb, hoja, opciones, i)
        ejemplos(wb, hojas)
        catalogos(wb, hojas, opciones)
        wb.save(destino / f"Plantilla_{nombre}.xlsx")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--salida", required=True, type=Path)
    parser.add_argument("--instantanea", type=Path)
    args = parser.parse_args()
    generar(args.salida, captura.leer(args.instantanea) if args.instantanea else None)
