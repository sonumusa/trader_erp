# Phase 12 — Dashboard

**Goal:** finish the compact, chart-free dashboard with real, ledger-derived
figures and the key operational sections — plus verify the responsive/mobile
experience is genuinely usable.

## What was delivered

### Finance cards (no charts — spec §39)
- **Today's Sales / Purchase** — summed from posted `sales_invoices` /
  `purchase_invoices` for the day (document-driven).
- **Today's Receipts / Payments** — cash+bank ledger debits/credits for the day.
- **Receivables / Payables** — AR / AP account balances (ledger-derived).
- **Stock Value** — inventory account balance.
- **Gross Profit (MTD)** — trial-balance income − expenses for the month.
- Every value is computed from the ledgers/documents — zero fabricated numbers;
  compact cards with icons, no charts anywhere.

### Operational sections
- **Recent sales** (last 6 invoices with customer + total) → All link.
- **Low stock** (below reorder level, live from stock ledger) → All link.
- **Outstanding customers** (top 6 from the AR party ledger) → report link.
- **Payable suppliers** (top 6 from the AP party ledger) → report link.
- **Recent activity** (audit trail) and **Recently added users**.
- Quick actions: **New Sale** / **New Purchase** buttons in the header.

### Responsive verification
- Mobile viewport meta on every page; hamburger (off-canvas) sidebar below
  992px; tables scroll horizontally in `erp-table-wrap` on small screens;
  stat-grid collapses to fewer columns on tablet/mobile.

## Verification

- Unit harness still **367/367 PASS** (no regressions).
- HTTP: dashboard renders with 12 stat cards, all 6 sections, live values
  (e.g. Receivables 27,371 / Payables 26,511 / Stock 9,538 / MTD profit 615 in
  test data); **zero chart/canvas elements** (only "Chart of Accounts" nav text).
- Full 22-route sweep → all 200; zero new web error-log entries.
- Mobile: viewport present on all key forms; hamburger button rendered;
  scrollable table wrappers in place.

## Next phase (13)
**Permissions + Closing Period + Audit hardening** — closing-period
transactions lock (with authorized override), permission enforcement pass
across every controller, and the audit trail UI polish.
