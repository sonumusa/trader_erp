# Phase 9 — Vouchers

**Goal:** complete the manual-accounting toolkit — Cash/Bank Payment, Cash/Bank
Receipt, Journal and Contra vouchers with deterministic automatic accounting,
plus the voucher register. Every voucher posts a balanced journal entry and can
be cancelled (mirror reversal, original kept).

## What was built

### One normalized voucher model (`vouchers` + `voucher_lines`)
| Type | Series | Automatic accounting |
|---|---|---|
| Cash Payment | `CPV-26-000001` | Dr counter / Cr Cash |
| Bank Payment | `BPV-26-000001` | Dr counter / Cr Bank (mode account) |
| Cash Receipt | `CRV-26-000001` | Dr Cash / Cr counter |
| Bank Receipt | `BRV-26-000001` | Dr Bank / Cr counter |
| Journal | `JV-26-000001` | free balanced multi-line |
| Contra | `CV-26-000001` | Cash ↔ Bank transfer |

**Automatic counter-account resolution** (spec §64 — users never pick GL accounts
for normal flows):
- party = supplier → **Accounts Payable** (payment: Dr AP; receipt: Cr AP)
- party = customer → **Accounts Receivable** (receipt: Cr AR; refund payment: Dr AR)
- no party → the selected leaf account (required), with sensible defaults offered
  in the UI (expenses for payments, income for receipts)

**Bank account resolution** — bank vouchers use the linked account of the selected
bank payment mode; falling back to the company `bank` default; group-account
defaults are rejected with a helpful message. A `Main Bank Account` leaf is now
seeded under the Bank Accounts group and mapped as the `bank` default.

### Voucher form (single screen, type-driven)
- Type selector switches panels (party/amount, contra, journal lines).
- Party-aware: picking customer/supplier reveals the party dropdown and the
  counter account auto-resolves (hint text explains it).
- Journal: dynamic line grid (account + debit + credit + narration), debit must
  equal credit — enforced server-side by the engine.
- Contra: from/to cash-bank accounts + amount.

### Register, view, print
- Voucher register with type + date-range filters, party, narration, amount, status.
- View page shows the accounting lines with party badges, **View Accounting**
  (journal entries), **Customer/Supplier Ledger** links, print (A4 voucher
  layout), permission-gated cancel with reason + audit.

### Support
- Schema `009_vouchers.sql` (vouchers + voucher_lines, check constraint for
  one-sided lines); permissions `accounting.voucher` (view/create/cancel/print);
  role grants for administrator + accountant; sidebar "Vouchers" under
  ACCOUNTING.
- COA seeder now includes the Main Bank Account leaf + `bank` default (defaults
  count 16 → 18).

## Verification

`php bin/test.php` — **327/327 PASS** (303 previous + 24 Phase 9).

- CRV: cash_receipt series, customer outstanding −2,000, Cash Dr / AR Cr party
  line, balanced
- CPV: cash_payment series, supplier outstanding −1,500, balanced
- BRV via bank mode: bank +3,000; BPV without party: bank −5,000, expense
  account Dr 5,000
- JV: balanced multi-line posts; **unbalanced journal voucher rejected**
- Contra: cash −10,000 / bank +10,000, balanced
- Cancel: status cancelled, cash reverses, JEs reversed
- Trial balance still balanced after all vouchers

HTTP end-to-end: all 7 voucher routes 200 (incl. per-type new forms); CRV, CPV,
BPV, JV and Contra all created via the real forms; list shows 7 vouchers; filter
by type works (CRV / JV only); view/print/journal render; cancel flow works;
register filters correct; zero new web error-log entries.

## Next phase (10)
**Costing** — already implemented inside the inventory engine (Phase 5): moving
average / weighted / FIFO / LIFO with controlled revaluation. This phase focuses
on hardening the costing-method change flow and the revaluation audit.
