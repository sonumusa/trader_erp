-- ============================================================================
-- TradeERP — Phase 10 schema additions (Costing)
--   costing_method_history — audit trail of costing-method changes.
--   Changing the method replays the full stock_ledger history through the
--   new method (controlled revaluation); every change is recorded here with
--   the old/new method, user, and the number of movements replayed.
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `costing_method_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `old_method` VARCHAR(20) NOT NULL,
  `new_method` VARCHAR(20) NOT NULL,
  `movements_replayed` INT NOT NULL DEFAULT 0,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `changed_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cmh_company` (`company_id`, `created_at`),
  CONSTRAINT `fk_cmh_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_cmh_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
