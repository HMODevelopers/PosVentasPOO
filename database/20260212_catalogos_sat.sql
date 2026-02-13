CREATE TABLE IF NOT EXISTS cat_sat_uso_cfdi (
  id_uso_cfdi INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave_uso_cfdi VARCHAR(10) NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_clave_uso_cfdi (clave_uso_cfdi),
  KEY idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cat_sat_regimen_fiscal (
  id_regimen_fiscal INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave_regimen VARCHAR(10) NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_clave_regimen (clave_regimen),
  KEY idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cat_sat_moneda (
  id_moneda INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave_moneda VARCHAR(10) NOT NULL,
  descripcion VARCHAR(120) NOT NULL,
  decimales TINYINT UNSIGNED NOT NULL DEFAULT 2,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_clave_moneda (clave_moneda),
  KEY idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes_sat (
  id_cliente_sat INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rfc VARCHAR(13) NOT NULL,
  razon_social VARCHAR(200) NOT NULL,
  id_regimen_fiscal INT UNSIGNED NULL,
  id_uso_cfdi INT UNSIGNED NULL,
  codigo_postal VARCHAR(10) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rfc (rfc),
  KEY idx_activo (activo),
  KEY idx_regimen (id_regimen_fiscal),
  KEY idx_uso (id_uso_cfdi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
