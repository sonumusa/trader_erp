-- ============================================================================
-- TradeERP — Phase 2 schema additions
--   * users.default_company_id      (remember company switcher choice)
--   * document_number_series: include_branch_code / include_financial_year /
--     last_used_number              (flexible document numbering)
--   * schema_migrations             (migration tracking for upgrades)
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `users`
  ADD COLUMN `default_company_id` INT UNSIGNED NULL AFTER `company_id`;

ALTER TABLE `document_number_series`
  ADD COLUMN `include_branch_code` TINYINT(1) NOT NULL DEFAULT 0 AFTER `prefix`,
  ADD COLUMN `include_financial_year` TINYINT(1) NOT NULL DEFAULT 1 AFTER `include_branch_code`,
  ADD COLUMN `last_used_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `next_number`;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(120) NOT NULL,
  `applied_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migrations_name` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
