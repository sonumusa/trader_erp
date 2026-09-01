# Phase 7 — Sales

**Goal:** the sales cycle — quotations, orders and invoices — where every invoice
immediately issues stock, records COGS from the costing engine, and posts
balanced double-entry accounting (Cash/Receivable Dr / Sales Cr / Tax Payable Cr,
plus COGS Dr / Inventory Cr), with optional instant receipts. Mirrors the
purchase cycle (Phase 6) through the same engines.

## What was built

### Sales documents (workflow SQ → SO → SI, or any subset, or direct)
- **Sales Quotation** (`SQ-26-000001`) — customer pricing proposal; no
  stock/accounting effect.
- **Sales Order** (`SO-26-000001`) — references a quotation; "Create Invoice"
  prefills the invoice form with the order lines.
- **Sales Invoice** (`SI-26-000001`) — the real transaction:
  - issues stock via `InventoryEngine` (COGS from cost layers — moving average,
    FIFO, LIFO as configured)
  - posts balanced accounting through `AccountingEngine`:
      * credit sale: `Accounts Receivable Dr (customer-tagged) / Sales Cr / Tax Payable Cr`
      * cash/walk-in sale: `Cash-or-Bank Dr / Sales Cr / Tax Payable Cr`
      * every line: `COGS Dr / Inventory Cr` at the issue cost
  - optional **payment mode + received amount**:
      * full payment = cash sale (Cash Dr, no receivable movement)
      * partial payment on a credit sale = separate `Cash Receipt / Bank Receipt`
        voucher in the same transaction
  - atomic: stock can never move without the books
- **Cancel** — restocks the goods, reverses all journal entries, marks the
  invoice `cancelled` with reason + audit; the document stays in history.

### Business-rule correctness
- Walk-in sales (no customer, or fully settled) post to **Cash**, never to
  Accounts Receivable — a customer's outstanding only reflects true credit
  movements (verified by test).
- Sales are credited **net of discounts**; output tax goes to Tax Payable.
- COGS is computed by the selected costing method, so gross profit on the
  invoice list, view page and (future) reports all come from the same source.

### Navigation & printing
- View pages: totals + profit cards, **View Accounting** (journal entries),
  **Customer Ledger** link, Print (A4 layout with customer header, totals,
  signature lines), permission-gated Cancel with reason.

### Support
- Schema `007_sales.sql` (6 tables with FKs); permissions
  `sales.sales_quotation/order/invoice` (view/create/cancel/print/export);
  role grants already declared in `config/role_grants.php`.
- Dashboard "Today's Sales" sums posted `sales_invoices.total` for the day
  (document-driven).

## Verification

`php bin/test.php` — **280/280 PASS** (247 previous + 33 Phase 7).

- credit sale: SI series, total 3,000, stock −20, COGS at moving average,
  customer +3,000, AR Dr 3,000 / Sales Cr 3,000 / COGS Dr = Inventory Cr in the
  journal, balanced, customer party line present
- cash sale (walk-in): Cash +1,500, stock −10, **no party line** (not a
  receivable movement)
- tax: 17% → Tax Payable Cr 170, balanced
- credit sale + partial receipt: total 1,500 / received 500 → customer net +1,000,
  cash_receipt voucher posted
- SQ-26-000001 → SO-26-000001 (references quotation) → invoice prefill lines
- cancel: status cancelled, stock restored +20, customer −3,000, JEs reversed,
  trial balance still balanced

HTTP end-to-end: all sales routes 200; credit sale via form (SI-26-000106,
1,500 / COGS 1,042.48), walk-in cash sale (1,500 received, posted), view/print/
journal render, customer ledger shows the SI, quotation → order form prefilled,
cancel flow works, dashboard "Today's Sales" shows the live figure, zero new
web error-log entries.

## Bugs found & fixed
- **Walk-in/cash sales posted to Accounts Receivable** when no customer was set —
  reworked settlement logic: full settlement or no customer → Cash; only true
  credit sales touch AR (with party tag).
- `PurchaseService::linesFromDocument` lacked the sales tables → quotation/order
  prefill returned empty; added `sales_quotation_items/order_items/invoice_items`.
- Test assertions collided on `source_document_id` (shared across document
  types) — switched to querying the specific journal entry; numbering and COGS
  assertions made relative (series counter + moving-average blending).

## Next phase (8)
**Returns** — Sales Returns (reverse stock back in, COGS reversal, receivable/
cash reversal, tax reversal, optional invoice reference) completing the
transaction cycle; Purchase Returns (Phase 6) already exist.
