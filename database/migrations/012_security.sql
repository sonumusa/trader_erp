-- ============================================================================
-- TradeERP — Phase 15 schema additions (Security hardening)
--   login_attempts   — throttling: failed logins per (email, ip) with window
--   password_resets  — token-based self-service password reset (hashed tokens,
--                      expiry, single-use)
--   attachments      — validated file uploads stored OUTSIDE the webroot;
--                      DB rows link them to documents
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `successful` TINYINT(1) NOT NULL DEFAULT 0,
  `attempted_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_la_email_ip` (`email`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pr_token` (`token_hash`),
  KEY `idx_pr_user` (`user_id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attachments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `document_type` VARCHAR(80) NOT NULL,
  `document_id` INT UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(64) NOT NULL,
  `mime_type` VARCHAR(120) NOT NULL DEFAULT '',
  `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_att_doc` (`document_type`, `document_id`),
  KEY `idx_att_company` (`company_id`),
  CONSTRAINT `fk_att_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_att_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance: composite index for ledger-based reports (trial balance, P&L)
ALTER TABLE `account_ledger`
  ADD KEY `idx_al_company_account` (`company_id`, `account_id`);
