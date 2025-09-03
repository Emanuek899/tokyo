-- Facturación: tablas y vistas
-- Ejecutar en la BD `restaurante`

CREATE TABLE IF NOT EXISTS clientes_facturacion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rfc VARCHAR(20) NOT NULL UNIQUE,
  razon_social VARCHAR(200) NOT NULL,
  correo VARCHAR(150) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  calle VARCHAR(150) DEFAULT NULL,
  numero_ext VARCHAR(20) DEFAULT NULL,
  numero_int VARCHAR(20) DEFAULT NULL,
  colonia VARCHAR(120) DEFAULT NULL,
  municipio VARCHAR(120) DEFAULT NULL,
  estado VARCHAR(120) DEFAULT NULL,
  pais VARCHAR(100) DEFAULT 'México',
  cp VARCHAR(10) DEFAULT NULL,
  regimen VARCHAR(100) DEFAULT NULL,
  uso_cfdi VARCHAR(10) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS facturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  cliente_id INT NOT NULL,
  folio VARCHAR(50) DEFAULT NULL,
  uuid VARCHAR(64) DEFAULT NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  impuestos DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  fecha_emision DATETIME DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('generada','cancelada') DEFAULT 'generada',
  notas TEXT NULL,
  KEY idx_fact_ticket (ticket_id),
  KEY idx_fact_cliente (cliente_id),
  CONSTRAINT fk_fact_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id),
  CONSTRAINT fk_fact_cliente FOREIGN KEY (cliente_id) REFERENCES clientes_facturacion (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS factura_detalles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  factura_id INT NOT NULL,
  ticket_detalle_id INT NULL,
  producto_id INT NULL,
  descripcion VARCHAR(255) NULL,
  cantidad INT NOT NULL DEFAULT 1,
  precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
  importe DECIMAL(10,2) NOT NULL DEFAULT 0,
  KEY idx_fd_fact (factura_id),
  CONSTRAINT fk_fd_fact FOREIGN KEY (factura_id) REFERENCES facturas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vista de facturas con cliente y ticket
CREATE OR REPLACE VIEW vista_facturas AS
SELECT f.id AS factura_id,
       f.folio,
       f.uuid,
       f.subtotal,
       f.impuestos,
       f.total,
       f.fecha_emision,
       f.estado,
       f.ticket_id,
       t.folio AS ticket_folio,
       t.total AS ticket_total,
       c.id AS cliente_id,
       c.rfc,
       c.razon_social,
       c.correo,
       c.telefono
FROM facturas f
JOIN tickets t ON t.id = f.ticket_id
JOIN clientes_facturacion c ON c.id = f.cliente_id;

-- Vista de detalles de factura con producto
CREATE OR REPLACE VIEW vista_factura_detalles AS
SELECT fd.id,
       fd.factura_id,
       fd.ticket_detalle_id,
       fd.producto_id,
       COALESCE(fd.descripcion, p.nombre) AS descripcion,
       fd.cantidad,
       fd.precio_unitario,
       fd.importe
FROM factura_detalles fd
LEFT JOIN productos p ON p.id = fd.producto_id;

