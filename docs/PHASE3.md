# Phase 3 — Chart of Accounts + Double-Entry Accounting Engine

**Goal:** the financial heart of TradeERP — a hierarchical COA, a central
double-entry engine where **total debit always equals total credit**, a
materialized account ledger, and automatic account mapping (Accounting Defaults).
All tested. No module ever writes accounting rows itself.

## What was built

### 1. Chart of Accounts (`/accounts`)
- Full hierarchical COA seeded per company: **Assets → Current/Fixed**, **Liabilities →
  Current/Long-term**, **Equity**, **Income**, **Expenses** (31 accounts incl. groups).
- Codes, parent groups, account type, active flag, `is_system`/`is_cash`/`is_bank`.
- **Expandable tree view** with type badges and per-account actions:
  **View ledger / Edit** (+ Delete where safe).
- **Deletion safety** (`AccountService::canDelete`): system accounts, accounts
  with children, and accounts with ledger lines cannot be deleted — the UI
  suggests deactivating instead. Verified by test.
- Account type is immutable once the account has activity.

### 2. Double-entry engine (`AccountingEngine`)
Central `post()` that every module (sales, purchase, vouchers…) will call:

- **Balancing invariant** — `Σdebit === Σcredit` (4-dp precision); an unbalanced
  entry is rejected with a clear message and **leaves no trace** (transaction rollback).
- **Line rules** — each line is exactly one-sided (debit XOR credit); posting to
  group accounts or inactive accounts is rejected.
- **Atomicity** — posting runs inside a DB transaction; nested calls join the
  caller's transaction (used by multi-step document saves in later phases).
- **Entry numbering** — transaction documents pass their own number
  (SI-26-000001…); manual journals take the next `JV` series number.
- **Reversal** (`reverse()`) — mirror reversal entry, original marked `reversed`,
  `reversed_from_id` + reason, full history retained in the ledger; double
  reversal impossible.

### 3. Account ledger (`account_ledger`)
- Materialized running balance per account, rebuilt **deterministically** per
  affected account after each post/reversal (ordered by entry date, id).
- Columns: Date · Voucher No. · Description · Debit · Credit · **Balance** ·
  Source document (+ party for customer/supplier sub-ledgers).
- Paginated ledger page with date-range filters and debit/credit totals;
  group accounts roll up all descendants.
- `trialBalanceSums()` proves the engine: **debit = credit** across the company.

### 4. Accounting Defaults (`/accounts/defaults`)
- 16 automatic mappings: cash → Cash in Hand, receivable → AR, payable → AP,
  sales, purchase, sales return, purchase return, inventory, COGS,
  stock adjustment, discount allowed/received, round off, tax payable,
  opening balance, profit & loss.
- **Fallback resolution** (service-level): branch override → company default
  (item-group/item overrides join in Phase 5).
- Users never pick GL accounts manually; the engine resolves them automatically.

### 5. Opening balances
- `postOpeningBalances()` posts a balanced opening entry (assets/expenses debit,
  liabilities/income/equity credit, balanced on Opening Balance Equity),
  recorded in the ledger with voucher `OPENING` — no parallel totals.

### Wiring
- Installer, `CompanyController::createCompany` and Setup Wizard steps
  4–5 (COA / Default Accounts) now live and seed the COA per company.
- New permissions: `accounting.account.*`, `accounting.defaults.*` (admin +
  accountant granted).

## Verification

`php bin/test.php` — **140/140 PASS** (96 previous + 44 Phase 3).

- COA seeded per company (counts, system/cash/bank flags, defaults = 16)
- Cash sale posting: entry + 2 lines + ledger rows + correct running balances
- **Unbalanced entry rejected & leaves no trace**; group/inactive/one-sided rules
- Reversal nets the account to zero; double reversal rejected
- Out-of-order dates still produce date-ordered running balance; totals correct
- Trial balance: Σdebit = Σcredit
- Opening balance entry balanced and reflected in ledger
- Default resolution fallback; unknown key rejected; deletion safety rules

HTTP end-to-end: COA tree renders (41 rows, 11 toggles), defaults screen saves,
ledger page renders with entries, account create/edit work, system-account delete
blocked with a friendly message, viewer 302'd away from all COA pages.

## Bugs found & fixed
- `seed_default_coa()` re-declaration under `require` → `require_once` + guard.
- Seeder closure missing `$pdo` capture.
- Engine error message referenced `name` not selected in the account lookup.
- Two Phase-3 test assertions were mis-specified (fixed to assert the real invariants).

## Next phase (4)
Customers & Suppliers masters with ledgers and opening balances, plus
Payment Modes bound to bank accounts — the receivable/payable side of the engine.
