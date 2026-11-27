-- Ejecutar desde la base de preparación, una vez importado el lote aprobado.
SELECT HEX(lote) AS lote, base_origen, preparado_por, preparado_en
FROM edson_lotes ORDER BY preparado_en DESC;
SELECT HEX(lote) AS lote, entidad, destino, COUNT(*) AS registros
FROM edson_filas GROUP BY lote, entidad, destino ORDER BY lote, entidad;
SELECT HEX(lote) AS lote, ordinal, entidad, destino,
       JSON_EXTRACT(documento, '$.clave') AS referencia,
       JSON_EXTRACT(documento, '$."revisión"') AS revision
FROM edson_filas ORDER BY lote, ordinal;
SELECT HEX(lote) AS lote, entidad, COUNT(*) AS referencias_historicas
FROM edson_referencias GROUP BY lote, entidad ORDER BY lote, entidad;
