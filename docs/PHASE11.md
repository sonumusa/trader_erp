# Phase 11 — Reports

**Goal:** a reusable reporting engine covering sales, purchase, parties, stock and
accounting — every figure derived from the accounting ledger / stock ledger /
document tables (spec §63: never from parallel totals), with date filters,
ASC/DESC sorting and Excel/CSV/print export that respects the current filters.

## What was built

### Report catalogue (`ReportService`)
| Group | Reports |
|---|---|
| Sales | Sales Register, Sales by Customer, Sales by Item |
| Purchase | Purchase Register, Purchase by Supplier |
| Customers & Suppliers | Customer Outstanding, Supplier Payable |
| Inventory | Stock Balance |
| Accounting | Trial Balance, Profit & Loss, Balance Sheet, Cash Book, Bank Book |

- `ReportService` returns `['title','columns','rows','totals']` (+ section data
  for P&L / balance sheet) and shares filter/sort helpers — adding a report is
  one method + one catalogue entry.
- **Sorting**: whitelisted columns, ASC/DESC, safe ordering (orders by SELECT
  aliases so `NULL` walk-in rows sort correctly). The UI headers are clickable
  and keep the current filters.
- **Filters**: from/to date ranges applied server-side; export URLs carry the
  same query string.

### Accounting reports (ledger-derived, spec §63)
- **Trial Balance** — per-account debit/credit sums; **Σdebit always equals
  Σcredit** (asserted in tests).
- **Profit & Loss** — income (credit-normal) and expenses (debit-normal) from the
  ledger, with total income / total expenses / net profit cards.
- **Balance Sheet** — assets (debit-normal, positive) vs liabilities & equity
  (credit-normal, presented positive) **plus the current-period profit closing
  into equity** — the sheet balances (assets = liabilities + equity + profit).
- **Cash Book / Bank Book** — the cash/bank account ledgers with running balance.

### Export
- **Excel** — HTML-table `.xls` (no library needed; opens in Excel).
- **CSV** — proper `fputcsv` with explicit escape (PHP 8.4 safe).
- **Print** — A4 report layout via the shared print layout.
- Exports re-run the report with the same filters+sort — never unrelated data.
- Permission-gated: `reports.report.view` (all roles incl. Viewer for read-only)
  and `reports.report.export` (admin/accountant/manager).

### UI
- Reports index grouped by category; each report page has filter bar +
  sortable headers + Print/Excel/CSV buttons; P&L and Balance Sheet use
  side-by-side section cards.

## Verification

`php bin/test.php` — **367/367 PASS** (343 previous + 24 Phase 11).

- sales register rows + totals match document totals; sorts ASC/DESC by customer
  (case-insensitive, NULL-safe); date-range filter limits rows
- sales by customer/item aggregate with matching totals
- purchase register, customer outstanding, supplier payable, stock balance
- **trial balance Σdebit = Σcredit**
- P&L income + expenses; net = income + expenses
- **balance sheet assets = liabilities + equity + current profit** (the fix:
  current-period profit closes into equity; liabilities/equity presented positive)
- cash book / bank book rows

HTTP end-to-end: all 14 report routes 200; trial balance/P&L/balance sheet
render with totals; sales register with date filter + `sort=si.total&dir=DESC`
shows the sort arrow; Excel and CSV downloads produce valid files (CSV header
clean); print layout renders; **Viewer role: can view reports but export is
blocked** (302); zero new web error-log entries.

## Bugs found & fixed
- **Balance sheet was unbalanced** — current-period profit wasn't included in
  equity, and liabilities/equity were presented as negative (credit-normal).
  Fixed: profit closes into equity; credit-normal sections presented positive;
  now assets = liabilities + equity + profit.
- **`ORDER BY c.name` put NULL walk-in rows first** — switched to ordering by the
  SELECT alias (`customer`).
- **PHP 8.4 `fputcsv` deprecation** — explicit `$escape` parameter.
- Viewer role lacked `reports.report.view` — granted (read-only reports, per spec).

## Next phase (12)
**Dashboard** — finalize the compact dashboard: real finance cards already live,
add recent transactions, low-stock, outstanding customers/suppliers sections,
and refine the layout for mobile.
