"""Valida o convierte Excel oficial de Edson-REP sin conectarse a MySQL."""
import argparse
import csv
import json
import os
import sys
from pathlib import Path

import captura
import emision
from validar import Revision

COLUMNAS = ["gravedad", "archivo", "hoja", "fila", "columna", "problema", "corrección_sugerida"]


def celda_csv(valor):
    if isinstance(valor, str) and valor.lstrip().startswith(("=", "+", "-", "@")):
        return "'" + valor
    return valor


def verificar_destino(nombre, base):
    if not nombre or len(nombre) > 64 or not all(c.isascii() and (c.isalnum() or c == "_") for c in nombre):
        raise ValueError("Indica un nombre de base de preparación con letras, números o guion bajo.")
    if nombre.casefold() in {base.casefold(), "mysql", "information_schema", "performance_schema", "sys"}:
        raise ValueError("La base de preparación debe ser distinta de la base operativa y de las bases del sistema.")


def ejecutar(args):
    os.umask(0o077)
    snap = captura.leer(args.instantanea)
    meta = snap["meta"][0]
    if not all(isinstance(meta.get(k), str) and meta[k] for k in ("base", "servidor", "version")):
        raise ValueError("La instantánea carece de la identidad de su base o servidor.")
    if args.accion == "convertir":
        verificar_destino(args.preparacion, meta["base"])
    origen = args.entrada.resolve()
    salida = args.salida.resolve()
    if origen == salida or origen in salida.parents:
        raise ValueError("Usa una carpeta de salida nueva fuera de la entrada.")
    salida.mkdir(parents=True, exist_ok=False)
    revision = Revision(snap)
    estados = revision.ejecutar(origen)
    criticos = sum(f["gravedad"] == "ERROR" for f in revision.problemas)
    with (salida / "informe.csv").open("x", encoding="utf-8-sig", newline="") as informe:
        escritor = csv.DictWriter(informe, fieldnames=COLUMNAS)
        escritor.writeheader()
        for fila in revision.problemas:
            escritor.writerow({k: celda_csv(v) for k, v in fila.items()})
    (salida / "plan.json").write_text(json.dumps(revision.plan, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    resumen = {
        "entorno": meta, "estados": dict(estados), "filas_con_plan": len(revision.plan),
        "errores": criticos, "avisos": len(revision.problemas) - criticos,
        "compatibilidad": "Revisar informe.csv" if criticos else "Compatible con la referencia versionada",
        "sql_generado": False,
    }
    if not criticos and args.accion == "convertir" and any(i["destino"] != "SOLO_REVISIÓN" for i in revision.plan):
        lote, sql = emision.generar(revision, args.preparacion, args.aplicar_catalogos)
        (salida / "carga_revisable.sql").write_text(sql, encoding="utf-8")
        resumen.update(sql_generado=True, lote=lote, catalogos_directos=args.aplicar_catalogos)
    (salida / "resumen.json").write_text(json.dumps(resumen, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Registros revisados {len(revision.plan)}. Errores {criticos}. "
          f"Avisos {resumen['avisos']}. SQL {'generado' if resumen['sql_generado'] else 'sin generar'}.")
    return 2 if criticos else 0


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    comandos = parser.add_subparsers(dest="accion", required=True)
    for nombre in ("validar", "convertir"):
        modo = comandos.add_parser(nombre)
        modo.add_argument("--entrada", required=True, type=Path, help="Carpeta de .xlsx completados o un archivo")
        modo.add_argument("--instantanea", required=True, type=Path, help="Resultado JSONL de consultar_entorno.sql")
        modo.add_argument("--salida", required=True, type=Path, help="Carpeta nueva para los resultados")
        if nombre == "convertir":
            modo.add_argument("--preparacion", required=True, help="Base existente dedicada a preparar la carga")
            modo.add_argument("--aplicar-catalogos", action="store_true",
                              help="Incluye altas y cambios compatibles de catálogos. Requiere revisión y respaldo.")
    args = parser.parse_args()
    try:
        return ejecutar(args)
    except (OSError, ValueError, KeyError) as exc:
        print(f"No se pudo preparar el lote. {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
