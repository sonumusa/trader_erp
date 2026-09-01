# Phase 16 — Hostinger Deployment Package

**Goal:** ship the final deliverable — a deployable package, in-app database
backups, production hardening checks, and the full acceptance-criteria
verification (spec §69).

## What was built

### Database backups (spec §53) — `BackupService` + `settings/backups`
- **Create** a backup with one click:
  - prefers `mysqldump` (available on Hostinger) → full, transaction-safe dump
  - falls back to a **pure-PHP SQL export** (`SHOW CREATE TABLE` + batched
    `INSERT`s) — works on any shared host without shell access
- Backups stored in **`storage/backups` — outside the web root**, never served
  directly; the storage dir is also Apache-denied.
- **Download / delete** with audit entries; permission-gated
  (`settings.backup.view/create/download/delete`; super admin/administrator by
  default).
- **Round-trip proven**: a backup is restored into a scratch database and the
  tables + data verified — the export is genuinely restorable.

### Deployment package — `bin/build_package.php`
- Builds `dist/tradeerp-<version>.zip` (currently **690 KB / 210 files**) with
  only production files: `app`, `config` (sample only), `database`, `routes`,
  `public`, `storage` (empty, with `.gitkeep`), `install`, `install.php`,
  root `.htaccess`.
- **Excludes**: `config/config.php` (real credentials), `storage/installed.lock`,
  logs/cache/uploads/backups/exports contents, `.git`, `dist`, `bin`, `tests`,
  `docs`.
- Deploy: upload to `public_html`, run `install.php`, delete `install.php` +
  `/install`. Full guide: `docs/DEPLOYMENT.md`.

### Production hardening
- Installer is self-locking (`storage/installed.lock`) and the wizard refuses to
  run again; post-install checklist recommends deleting `install.php`.
- Root `.htaccess` blocks `app|config|database|routes|storage|install` and
  dotfiles; `public/.htaccess` routes through the front controller with
  nosniff; `config/.htaccess` + `storage/.htaccess` deny all.
- Session destroy hardened against CLI-only edge cases.

## Verification

`php bin/test.php` — **433/433 PASS** (420 previous + 13 Phase 16).

- backup created (real SQL file, outside webroot, correct filename), contains
  schema + data, listed, path-traversal rejected, deleted
- **backup restores cleanly into a scratch DB** (≥10 tables incl. users +
  companies, data present)
- Hostinger acceptance: no Node.js/Composer/package.json, no manufacturing
  module, installer present, deployment package exists

HTTP end-to-end: backups page renders; create → real MariaDB dump
(192 KB) listed; download returns the SQL (200); direct `storage/` access 404;
delete removes it; viewer role blocked from backups; zero new web errors.

## Final acceptance (spec §69) — verified throughout the build

| Criterion | Status |
|---|---|
| Works on Hostinger shared hosting; no Node.js; Core PHP MVC; MySQL/MariaDB | ✅ |
| True double-entry; debit always equals credit | ✅ (433 tests) |
| Sales/purchases update accounting + stock; returns reverse effects | ✅ |
| Costing per selected method (MA/WA/FIFO/LIFO) + controlled revaluation | ✅ |
| UOM conversion; item-specific UOMs only; search by code/name/barcode | ✅ |
| Customer/supplier ledgers; COA tree; branch context | ✅ |
| Closing period + audited override; server-side permissions; audit trail | ✅ |
| Reports with filters, ASC/DESC, Excel/CSV/print | ✅ |
| Compact chart-free dashboard; mobile responsive; quick-create; setup wizard | ✅ |
| Feature enable/disable; no manufacturing; future modules plug in | ✅ |

---

# 🎉 TradeERP — Complete

**26.5K LOC · 187 PHP files · 12 migrations · 433 automated tests · 690 KB deployment package**

The full Trading + Inventory + Double-Entry Accounting ERP is built, tested and
deployment-ready for Hostinger shared hosting. Every phase delivered a tested,
working module — no placeholders, no fake functionality, and the double-entry
ledger never loses balance.
