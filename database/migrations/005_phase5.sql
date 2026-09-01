-- ============================================================================
-- TradeERP — Phase 5 schema additions
--   * items.tax_id                    (tax category on items)
--   * item_groups account overrides   (fallback hierarchy item → group → default)
--   * stock_ledger                    (authoritative stock movement + balance)
--   * stock_layers                    (costing layers: MA / FIFO / LIFO)
--   * item_batches / item_serials     (optional physical tracking)
--   * stock_transfers (+ items)       (warehouse-to-warehouse)
--   * stock_adjustments (+ items)     (increase/decrease with reason)
--   * taxes                           (configurable tax rates)
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `items`
  ADD COLUMN `company_id` INT UNSIGNED NOT NULL AFTER `id`,
  ADD COLUMN `tax_id` INT UNSIGNED NULL AFTER `purchase_account_id`;

ALTER TABLE `items`
  ADD KEY `idx_items_company` (`company_id`);

ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

ALTER TABLE `item_groups`
  ADD COLUMN `inventory_account_id` INT UNSIGNED NULL AFTER `code`,
  ADD COLUMN `cogs_account_id` INT UNSIGNED NULL AFTER `inventory_account_id`,
  ADD COLUMN `sales_account_id` INT UNSIGNED NULL AFTER `cogs_account_id`,
  ADD COLUMN `purchase_account_id` INT UNSIGNED NULL AFTER `sales_account_id`;

-- ----------------------------------------------------------------------------
-- Taxes
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `taxes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `rate` DECIMAL(7,4) NOT NULL DEFAULT 0,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_taxes_company` (`company_id`),
  CONSTRAINT `fk_taxes_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Stock ledger — the authoritative inventory movement table.
-- balance_qty is a cumulative running balance per (item, warehouse)
-- computed in (entry_date, id) order — never a separate stored total.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_ledger` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `entry_date` DATE NOT NULL,
  `document_type` VARCHAR(60) NOT NULL,
  `document_id` INT UNSIGNED NULL,
  `document_no` VARCHAR(60) NOT NULL DEFAULT '',
  `uom_id` INT UNSIGNED NULL,
  `qty_in` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `qty_out` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `balance_qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `rate` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `value` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `cost_method` VARCHAR(20) NOT NULL DEFAULT 'moving_average',
  `batch_id` INT UNSIGNED NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sl_item_wh` (`item_id`, `warehouse_id`, `entry_date`, `id`),
  KEY `idx_sl_doc` (`document_type`, `document_id`),
  KEY `idx_sl_company_date` (`company_id`, `entry_date`),
  CONSTRAINT `fk_sl_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_sl_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `fk_sl_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_sl_uom` FOREIGN KEY (`uom_id`) REFERENCES `uoms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Costing layers — the valuation source for MA / FIFO / LIFO.
-- Rebuilt deterministically from stock_ledger when the costing method changes.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_layers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `layer_date` DATE NOT NULL,
  `qty_remaining` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `rate` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `source_document_type` VARCHAR(60) NULL,
  `source_document_id` INT UNSIGNED NULL,
  `batch_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stock_layers` (`item_id`, `warehouse_id`, `layer_date`, `id`),
  CONSTRAINT `fk_stock_layers_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Optional batch / serial tracking
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `item_batches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `batch_no` VARCHAR(80) NOT NULL,
  `mfg_date` DATE NULL,
  `expiry_date` DATE NULL,
  `qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `rate` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `created_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch_item_wh_no` (`item_id`, `warehouse_id`, `batch_no`),
  KEY `idx_batches_expiry` (`expiry_date`),
  CONSTRAINT `fk_batches_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `fk_batches_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `item_serials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `serial_no` VARCHAR(80) NOT NULL,
  `status` ENUM('in_stock','issued','returned') NOT NULL DEFAULT 'in_stock',
  `batch_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_serial_no` (`item_id`, `serial_no`),
  KEY `idx_serials_wh_status` (`item_id`, `warehouse_id`, `status`),
  CONSTRAINT `fk_serials_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `fk_serials_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Stock transfers
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_transfers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `transfer_no` VARCHAR(60) NOT NULL DEFAULT '',
  `transfer_date` DATE NOT NULL,
  `from_warehouse_id` INT UNSIGNED NOT NULL,
  `to_warehouse_id` INT UNSIGNED NOT NULL,
  `narration` VARCHAR(255) NOT NULL DEFAULT '',
  `status` ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  `cancelled_by` INT UNSIGNED NULL,
  `cancelled_at` DATETIME NULL,
  `cancelled_reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_st_company_date` (`company_id`, `transfer_date`),
  CONSTRAINT `fk_st_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_st_from_wh` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_st_to_wh` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transfer_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `uom_id` INT UNSIGNED NULL,
  `quantity` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `base_qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `rate` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`id`),
  KEY `idx_sti_transfer` (`transfer_id`),
  CONSTRAINT `fk_sti_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sti_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Stock adjustments (signed quantity: + increase / - decrease)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `adjustment_no` VARCHAR(60) NOT NULL DEFAULT '',
  `adjustment_date` DATE NOT NULL,
  `reason` VARCHAR(255) NOT NULL DEFAULT '',
  `status` ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  `cancelled_by` INT UNSIGNED NULL,
  `cancelled_at` DATETIME NULL,
  `cancelled_reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sa_company_date` (`company_id`, `adjustment_date`),
  CONSTRAINT `fk_sa_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_adjustment_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `adjustment_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `uom_id` INT UNSIGNED NULL,
  `quantity` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `base_qty` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `rate` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`id`),
  KEY `idx_sai_adjustment` (`adjustment_id`),
  CONSTRAINT `fk_sai_adjustment` FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sai_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  CONSTRAINT `fk_sai_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
