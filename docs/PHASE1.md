# Phase 1 — Deliverables & Verification

**Goal:** a production-quality foundation: architecture + database + authentication
+ installation + users/roles/permissions + settings + features + audit trail —
tested, with no Node.js anywhere.

## What was built

### 1. MVC framework (`app/Core/`)
- **Bootstrap** — config loading (generated `config/config.php`, sample fallback),
  PSR-4-style autoloader, global error handling (friendly pages, logs to
  `storage/logs/`), timezone, Database + Session boot, Request init.
- **Router** — GET/POST/ANY, `{param}` captures, route groups, middleware chains,
  friendly 404 page for authenticated users.
- **Request** — path/base-URL detection (subfolder installs), JSON body parsing
  (fetch-ready), `_method` override, page() helper.
- **Response** — html/json/redirect/back/download, CSRF 419 page.
- **Database** — PDO wrapper; **all queries prepared**; `transaction()` helper
  (used by later phases for atomicity); no SQL string concatenation anywhere.
- **Session** — strict cookies (HttpOnly, SameSite, Secure-aware), idle + absolute
  timeouts, regeneration on privilege change, flash messages.
- **Csrf** — per-session token, `hash_equals` validation, middleware on every
  state-changing route.
- **View** — layout + section engine, `e()` escaping, CSRF field helper, asset/url.
- **Validator** — required/email/min/max/numeric/integer/date/in/confirmed/unique/
  strong_password rules, server-side only.
- **ErrorHandler** — raw errors never reach users; technical details go to logs.

### 2. Domain layer
- **Services**: `AuthService` (password_verify, session regeneration, activity
  log), `PermissionService` (module.document.action + super-admin wildcard,
  catalogue from `config/permissions.php`), `AuditService` (audit_logs +
  change_history with user/company/IP), `SettingsService`, `FeatureService`
  (17 toggles from `config/features.php`), `CompanyContextService`.
- **Repositories**: `UserRepository` (paginated search/sort), `RoleRepository`.
- **Models**: thin `BaseModel` + concrete models.

### 3. Database (`database/migrations/001_core_schema.sql`)
22 tables: users, roles, permissions, permission_role, companies, branches,
financial_years, settings, features, audit_logs, change_history, user_activity,
customers, suppliers, items, item_groups, uoms, item_uoms, warehouses, accounts,
account_groups, document_number_series — InnoDB, utf8mb4, FKs, indexes,
soft-delete (`deleted_at`) on masters, `DECIMAL(18,2)` money.

### 4. Installer (`install.php` + `install/index.php` + seeder)
4-step wizard (requirements → database → admin/company → finish) with
connection testing, DB creation, schema execution, seeding (8 roles, permission
catalogue, grants, 17 features, settings, company + Head Office, financial year,
warehouse, super admin), config generation and self-locking.

### 5. UI (Bootstrap 5.3.3 local — zero CDN, zero Node)
Sidebar layout (feature + permission gated navigation), topbar with quick-jump
and user menu, dashboard with compact stat cards and live tables (**no charts**),
user list (search/sort/pagination) + create/edit forms, role list + permission
matrix with module "All" toggles, settings, feature toggles, audit trail,
placeholder pages that honestly say a module is pending.

## Verification performed

### Unit harness — `php bin/test.php` → **66/66 PASS**
Schema integrity, seeded defaults, auth, RBAC, audit, CSRF, validator, features.

### HTTP end-to-end (curl against dev server)
| Check | Result |
|---|---|
| GET /login renders with 64-char CSRF token | ✅ 200 |
| POST /login without token | ✅ 419 (blocked) |
| POST /login with cookie+token | ✅ 302 → / |
| GET / dashboard (authenticated) | ✅ 200, 12 stat cards |
| GET /users, /roles, /settings, /audit, /settings/features | ✅ 200 |
| GET /roles/1/permissions (Super Admin locked) | ✅ 200, matrix hidden |
| GET /roles/3/permissions (Manager) | ✅ 200, 49 checkboxes |
| POST save permissions → DB + audit entry | ✅ |
| Viewer user blocked from /users, /settings, /audit (302) | ✅ |
| Viewer POST /users blocked server-side (no row created) | ✅ |
| User create → edit (prefill) → soft delete + audit | ✅ |
| Settings POST persists to companies + settings | ✅ |
| Placeholder module pages | ✅ 200 |
| Unknown URL | ✅ 404 |

## Bugs found & fixed during testing
- `APP_ROOT` resolution (dirname depth) — masked by the harness, caught by HTTP.
- `permissions` table lacked PRIMARY KEY on AUTO_INCREMENT id (MySQL 1075).
- View engine never stored `__content` → layouts rendered empty.
- `e()` called inside `app\Core` namespace without import.
- String route params vs typed `int $id` controller signatures.
- Placeholder routes called `module()` with 0 args.
- Stale `_old_input` leaking into edit forms.

## Next phase (2)
Companies/branches CRUD + company switcher, financial-year management,
document numbering with branch prefixes + serial reset, setup wizard,
and opening-balance foundations.
