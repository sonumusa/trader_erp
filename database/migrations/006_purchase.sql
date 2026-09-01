-- ============================================================================
-- TradeERP — Phase 6 schema additions (Purchase)
--   purchase_quotations / purchase_orders / purchase_invoices / purchase_returns
--   + their item lines. All documents carry status posted|cancelled
--   (never physically deleted). Invoices/returns post stock + accounting.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- Purchase quotations (no stock / accounting impact)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_quotations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `quotation_no` VARCHAR(60) NOT NULL DEFAULT '',
  `quotation_date` DATE NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `narration` VARCHAR(500) NOT NULL DEFAULT '',
  `status` ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  `cancelled_by` INT UNSIGNED NULL,
  `cancelled_at` DATETIME NULL,
  `cancelled_reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pq_company_date` (`company_id`, `quotation_date`),
  KEY `idx_pq_supplier` (`supplier_id`),
  CONSTRAINT `fk_pq_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_pq_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_quotation_items` (
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
  KEY `idx_pqi_quotation` (`quotation_id`),
  CONSTRAINT `fk_pqi_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `purchase_quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pqi_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Purchase orders
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `order_no` VARCHAR(60) NOT NULL DEFAULT '',
  `order_date` DATE NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
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
  KEY `idx_po_company_date` (`company_id`, `order_date`),
  KEY `idx_po_supplier` (`supplier_id`),
  CONSTRAINT `fk_po_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_po_quotation` FOREIGN KEY (`reference_quotation_id`) REFERENCES `purchase_quotations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
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
  KEY `idx_poi_order` (`order_id`),
  CONSTRAINT `fk_poi_order` FOREIGN KEY (`order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poi_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Purchase invoices (posts stock IN + Accounts Payable + tax receivable)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_invoices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `invoice_no` VARCHAR(60) NOT NULL DEFAULT '',
  `invoice_date` DATE NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `reference_order_id` INT UNSIGNED NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `payment_mode_id` INT UNSIGNED NULL,
  `paid_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `discount_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `tax_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `narration` VARCHAR(500) NOT NULL DEFAULT '',
  `status` ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  `cancelled_by` INT UNSIGNED NULL,
  `cancelled_at` DATETIME NULL,
  `cancelled_reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pi_company_date` (`company_id`, `invoice_date`),
  KEY `idx_pi_supplier` (`supplier_id`),
  KEY `idx_pi_no` (`invoice_no`),
  CONSTRAINT `fk_pi_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_pi_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_pi_order` FOREIGN KEY (`reference_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pi_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_pi_pm` FOREIGN KEY (`payment_mode_id`) REFERENCES `payment_modes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_invoice_items` (
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
  PRIMARY KEY (`id`),
  KEY `idx_pii_invoice` (`invoice_id`),
  CONSTRAINT `fk_pii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pii_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Purchase returns (reverses stock, AP, tax receivable)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_returns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `return_no` VARCHAR(60) NOT NULL DEFAULT '',
  `return_date` DATE NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `reference_invoice_id` INT UNSIGNED NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `subtotal` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `discount_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `tax_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `narration` VARCHAR(500) NOT NULL DEFAULT '',
  `status` ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  `cancelled_by` INT UNSIGNED NULL,
  `cancelled_at` DATETIME NULL,
  `cancelled_reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pr_company_date` (`company_id`, `return_date`),
  KEY `idx_pr_supplier` (`supplier_id`),
  CONSTRAINT `fk_pr_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_pr_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_pr_invoice` FOREIGN KEY (`reference_invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pr_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_return_items` (
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
  PRIMARY KEY (`id`),
  KEY `idx_pri_return` (`return_id`),
  CONSTRAINT `fk_pri_return` FOREIGN KEY (`return_id`) REFERENCES `purchase_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pri_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
