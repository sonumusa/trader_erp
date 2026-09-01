# Phase 13 — Permissions, Closing Period, Audit

**Goal:** enforce the closing-period lock (with an audited authorized override),
verify server-side permission enforcement across every controller, and polish
the audit trail with document-type filtering.

## What was built

### Closing period (spec §31) — `ClosingPeriodService` + `settings/closing`
- Per-company **"Closed To Date"** stored in `closing_periods`.
- **All transactions on or before the date are locked** for normal users:
  create / edit / delete / cancel on sales, purchase, returns, vouchers,
  transfers and adjustments all call `guardDate()` at the start of the
  operation (services, not just UI — the block is server-side and atomic).
- **Authorized override**: users with `settings.closing.override`
  (administrator / accountant by default; super admin always) may post inside
  the closed period — every bypass writes a `closing_override` audit entry
  with the date, action and closed-to value.
- Normal users get a clear message: *"This transaction date is inside the
  closing period (closed to …). Only an authorized user can override."*
- **UI**: Settings → Closing Period page shows the current closed-to state,
  lets authorized users set / update / clear the date (with confirm), and
  explains who can override. Setting itself requires `settings.closing.set`.

### Permission enforcement pass
- All 24 module controllers verified to call `requirePermission()` (server-side)
  on every action — the UI only hides what the backend also blocks.
- New permission `settings.closing` (view / set / override); grants added for
  administrator + accountant; super admin keeps the wildcard.

### Audit trail polish
- Audit page now has a **document-type filter** alongside module/action/search —
  e.g. show only `sales_invoice` or `voucher` entries.
- AuditService consistently records `document_type` + `document_id` on every
  document create/cancel (verified).

## Verification

`php bin/test.php` — **380/380 PASS** (367 previous + 13 Phase 13).

- closing-period boundary semantics: inside / on-boundary locked, after open
- super admin override passes and records the `closing_override` audit entry
- a real sales invoice dated inside the closed period posts via override
- **non-privileged user is blocked** with the closing-period message
- open dates pass for everyone; clearing the period reopens all dates
- all 24 module controllers enforce permissions server-side
- audit document-type filter sees every document kind and filters correctly

HTTP end-to-end: closing page renders; setting 2026-08-01 → override invoice
SI-26-000110 created dated 2026-07-15 with the audit entry
*"Authorized override: create sales invoice allowed on 2026-07-15 (closed-to
2026-08-01)"*; clearing restores NULL; audit page filtered by `sales_invoice`;
zero new web error-log entries.

## Next phase (14)
**Responsive / mobile optimization** — a final polish pass on the mobile
experience: touch-friendly targets, form layout on small screens, and print
styles, followed by the security/performance pass (15).
