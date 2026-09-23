-- TradeERP - Master data and controlled edit extensions
-- Extends existing UOM/tax masters without replacing them.

SET NAMES utf8mb4;

ALTER TABLE `uoms`
  ADD COLUMN `description` VARCHAR(255) NOT NULL DEFAULT '' AFTER `code`,
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `description`;

ALTER TABLE `uoms`
  ADD KEY `idx_uoms_active` (`is_active`, `deleted_at`);

ALTER TABLE `taxes`
  ADD COLUMN `tax_type` VARCHAR(40) NOT NULL DEFAULT 'sales_tax' AFTER `name`,
  ADD COLUMN `effective_from` DATE NULL AFTER `rate`,
  ADD COLUMN `effective_to` DATE NULL AFTER `effective_from`,
  ADD COLUMN `description` VARCHAR(255) NOT NULL DEFAULT '' AFTER `effective_to`;

ALTER TABLE `taxes`
  ADD KEY `idx_taxes_active_dates` (`company_id`, `is_active`, `effective_from`, `effective_to`);

ALTER TABLE `journal_entries`
  ADD KEY `idx_je_source_status` (`source_document_type`, `source_document_id`, `status`);
