-- ============================================================================
-- TradeERP — Phase 7 schema additions (Sales)
--   sales_quotations / sales_orders / sales_invoices + item lines.
--   Invoices issue stock (COGS) and post receivables/cash + tax.
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `sales_quotations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `quotation_no` VARCHAR(60) NOT NULL DEFAULT '',
  `quotation_date` DATE NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `narration` VARCHAR(500) NOT NULL DEFAULT '',
  `status` ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  `cancelled_by` INT UNSIGNED NULL,
  `cancelled_at` DATETIME NULL,
  `cancelled_reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sq_company_date` (`company_id`, `quotation_date`),
  KEY `idx_sq_customer` (`customer_id`),
  CONSTRAINT `fk_sq_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_sq_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_quotation_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quotation_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `uom_id` INT UNSIGNED NULL,
  `quantity` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `base_qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `rate` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `discount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `tax_id` INT UNSIGNED NULL,
  `tax_rate` DECIMAL(7,4) NOT NULL DEFAULT 0,
  `tax_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_sqi_quotation` (`quotation_id`),
  CONSTRAINT `fk_sqi_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `sales_quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sqi_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `order_no` VARCHAR(60) NOT NULL DEFAULT '',
  `order_date` DATE NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `reference_quotation_id` INT UNSIGNED NULL,
  `narration` VARCHAR(500) NOT NULL DEFAULT '',
  `status` ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  `cancelled_by` INT UNSIGNED NULL,
  `cancelled_at` DATETIME NULL,
  `cancelled_reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_so_company_date` (`company_id`, `order_date`),
  KEY `idx_so_customer` (`customer_id`),
  CONSTRAINT `fk_so_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_so_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_so_quotation` FOREIGN KEY (`reference_quotation_id`) REFERENCES `sales_quotations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `uom_id` INT UNSIGNED NULL,
  `quantity` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `base_qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `rate` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `discount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `tax_id` INT UNSIGNED NULL,
  `tax_rate` DECIMAL(7,4) NOT NULL DEFAULT 0,
  `tax_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_soi_order` (`order_id`),
  CONSTRAINT `fk_soi_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_soi_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Sales invoices (issues stock + COGS; posts AR/cash + sales + tax payable)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales_invoices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `invoice_no` VARCHAR(60) NOT NULL DEFAULT '',
  `invoice_date` DATE NOT NULL,
  `customer_id` INT UNSIGNED NULL,
  `reference_order_id` INT UNSIGNED NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `payment_mode_id` INT UNSIGNED NULL,
  `received_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `discount_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `tax_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `cogs_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `narration` VARCHAR(500) NOT NULL DEFAULT '',
  `status` ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  `cancelled_by` INT UNSIGNED NULL,
  `cancelled_at` DATETIME NULL,
  `cancelled_reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_si_company_date` (`company_id`, `invoice_date`),
  KEY `idx_si_customer` (`customer_id`),
  KEY `idx_si_no` (`invoice_no`),
  CONSTRAINT `fk_si_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_si_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_si_order` FOREIGN KEY (`reference_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_si_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_si_pm` FOREIGN KEY (`payment_mode_id`) REFERENCES `payment_modes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_invoice_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `uom_id` INT UNSIGNED NULL,
  `quantity` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `base_qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `rate` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `discount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `tax_id` INT UNSIGNED NULL,
  `tax_rate` DECIMAL(7,4) NOT NULL DEFAULT 0,
  `tax_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `cogs_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_sii_invoice` (`invoice_id`),
  CONSTRAINT `fk_sii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sii_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
