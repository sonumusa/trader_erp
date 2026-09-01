-- ============================================================================
-- TradeERP — Phase 8 schema additions (Sales Returns)
--   sales_returns + sales_return_items.
--   A return reverses stock (back in), COGS, receivable/cash and output tax.
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `sales_returns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `return_no` VARCHAR(60) NOT NULL DEFAULT '',
  `return_date` DATE NOT NULL,
  `customer_id` INT UNSIGNED NULL,
  `reference_invoice_id` INT UNSIGNED NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
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
  KEY `idx_sr_company_date` (`company_id`, `return_date`),
  KEY `idx_sr_customer` (`customer_id`),
  KEY `idx_sr_no` (`return_no`),
  CONSTRAINT `fk_sr_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_sr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sr_invoice` FOREIGN KEY (`reference_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sr_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_return_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `return_id` INT UNSIGNED NOT NULL,
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
  KEY `idx_sri_return` (`return_id`),
  CONSTRAINT `fk_sri_return` FOREIGN KEY (`return_id`) REFERENCES `sales_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sri_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
