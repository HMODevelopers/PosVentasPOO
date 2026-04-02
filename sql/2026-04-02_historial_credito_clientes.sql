-- Módulo: Historial de crédito de clientes
-- Fecha: 2026-04-02

-- 1) Permiso ACL
INSERT INTO acl_permisos (clave, nombre, categoria, orden)
SELECT 'ventas.creditos_historial', 'Historial de crédito de clientes', 'Ventas', 40
WHERE NOT EXISTS (
  SELECT 1 FROM acl_permisos WHERE clave = 'ventas.creditos_historial'
);

-- 2) Asignar permiso al rol administrador (id_rol = 1)
INSERT INTO acl_rol_permiso (id_rol, clave)
SELECT 1, 'ventas.creditos_historial'
WHERE NOT EXISTS (
  SELECT 1 FROM acl_rol_permiso WHERE id_rol = 1 AND clave = 'ventas.creditos_historial'
);

-- 3) Índices sugeridos para rendimiento del módulo
CREATE INDEX idx_ventas_credito_fecha_cliente ON ventas (estatus, activo, fecha, id_cliente);
CREATE INDEX idx_ventas_abonos_venta_fecha_activo ON ventas_abonos (id_venta, fecha_abono, activo);
