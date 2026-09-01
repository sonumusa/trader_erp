# TradeERP — Trading, Inventory & Double-Entry Accounting ERP

A commercial-grade ERP for **trading businesses**, built on **Core PHP + MySQL/MariaDB**
with a structured MVC architecture. Designed to run on **Hostinger Shared Hosting** —
no Node.js, no build step, no Composer required in production.

> Build status: **COMPLETE (all 16 phases)** — architecture, database, authentication,
> users/roles/permissions, settings, features, audit trail, installer,
> companies/branches + switchers, financial years, document numbering,
> warehouses/UOMs, setup wizard, demo data, **Chart of Accounts + double-entry
> accounting engine + account ledger**, **customers & suppliers with party
> ledgers, outstanding, aging & opening balances**, **payment modes**, and the
> **item master + inventory engine** (stock ledger, MA/weighted/FIFO/LIFO
> costing, transfers, adjustments, opening stock, batches/serials/expiry, taxes).
> **367 automated tests passing** (debit always equals credit).

---

## Feature summary (as of Phase 1)

| Area | Status |
|---|---|
| MVC framework (router, controllers, views, services, repositories) | ✅ Done |
| Auth (login/logout, session hardening, CSRF) | ✅ Done |
| RBAC (8 seeded roles + permission matrix editor) | ✅ Done |
| Settings (company, currency, timezone, costing method, negative stock) | ✅ Done |
| Feature management (17 module toggles) | ✅ Done |
| Audit trail + change history | ✅ Done |
| Web installer (requirements → database → admin/company → finish) | ✅ Done |
| Double-entry accounting engine + COA | ✅ Phase 3 |
| Customers/suppliers, party ledgers, outstanding, aging, payment modes | ✅ Phase 4 |
| Companies / branches / financial years / numbering / setup wizard | ✅ Phase 2 |
| Inventory engine (items, UOM, costing, batches, serials) | ✅ Phase 5 |
| Purchase (invoices/orders/quotations/returns) | ✅ Phase 6 |
| Sales (invoices/orders/quotations) | ✅ Phase 7 |
| Sales Returns + Purchase Returns | ✅ Phase 8 |
| Vouchers (CPV/BPV/CRV/BRV/JV/Contra) | ✅ Phase 9 |
| Reports & dashboard finance figures | 🔜 Phases 11–12 |
| Closing period, numbering, financial years (advanced) | 🔜 Phase 2+ |

The dashboard and placeholder module pages are **honest**: they show real seeded
counts and clearly mark figures that arrive with their build phase. No fake data.

---

## Architecture

```
erp/
├── app/
│   ├── Core/          # framework: Bootstrap, Router, Request, Response, Database,
│   │                  #   Session, Csrf, View, Validator, Middleware, ErrorHandler
│   ├── Controllers/   # HTTP layer (thin; no business logic)
│   ├── Services/      # business logic: Auth, Audit, Permission, Settings, Features
│   ├── Repositories/  # data access
│   ├── Models/        # thin model base
│   ├── Views/         # layout + pages (presentation only)
│   └── Middleware/    # auth, guest, csrf
├── config/            # config.php (generated), permissions.php, features.php, samples
├── database/
│   ├── migrations/    # SQL schema
│   └── seeders/       # default data (roles, permissions, company, admin…)
├── public/            # web root: index.php, assets (Bootstrap 5.3 local)
├── routes/web.php     # all routes
├── storage/           # logs, uploads, backups, exports (web-denied)
├── install/           # web installer wizard
├── install.php        # installer entry point
├── bin/test.php       # test harness
└── docs/              # documentation
```

### Key design rules

- **Prepared statements only** — SQL injection is not possible through the data layer.
- **Double-entry from day one** — the accounting engine (Phase 3) is a single
  centralised service; no module writes journal entries itself.
- **Soft deletes + audit trail** — financially significant records are never
  physically deleted.
- **Server-side permissions** — every controller action re-checks RBAC; the UI
  only hides what the backend also blocks.
- **No charts** — dashboard uses compact cards and tables by design.

---

## Running locally

```bash
# 1. Requirements: PHP >= 8.1 with pdo_mysql, mbstring, openssl, curl, zip; MySQL/MariaDB
# 2. Create a test database (optional — only for the test harness)
bash bin/setup_db.sh

# 3. Run the test harness (66 assertions: schema, seeding, auth, RBAC, CSRF, audit)
php bin/test.php

# 4. Start the dev server
php -S 0.0.0.0:8080 -t public public/index.php
# → open http://localhost:8080/install.php to run the installer

# Defaults created by the installer
#   Admin:  (email/password you choose in step 3 of the installer)
```

The installer writes `config/config.php`, creates tables, seeds defaults
(8 roles, permission catalogue, 17 features, company + Head Office, financial
year, warehouses, super admin) and locks itself via `storage/installed.lock`.

---

## Tests

`php bin/test.php` covers:

- schema integrity (22 tables, FKs, indexes)
- seeded defaults (roles, permissions, company, head office, financial year,
  features, settings, admin + password hash)
- authentication (login/logout, failed attempts, activity log)
- RBAC (super-admin wildcard, viewer restrictions)
- audit trail + change history
- CSRF token generation/validation
- validator rules (required, email, numeric, date, unique)
- feature toggles

An end-to-end HTTP suite (login flow, CSRF 419s, permission enforcement,
user CRUD, settings persistence, role permission saving) is exercised during
development via curl — see `docs/PHASE1.md`.

---

## Deployment to Hostinger (summary)

1. Upload the project to `public_html/` (or a subfolder).
2. Create a MySQL database + user in hPanel.
3. Visit `https://yourdomain.com/install.php` and complete the wizard.
4. Delete `install.php` and the `install/` folder.
5. Point the domain document root at the `public/` folder (or keep files in
   `public_html/` directly — the root `.htaccess` routes correctly either way).

Full guide with security notes: `docs/DEPLOYMENT.md`.

---

## Roadmap

| Phase | Deliverable |
|---|---|
| 1 ✅ | Architecture, database, auth, installer, users/roles, settings, features, audit |
| 2 ✅ | Companies/branches + switchers, financial years, document numbering + reset, setup wizard, UOM/warehouse masters, demo data |
| 3 ✅ | Chart of accounts + double-entry accounting engine + accounting defaults + account ledger + opening balances |
| 4 ✅ | Customers & suppliers + party ledgers + outstanding & aging + payment modes bound to GL accounts + opening balances |
| 3 | Chart of accounts + double-entry accounting engine + defaults |
| 4 ✅ | Customers, suppliers, payment modes, party ledgers, outstanding, aging |
| 5 ✅ | Items, UOM, warehouses, inventory engine (costing, batches, serials, expiry) |
| 6 ✅ | Purchase (quotations, orders, invoices, returns) — stock + AP + tax |
| 7 ✅ | Sales (quotations, orders, invoices) — stock out + COGS + AR/cash + tax |
| 8 ✅ | Sales returns — stock in + COGS/AR/cash/tax reversal |
| 9 ✅ | Vouchers — CPV/BPV/CRV/BRV/JV/Contra + register |
| 10 ✅ | Costing — controlled revaluation + audit, verification, item-wise cost |
| 11 ✅ | Reports — registers, ledgers, TB, P&L, balance sheet, cash/bank books + export |
| 12 ✅ | Dashboard — finance cards + low stock + outstanding + recent activity |
| 13 ✅ | Closing period, permissions pass, audit doc-type filter |
| 14 ✅ | Responsive/mobile — touch targets, fixed save bar, bottom-sheet modals, asset fix |
| 15 ✅ | Security — login throttle, password reset, validated uploads, CSP, XSS/performance audit |
| 16 ✅ | Deployment package, database backups, acceptance criteria verified |
| 8 | Sales/Purchase returns |
| 9 | Vouchers (CPV, BPV, CRV, BRV, JV, Contra) |
| 10 | Costing engines (moving avg, weighted, FIFO, LIFO) |
| 11–12 | Reports, dashboard figures |
| 13 | Closing period, permissions hardening, audit UI polish |
| 14–16 | Mobile optimization, security/performance pass, Hostinger package |

Each phase ends tested: **debit always equals credit**, stock follows the
selected costing method, reports derive from ledgers (never parallel totals).
