-- ============================================================================
-- TradeERP — Phase 4 schema additions
--   * payment_modes — configurable payment modes linked to GL accounts
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `payment_modes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `account_id` INT UNSIGNED NULL,
  `is_cash` TINYINT(1) NOT NULL DEFAULT 0,
  `is_bank` TINYINT(1) NOT NULL DEFAULT 0,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pm_company_code` (`company_id`, `code`),
  KEY `idx_pm_account` (`account_id`),
  CONSTRAINT `fk_pm_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_pm_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
