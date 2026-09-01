# Phase 8 — Sales Returns

**Goal:** complete the transaction cycle — sales returns that reverse every
effect of a sale (stock back in, COGS reversal, receivable/cash reversal,
output-tax reversal), with an optional reference to the original invoice.
Purchase Returns already exist (Phase 6); both flows share the same engines.

## What was built

### Sales return document (`SR-26-000001`)
- **Reference invoice optional** — when given, it must be a posted invoice of the
  same company (customer mismatch rejected); standalone returns allowed.
- Posting a return immediately:
  - brings goods **back into stock** via `InventoryEngine` (rate = original
    invoice COGS rate when referenced, otherwise the item's current average rate)
  - posts balanced accounting through `AccountingEngine`:
      * `Sales Returns Dr (net)` — contra-income (default account 4200)
      * `Tax Payable Dr (tax)` — output tax reversed
      * `Cash or Accounts Receivable Cr (total)` — party-tagged for customers;
        walk-in refunds credit Cash
      * `Inventory Dr / COGS Cr` — cost-of-goods reversal
- **Smart reversal target**: referencing a credit sale → AR (customer's balance
  reduced); referencing a cash sale or walk-in → Cash refund; standalone with a
  customer → AR; standalone walk-in → Cash.
- **Cancel** — re-issues the returned stock, reverses all journal entries, marks
  the return `cancelled` with reason + audit; document stays in history.

### Navigation & printing
- View page: total/tax/COGS cards, reference invoice, **View Accounting**
  (journal), **Customer Ledger** link, A4 print layout, permission-gated cancel.
- Return form: customer, warehouse, optional invoice reference dropdown,
  narration, item lines prefilled from the invoice via `?from_invoice=`.

### Support
- Schema `008_sales_returns.sql` (sales_returns + sales_return_items with FKs).
- Permissions `sales.sales_return` (view/create/cancel/print) — already declared
  in `config/permissions.php` and role grants.

## Verification

`php bin/test.php` — **303/303 PASS** (280 previous + 23 Phase 8).

- credit-sale return with reference: SR-26-000001, total 600, stock +4,
  customer −600, AR Cr 600, Sales Return Dr 600, COGS/Inventory reversal at
  original rate, journal balanced, customer party line present
- tax reversal: return of a 17% item → Tax Payable Dr 34, balanced
- walk-in refund: Cash −300, **no party line** (not a receivable movement)
- standalone return without reference allowed, posts stock
- cancel return: status cancelled, stock −4 back out, customer +600 restored,
  JEs reversed, trial balance still balanced

HTTP end-to-end: return routes 200; form prefilled from invoice (SI-26-000107),
return created via form (SR-26-000005, total 300, COGS 204.96, reference kept),
view/print/journal render, cancel flow works, all transaction routes still 200,
zero new web error-log entries.

## Next phase (9)
**Vouchers** — Cash/Bank Payment, Cash/Bank Receipt, Journal and Contra vouchers
with automatic accounting from payment modes, plus the voucher register.
