-- Módulo: Facturación múltiple
-- Fecha: 2026-04-06

-- 1) Permiso ACL para menú de ventas
INSERT INTO acl_permisos (clave, nombre, categoria, orden)
SELECT 'ventas.facturacion_multiple', 'Facturación múltiple', 'Ventas', 45
WHERE NOT EXISTS (
  SELECT 1 FROM acl_permisos WHERE clave = 'ventas.facturacion_multiple'
);

-- 2) Asignar permiso al rol administrador
INSERT INTO acl_rol_permiso (id_rol, clave)
SELECT 1, 'ventas.facturacion_multiple'
WHERE NOT EXISTS (
  SELECT 1 FROM acl_rol_permiso WHERE id_rol = 1 AND clave = 'ventas.facturacion_multiple'
);

-- 3) Trazabilidad CFDI <-> tickets múltiples
CREATE TABLE IF NOT EXISTS ventas_cfdi_tickets (
  id_cfdi_ticket BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_cfdi BIGINT UNSIGNED NOT NULL,
  id_venta BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_cfdi_ticket),
  UNIQUE KEY uk_cfdi_venta (id_cfdi, id_venta),
  UNIQUE KEY uk_venta_unica (id_venta),
  KEY idx_cfdi (id_cfdi),
  KEY idx_venta (id_venta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
