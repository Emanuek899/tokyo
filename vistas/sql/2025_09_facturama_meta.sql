-- Metadatos de Facturama en `facturas`
ALTER TABLE `facturas`
  ADD COLUMN `facturama_id` varchar(64) DEFAULT NULL AFTER `id`,
  ADD COLUMN `serie` varchar(10) DEFAULT NULL AFTER `folio`,
  ADD COLUMN `metodo_pago` varchar(5) DEFAULT 'PUE' AFTER `total`,
  ADD COLUMN `forma_pago` varchar(5) DEFAULT '03' AFTER `metodo_pago`,
  ADD COLUMN `uso_cfdi` varchar(5) DEFAULT NULL AFTER `forma_pago`,
  ADD COLUMN `xml_path` varchar(255) DEFAULT NULL AFTER `notas`,
  ADD COLUMN `pdf_path` varchar(255) DEFAULT NULL AFTER `xml_path`;

-- Índices sugeridos
ALTER TABLE `facturas`
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_uuid` (`uuid`);

-- Claves primarias/auto_increment si faltan
ALTER TABLE `clientes_facturacion` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `ux_rfc` (`rfc`);
ALTER TABLE `facturas` ADD PRIMARY KEY (`id`);
ALTER TABLE `factura_detalles` ADD PRIMARY KEY (`id`), ADD KEY `idx_factura` (`factura_id`);
ALTER TABLE `clientes_facturacion` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `facturas` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `factura_detalles` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

