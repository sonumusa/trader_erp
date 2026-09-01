-- ============================================================================
-- TradeERP — Phase 3 schema additions
--   * accounts: is_system / is_cash / is_bank flags
--   * journal_entries + journal_entry_lines   (double-entry posting)
--   * account_ledger                          (materialized running-balance ledger)
--   * account_defaults                        (Accounting Defaults mapping)
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `accounts`
  ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_group`,
  ADD COLUMN `is_cash` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_system`,
  ADD COLUMN `is_bank` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_cash`;

-- ----------------------------------------------------------------------------
-- Journal entries — one balanced posting per transaction
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `entry_no` VARCHAR(60) NOT NULL DEFAULT '',
  `entry_date` DATE NOT NULL,
  `voucher_type` VARCHAR(40) NOT NULL DEFAULT 'journal',
  `source_document_type` VARCHAR(80) NULL,
  `source_document_id` INT UNSIGNED NULL,
  `narration` VARCHAR(500) NOT NULL DEFAULT '',
  `status` ENUM('posted','reversed') NOT NULL DEFAULT 'posted',
  `reversed_from_id` BIGINT UNSIGNED NULL,
  `reversal_reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_je_company_date` (`company_id`, `entry_date`),
  KEY `idx_je_source` (`source_document_type`, `source_document_id`),
  KEY `idx_je_voucher` (`voucher_type`, `entry_no`),
  KEY `idx_je_reversed_from` (`reversed_from_id`),
  CONSTRAINT `fk_je_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_je_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_je_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_entry_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_entry_id` BIGINT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `debit` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `party_type` ENUM('customer','supplier') NULL,
  `party_id` INT UNSIGNED NULL,
  `narration` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_jel_entry` (`journal_entry_id`),
  KEY `idx_jel_account` (`account_id`),
  KEY `idx_jel_party` (`party_type`, `party_id`),
  CONSTRAINT `fk_jel_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jel_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `chk_jel_side` CHECK ((`debit` > 0 AND `credit` = 0) OR (`credit` > 0 AND `debit` = 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Account ledger — materialized, running balance per account
-- Rebuilt deterministically per account after each post/reversal
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `account_ledger` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `entry_date` DATE NOT NULL,
  `voucher_no` VARCHAR(60) NOT NULL DEFAULT '',
  `voucher_type` VARCHAR(40) NOT NULL DEFAULT '',
  `journal_entry_id` BIGINT UNSIGNED NOT NULL,
  `line_id` BIGINT UNSIGNED NULL,
  `debit` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `party_type` ENUM('customer','supplier') NULL,
  `party_id` INT UNSIGNED NULL,
  `source_document_type` VARCHAR(80) NULL,
  `source_document_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_al_account_date` (`account_id`, `entry_date`, `id`),
  KEY `idx_al_company_date` (`company_id`, `entry_date`),
  KEY `idx_al_party` (`party_type`, `party_id`),
  KEY `idx_al_entry` (`journal_entry_id`),
  CONSTRAINT `fk_al_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_al_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Accounting defaults — automatic account resolution (Cash→Cash in Hand, etc.)
-- branch_id NULL = company-wide default; a branch override takes precedence
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `account_defaults` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `key` VARCHAR(40) NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ad_company_key` (`company_id`, `key`),
  KEY `idx_ad_account` (`account_id`),
  CONSTRAINT `fk_ad_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_ad_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ad_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
