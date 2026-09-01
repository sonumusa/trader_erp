# Phase 2 — Companies, Branches, Financial Years, Numbering, Setup Wizard

**Goal:** multi-company/branch structure, financial-year management, flexible
document numbering with safe serial reset, warehouse & UOM masters, and a
non-technical setup wizard — all tested.

## What was built

### Multi-company & branch context
- `CompanyController` — list, create, edit; **company switcher** in the top bar.
- `CompanyContextService` — active company/branch live in the session; the user's
  choice is remembered in `users.default_company_id`.
- Creating a company (via form or setup wizard) atomically creates: head office
  branch, default warehouse, default financial year, and 16 document number series.
- `BranchController` — CRUD with soft delete; Head Office cannot be deleted;
  **branch switcher** in the top bar; reports will support
  Consolidated / Head Office / Branch views (reporting phase).
- The whole branch module is feature-gated (`features.branches`) both in the UI
  and server-side (`BranchController` refuses to run when disabled).

### Financial years
- `FinancialYearService` — current-year resolution (active → contains today →
  latest), activate (deactivate others), close/reopen with permission + audit.
- UI: list with Current-period badge, activate/close/lock buttons.

### Document numbering
- `DocumentNumberService` — per (company, document type) series.
  Format `[branch-]PREFIX[-fy]-NNNNNN`, e.g. `SI-26-000001`, `LHR-SI-26-000001`.
- **Concurrency-safe**: `SELECT … FOR UPDATE` inside a transaction — two parallel
  requests can never get the same number.
- Configurable prefix, number length, branch-code and financial-year inclusion,
  starting number, next number, active flag.
- **Serial reset** (`POST /settings/numbering/{id}/reset`):
  - permission-gated (`settings.numbering.reset`), super-admin/administrator only by default
  - shows the last-used number in the confirmation modal
  - refuses values ≤ last used (would duplicate numbers) — verified by test
  - records an audit entry with user, date/time, IP and old/new values
  - never overwrites history (document numbers on existing documents stay)
- Live format preview in the edit form.

### Setup wizard
- 13-step wizard (`SetupController::STEPS`): Company, Financial Year, Branches,
  COA, Default Accounts, Warehouses, UOM, Tax, Users & Roles, Opening Balances,
  Opening Stock, Feature Settings, Finish.
- Steps whose modules aren't built yet are shown honestly ("Available with Phase 3/4/5")
  instead of faking functionality; live steps post through the wizard and write
  real rows.

### Masters
- `UomController` — real CRUD; duplicate-code protection; delete blocked when in use.
- `WarehouseController` — real CRUD with branch assignment and default-warehouse rule
  (only one default per company; default cannot be deleted).

### Demo data
- `bin/seed_demo.php` / `bin/unseed_demo.php` — optional sample customers, suppliers,
  item groups, items, UOMs (all marked `DEMO-*` / `@demo.local`). Fully removable
  without touching application structure.

### Migration runner
- `bin/migrate.php` applies pending `database/migrations/*.sql`, tracks them in
  `schema_migrations`, tolerates already-applied SQL on pre-Phase-2 installs, and
  syncs the permission catalogue. The installer now also records applied migrations.

## Verification

`php bin/test.php` — **96/96 PASS** (66 Phase 1 + 30 Phase 2).

Key Phase-2 assertions:
- 16 series seeded; `SI-26-000001` → `SI-26-000002` sequencing
- reset below last-used rejected; reset to valid value works; `last_used_number` preserved
- FY close/reopen; activate switches the single active FY
- `createCompany` creates HO + warehouse + FY + 16 series atomically
- company/branch switch persists to session + `users.default_company_id`
- UOM create/update/unique; warehouse update/soft-delete

HTTP end-to-end (curl):
- All 22 routes → 200
- Branch/UOM/warehouse/FY create → persisted
- Numbering edit rejects invalid next number; reset audited
- Company switch persists `default_company_id`
- Setup wizard step 7 (UOM) creates a real row; step 4 (COA, Phase 3) politely refuses
- Viewer blocked (302) on all 7 Phase-2 admin pages
- Feature gate: branches off → `/branches` 302 to `/`; on → 200
- Zero new error-log entries after fixes

## Bugs found & fixed
- `FeatureController`/`UserController`/etc. declared a private `requirePermission()`
  that collided with the new protected base-controller method → PHP fatal;
  removed the redundant private copies.
- Migration runner double-applied on pre-Phase-2 DBs → added idempotency guard +
  installer now records applied migrations.
- sed-mangled view strings (`ppControllers…`, stray bell char) → repaired with a
  PHP cleanup pass.

## Next phase (3)
Chart of accounts + the central double-entry accounting engine + default account
mapping, so every sales/purchase/voucher flow posts verified balanced entries.
