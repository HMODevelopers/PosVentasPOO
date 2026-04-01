-- Agrega nuevo tipo de operación Pago y soporte de forma de pago para clasificar dashboard
ALTER TABLE prestamos
  MODIFY COLUMN tipo_operacion ENUM('Prestamo','Disposicion','Pago') NOT NULL;

ALTER TABLE prestamos
  ADD COLUMN id_forma_pago INT NULL AFTER estatus,
  ADD INDEX idx_prestamos_id_forma_pago (id_forma_pago),
  ADD CONSTRAINT fk_prestamos_forma_pago
    FOREIGN KEY (id_forma_pago) REFERENCES formas_pago(id_forma_pago);
