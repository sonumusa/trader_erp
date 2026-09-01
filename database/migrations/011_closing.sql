-- ============================================================================
-- TradeERP — Phase 13 schema additions (Closing Period)
--   closing_periods — per company, a single "Closed To" date.
--   All transactions on/before the closed-to date are locked for normal
--   users; authorized roles (permission settings.closing.override) can
--   temporarily bypass. Every override is recorded in the audit trail.
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `closing_periods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `closed_to` DATE NULL,
  `set_by` INT UNSIGNED NULL,
  `set_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cp_company` (`company_id`),
  CONSTRAINT `fk_cp_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_cp_user` FOREIGN KEY (`set_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
