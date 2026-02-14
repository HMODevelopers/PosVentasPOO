-- Migración: múltiples emisores CFDI por sucursal + default
START TRANSACTION;

SET @idx_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'config_fiscal_emisor'
    AND index_name = 'uk_cfg_emisor_sucursal'
);
SET @sql_drop := IF(@idx_exists > 0,
  'ALTER TABLE config_fiscal_emisor DROP INDEX uk_cfg_emisor_sucursal',
  'SELECT 1'
);
PREPARE stmt_drop FROM @sql_drop;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

ALTER TABLE config_fiscal_emisor
  ADD COLUMN nombre_emisor VARCHAR(100) NOT NULL DEFAULT '' AFTER id_sucursal,
  ADD COLUMN es_default TINYINT(1) NOT NULL DEFAULT 0 AFTER logo_base64,
  ADD INDEX idx_cfg_emisor_sucursal (id_sucursal, activo),
  ADD UNIQUE KEY uk_cfg_emisor_rfc_sucursal (id_sucursal, rfc_emisor);

UPDATE config_fiscal_emisor
SET es_default = 1
WHERE id_sucursal IS NOT NULL;

COMMIT;
