-- Conekta checkout integration tables
-- Apply on database `restaurante`

CREATE TABLE IF NOT EXISTS `conekta_payments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(64) NOT NULL,
  `venta_id` INT DEFAULT NULL,
  `customer_name` VARCHAR(150) DEFAULT NULL,
  `customer_email` VARCHAR(150) DEFAULT NULL,
  `customer_phone` VARCHAR(30) DEFAULT NULL,
  `amount` INT NOT NULL,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'MXN',
  `status` ENUM('pending','paid','expired','canceled','failed') NOT NULL DEFAULT 'pending',
  `payment_method` VARCHAR(32) DEFAULT NULL,
  `conekta_order_id` VARCHAR(64) DEFAULT NULL,
  `conekta_checkout_id` VARCHAR(64) DEFAULT NULL,
  `checkout_url` TEXT,
  `cart_snapshot` JSON NULL,
  `metadata` JSON NULL,
  `raw_order` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reference` (`reference`),
  KEY `idx_venta` (`venta_id`),
  KEY `idx_order` (`conekta_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `conekta_events` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(64) DEFAULT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `conekta_event_id` VARCHAR(64) DEFAULT NULL,
  `payload` JSON NULL,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ref` (`reference`),
  KEY `idx_evt` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

