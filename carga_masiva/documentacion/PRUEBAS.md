# Resultados de pruebas

Pruebas realizadas con archivos ficticios y MySQL 8.4.13 en un contenedor desechable. Se aplicaron las 68 migraciones de `main` a bases de prueba vacías. La herramienta de conversión nunca se conectó a esas bases. El SQL generado se ejecutó mediante el cliente MySQL para comprobar su comportamiento.

| Verificación | Resultado |
| --- | --- |
| Nueve Excel | Se abrieron todos con openpyxl y se revisaron las hojas, instrucciones, ejemplos, rangos, formatos, tablas filtrables, celdas bloqueadas y listas. |
| Captura y catálogos | Se verificó que las listas del entorno contienen los nombres y códigos obtenidos de la instantánea y que se omiten regiones inactivas. Los ejemplos no se procesaron. |
| Formatos y errores | Se verificaron ceros iniciales, prohibición de fórmulas, fechas inválidas o con hora, cantidades negativas, condición inválida, requeridos, referencias inexistentes, duplicados en cuentas, conteos y custodias, e informe con ubicación y corrección. |
| Comparación y compatibilidad | Se comprobaron registros nuevos, existentes y actualizables, un precio histórico de dos decimales, saldos conocidos y una custodia con fecha desconocida. Una columna adicional real en MySQL bloqueó SQL. Una instantánea truncada también se rechazó. |
| Preparación y catálogos | Se ejecutaron ambos modos. El primero dejó el origen intacto. El modo aprobado creó catálogos elegibles, actualizó un puesto, conservó los códigos y almacenó correctamente caracteres como acentos y apóstrofes. |
| Reejecución | Ejecutar dos veces el mismo archivo dejó un solo lote. Reutilizar una referencia histórica idéntica en otro lote conservó una sola preparación. |
| Reversión | Un cambio intermedio de producto y un conteo con la misma referencia y distinto contenido detuvieron sus lotes. Se revirtieron las escrituras de catálogo anteriores a ambos conflictos. |
| Invariantes operativos | Inventario, movimientos, custodias, facturas, cuentas, alertas y documentos conservaron valores de control antes y después de los SQL ejecutados. Las custodias con fecha desconocida conservaron el dato vacío en preparación. |

## Reproducción local

Desde `carga_masiva`:

```sh
python -m unittest discover -s herramientas_it/pruebas -v
```

La prueba de MySQL exige una base nueva y **desechable** llamada `edson_carga_qa_suite` con las migraciones del proyecto y el directorio de usuarios y colaboradores vacío. Crea `edson_carga_qa_suite_stage`. Use un servidor aislado. El ejecutable indicado recibe el nombre de base como argumento y debe producir valores separados por tabulaciones sin encabezados, en UTF-8. Puede usar un envoltorio que invoque `mysql --login-path=edson_qa --default-character-set=utf8mb4 --batch --raw --skip-column-names "$1"`. Después:

```sh
python herramientas_it/pruebas/mysql_desechable.py --cliente /ruta/mysql-qa
```

phpMyAdmin, la versión del esquema y los catálogos de la instalación real requieren la verificación previa del procedimiento IT.
