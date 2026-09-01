-- ============================================================================
-- TradeERP — Phase 9 schema additions (Vouchers)
--   One normalized vouchers table + voucher_lines.
--   Types: cash_payment (CPV) / bank_payment (BPV) / cash_receipt (CRV) /
--          bank_receipt (BRV) / journal (JV) / contra (CV)
--   Every voucher posts a balanced journal entry through the accounting
--   engine; cancellation reverses it (never physical deletion).
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NULL,
  `voucher_type` ENUM('cash_payment','bank_payment','cash_receipt','bank_receipt','journal','contra') NOT NULL,
  `voucher_no` VARCHAR(60) NOT NULL DEFAULT '',
  `voucher_date` DATE NOT NULL,
  `party_type` ENUM('customer','supplier') NULL,
  `party_id` INT UNSIGNED NULL,
  `payment_mode_id` INT UNSIGNED NULL,
  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `narration` VARCHAR(500) NOT NULL DEFAULT '',
  `status` ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  `cancelled_by` INT UNSIGNED NULL,
  `cancelled_at` DATETIME NULL,
  `cancelled_reason` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_v_company_date` (`company_id`, `voucher_date`),
  KEY `idx_v_type` (`voucher_type`, `voucher_no`),
  KEY `idx_v_party` (`party_type`, `party_id`),
  KEY `idx_v_pm` (`payment_mode_id`),
  CONSTRAINT `fk_v_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_v_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_v_pm` FOREIGN KEY (`payment_mode_id`) REFERENCES `payment_modes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_v_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `voucher_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voucher_id` INT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `debit` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `party_type` ENUM('customer','supplier') NULL,
  `party_id` INT UNSIGNED NULL,
  `narration` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_vl_voucher` (`voucher_id`),
  KEY `idx_vl_account` (`account_id`),
  CONSTRAINT `fk_vl_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vl_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `chk_vl_side` CHECK ((`debit` > 0 AND `credit` = 0) OR (`credit` > 0 AND `debit` = 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
