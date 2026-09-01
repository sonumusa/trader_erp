# Phase 4 — Customers, Suppliers, Payment Modes, Party Ledgers

**Goal:** the receivable/payable side of the engine — separate customer and
supplier masters, real party ledgers, outstanding + aging, and configurable
payment modes bound to GL accounts. All figures come from the accounting
ledger — never parallel totals.

## What was built

### Customers & Suppliers (separate masters)
- `CustomerController` / `SupplierController` — searchable, paginated lists with
  live **outstanding** column; create/edit with full contact info (phone, mobile,
  email, address, city, tax number/NTN), credit limit, status, notes.
- **Opening balance on create** → posted through `AccountingEngine::postPartyOpening()`
  as a balanced entry on Accounts Receivable / Accounts Payable (party-tagged)
  against Opening Balance Equity. Positive customer opening = they owe us;
  negative = advance/credit balance. Suppliers are credit-normal (positive = we owe them).
- **Delete protection**: a party with an outstanding balance cannot be removed —
  the system asks you to settle it first (verified: blocked with a clear message).
- Feature-gated (`features.sales` / `features.purchase`) both in the UI and
  server-side.

### Party sub-ledgers (`PartyLedgerService`)
- **Party ledger page** per customer/supplier: date, voucher no., description,
  debit, credit, **balance**, source document — with date-range filter, opening/
  closing balances, totals, and pagination.
- **Outstanding**: `SUM(debit) - SUM(credit)` for customers; sign-flipped for
  suppliers so a payable reads positive.
- **Aging** (FIFO): outstanding allocated oldest-first into 0–30 / 31–60 / 61–90 /
  90+ buckets — correct for both customers (debit-normal) and suppliers (credit-normal).
- Rollups for list screens (`outstandingByParty`) and the dashboard.

### Payment modes (`/settings/payment-modes`)
- Six system modes seeded per company: **Cash** (auto-bound to the company's Cash
  in Hand default), Bank Transfer, Cheque, Online, Credit, Other.
- Create custom modes (e.g. Meezan Bank, HBL, JazzCash) linked to a bank/cash
  account — when the user selects the mode later, the account auto-selects.
- System modes can't be deleted (deactivate instead); duplicate codes blocked;
  soft delete for custom modes.

### Dashboard — real figures
- Today's Sales/Purchase/Receipts/Payments, Receivables, Payables, Stock Value
  and Gross Profit (MTD) are now **computed from the ledger** (`AccountService` +
  `trialBalanceSums`). No fabricated numbers: until sales/purchase documents are
  posted the values are genuinely 0.

### Wiring
- Migration `004_phase4.sql` (payment_modes), seeder `003_payment_modes.php`;
  installer, company creation and setup wizard seed payment modes automatically.
- Permissions: `settings.payment_mode` view/create/edit/delete (admin wildcard;
  accountant gets view via existing grants — extended in `001_defaults.php`).

## Verification

`php bin/test.php` — **165/165 PASS** (140 previous + 25 Phase 4).

- payment modes seeded (≥6), Cash bound to the cash default, custom mode
  create/update/soft-delete, system mode undeletable
- customer opening (+25,000) → AR party-tagged line, outstanding = 25,000,
  AR account balance matches
- negative customer opening → credit balance
- party ledger rows, closing balance, date filter opening balance
- FIFO aging: old invoice (90+) partially paid by recent receipt; buckets correct
- supplier opening (+15,000) → AP, sign-correct outstanding; payment reduces to 10,000
- `outstandingByParty` rollup; trial balance stays balanced after all posts

HTTP end-to-end: customer/supplier create with opening → ledger pages render with
OPENING rows + aging; customer list shows live outstanding; payment mode create;
delete protection message; viewer 302'd from all three; feature gate (sales off →
`/customers` blocked).

## Bugs found & fixed
- `PartyLedgerService` used one sign convention for both party types — suppliers
  (credit-normal) got inverted outstanding/ledger/aging. Made type-aware.
- Supplier views still referenced customer-specific fields (`type` column) and the
  wrong data key (`customer` vs `supplier`) — removed/fixed.
- Two test assertions ignored the Phase-3 opening-balance impact on AP —
  switched to delta-based assertions.

## Next phase (5)
Items, item groups, UOM conversion, warehouses and the **inventory engine**
(stock ledger, costing, batches/serials/expiry, transfers, adjustments) —
the trading side of the system.
