-- ============================================================================
-- TradeERP — Core schema (Phase 1)
-- MySQL / MariaDB, InnoDB, utf8mb4
--
-- Conventions:
--   * Every row carries created_at / updated_at; masters also carry deleted_at
--     (soft delete keeps history intact).
--   * Amounts are DECIMAL(18,2) — never floats.
--   * Accounting documents are NEVER physically deleted (see cancel/void design
--     in later phases).
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Companies & branches
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(20) NOT NULL DEFAULT '',
  `address` VARCHAR(255) NOT NULL DEFAULT '',
  `phone` VARCHAR(40) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'PKR',
  `fiscal_year_start` DATE NULL,
  `fiscal_year_end` DATE NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_companies_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(20) NOT NULL DEFAULT '',
  `address` VARCHAR(255) NOT NULL DEFAULT '',
  `is_head_office` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_branches_company` (`company_id`),
  CONSTRAINT `fk_branches_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Financial years
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `financial_years` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(60) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fy_company` (`company_id`),
  CONSTRAINT `fk_fy_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Roles, permissions, users
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(60) NOT NULL,
  `document` VARCHAR(80) NOT NULL,
  `action` VARCHAR(40) NOT NULL,
  `name` VARCHAR(160) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permission` (`module`, `document`, `action`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permission_role` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_pr_permission` (`permission_id`),
  CONSTRAINT `fk_pr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at` DATETIME NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_company` (`company_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Settings & features
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(80) NOT NULL,
  `value` TEXT NULL,
  `group_name` VARCHAR(60) NOT NULL DEFAULT 'general',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `features` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(60) NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `is_installed` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_features_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Audit trail
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `company_id` INT UNSIGNED NULL,
  `action` VARCHAR(40) NOT NULL,
  `module` VARCHAR(60) NOT NULL,
  `document_type` VARCHAR(80) NULL,
  `document_id` INT UNSIGNED NULL,
  `description` VARCHAR(500) NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_module` (`module`),
  KEY `idx_audit_doc` (`document_type`, `document_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `change_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `module` VARCHAR(60) NOT NULL,
  `document_type` VARCHAR(80) NOT NULL,
  `document_id` INT UNSIGNED NOT NULL,
  `field_name` VARCHAR(80) NOT NULL,
  `old_value` VARCHAR(500) NULL,
  `new_value` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_change_doc` (`module`, `document_id`),
  CONSTRAINT `fk_change_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_activity` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `event` VARCHAR(40) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ua_user` (`user_id`),
  KEY `idx_ua_created` (`created_at`),
  CONSTRAINT `fk_ua_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Master data — created now (empty), populated in their build phases
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `code` VARCHAR(30) NOT NULL DEFAULT '',
  `name` VARCHAR(150) NOT NULL,
  `type` ENUM('business','individual') NOT NULL DEFAULT 'business',
  `phone` VARCHAR(40) NOT NULL DEFAULT '',
  `mobile` VARCHAR(40) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `address` VARCHAR(255) NOT NULL DEFAULT '',
  `city` VARCHAR(80) NOT NULL DEFAULT '',
  `tax_number` VARCHAR(40) NOT NULL DEFAULT '',
  `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `credit_limit` DECIMAL(18,2) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `notes` VARCHAR(500) NOT NULL DEFAULT '',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customers_company` (`company_id`),
  KEY `idx_customers_name` (`name`),
  CONSTRAINT `fk_customers_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `code` VARCHAR(30) NOT NULL DEFAULT '',
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(40) NOT NULL DEFAULT '',
  `mobile` VARCHAR(40) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `address` VARCHAR(255) NOT NULL DEFAULT '',
  `city` VARCHAR(80) NOT NULL DEFAULT '',
  `tax_number` VARCHAR(40) NOT NULL DEFAULT '',
  `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `notes` VARCHAR(500) NOT NULL DEFAULT '',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_suppliers_company` (`company_id`),
  KEY `idx_suppliers_name` (`name`),
  CONSTRAINT `fk_suppliers_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `item_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `name` VARCHAR(120) NOT NULL,
  `code` VARCHAR(30) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ig_parent` (`parent_id`),
  CONSTRAINT `fk_ig_parent` FOREIGN KEY (`parent_id`) REFERENCES `item_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `uoms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(60) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_uoms_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_code` VARCHAR(60) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `barcode` VARCHAR(80) NOT NULL DEFAULT '',
  `item_group_id` INT UNSIGNED NULL,
  `brand` VARCHAR(80) NOT NULL DEFAULT '',
  `description` TEXT NULL,
  `default_purchase_rate` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `default_sales_rate` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `stock_uom_id` INT UNSIGNED NULL,
  `min_stock` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `reorder_level` DECIMAL(18,4) NOT NULL DEFAULT 0,
  `batch_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `serial_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `expiry_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `inventory_account_id` INT UNSIGNED NULL,
  `cogs_account_id` INT UNSIGNED NULL,
  `sales_account_id` INT UNSIGNED NULL,
  `purchase_account_id` INT UNSIGNED NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_items_code` (`item_code`),
  KEY `idx_items_name` (`name`),
  KEY `idx_items_barcode` (`barcode`),
  KEY `idx_items_group` (`item_group_id`),
  CONSTRAINT `fk_items_group` FOREIGN KEY (`item_group_id`) REFERENCES `item_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_items_uom` FOREIGN KEY (`stock_uom_id`) REFERENCES `uoms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `item_uoms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` INT UNSIGNED NOT NULL,
  `uom_id` INT UNSIGNED NOT NULL,
  `is_stock_uom` TINYINT(1) NOT NULL DEFAULT 0,
  `conversion_factor` DECIMAL(18,4) NOT NULL DEFAULT 1.0000,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_iu_item` (`item_id`),
  CONSTRAINT `fk_iu_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_iu_uom` FOREIGN KEY (`uom_id`) REFERENCES `uoms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warehouses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `name` VARCHAR(120) NOT NULL,
  `code` VARCHAR(30) NOT NULL DEFAULT '',
  `address` VARCHAR(255) NOT NULL DEFAULT '',
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wh_company` (`company_id`),
  CONSTRAINT `fk_wh_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Chart of accounts (populated in Phase 3)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `account_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `name` VARCHAR(120) NOT NULL,
  `account_type` ENUM('asset','liability','income','expense','equity') NOT NULL DEFAULT 'asset',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ag_parent` (`parent_id`),
  CONSTRAINT `fk_ag_parent` FOREIGN KEY (`parent_id`) REFERENCES `account_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED NULL,
  `code` VARCHAR(30) NOT NULL DEFAULT '',
  `name` VARCHAR(150) NOT NULL,
  `account_type` ENUM('asset','liability','income','expense','equity') NOT NULL DEFAULT 'asset',
  `is_group` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_accounts_company` (`company_id`),
  KEY `idx_accounts_parent` (`parent_id`),
  KEY `idx_accounts_code` (`code`),
  CONSTRAINT `fk_accounts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_accounts_parent` FOREIGN KEY (`parent_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Document numbering (populated in Phase 2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `document_number_series` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `document_type` VARCHAR(60) NOT NULL,
  `prefix` VARCHAR(30) NOT NULL DEFAULT '',
  `financial_year_id` INT UNSIGNED NULL,
  `starting_number` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `next_number` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `number_length` TINYINT UNSIGNED NOT NULL DEFAULT 6,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dns_company` (`company_id`),
  KEY `idx_dns_type` (`document_type`, `financial_year_id`),
  CONSTRAINT `fk_dns_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
