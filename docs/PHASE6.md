# Phase 6 — Purchase

**Goal:** the complete purchase cycle — quotations, orders, invoices and returns —
where every invoice immediately receives stock and posts balanced double-entry
accounting, and every return reverses the effects. No draft step, no manual GL.

## What was built

### Purchase documents (workflow PQ → PO → PI, or any subset, or direct)
- **Purchase Quotation** (`PQ-26-000001`) — capture supplier quotes; no
  stock/accounting effect.
- **Purchase Order** (`PO-26-000001`) — can reference a quotation; "Create
  Invoice" on the order list opens a **prefilled** invoice form (lines copied).
- **Purchase Invoice** (`PI-26-000001`) — the real transaction:
  - receives stock via `InventoryEngine` (item UOM conversion applied;
    batch/serial/expiry respected when enabled)
  - posts balanced accounting through `AccountingEngine`:
    `Inventory Dr (net) / Tax Receivable Dr / Accounts Payable Cr (party-tagged)`
  - optional **payment mode + paid amount** → immediate cash/bank payment
    voucher (Cash Payment / Bank Payment series) in the same transaction
  - all inside one DB transaction — stock can never update without the books
- **Purchase Return** (`PR-26-000001`) — optional reference to the original
  invoice (validated: posted + same supplier; a return **cannot** reference a
  cancelled invoice), or standalone. Reverses stock out, AP Dr, Inventory Cr,
  Tax Receivable Cr — all balanced.

### Line engine (`PurchaseService`)
- Single `calculateLine()` shared by all four document types — deterministic
  (spec §60): UOM conversion → base_qty, rate per stock unit, gross, line
  discount (capped), net, **tax from the item's tax category**, amount.
- `totals()` → subtotal / discount / tax / total; stored on every document.
- `linesFromInput()` parses the dynamic form rows; `linesFromDocument()` feeds
  quotation→order→invoice prefill.

### Document detail & navigation
- View pages with totals, item table, **View Accounting** (journal entries),
  **Supplier Ledger** link, Print, Cancel (permission-gated, reason, audit).
- Printable layout (standalone `layout/print.php`) with company header, supplier
  details, item table, totals, signature lines, cancellation banner.

### Support
- Schema `006_purchase.sql` (8 tables with FKs); shared role-grant syncer
  `config/role_grants.php` + `004_role_grants.php` (migrate.php and installer
  keep system-role defaults additive — custom edits preserved).
- Permissions: `purchase.purchase_quotation/order/invoice/return` with
  view/create/cancel/print(/export).
- Dashboard "Today's Purchase" now sums posted `purchase_invoices.total` for
  the day (document-driven, honest).

## Verification

`php bin/test.php` — **247/247 PASS** (210 previous + 37 Phase 6).

- credit invoice: PI-26-000001, totals 2,150, stock +20, AP +2,150, journal
  balanced with supplier party line
- UOM conversion: 2 CTN @ 2,640 → +48 pcs, PI-26-000002
- tax: 17% → tax_total 170, Tax Receivable debited, balanced
- cash purchase: total = paid, net supplier outstanding unchanged, cash −1,000,
  cash_payment voucher posted
- PQ-26-000001 → PO-26-000001 (references quotation) → invoice prefill lines
- return: PR-26-000001, stock −5, AP −550, balanced journal; standalone return
  without reference allowed
- cancel invoice: status cancelled + reason, stock reversed (−48), AP reduced
  by 5,280, JEs reversed, trial balance still balanced

HTTP end-to-end: all 12 purchase routes 200; invoice created via form
(PI-26-000006, 10 × 150 = 1,500 posted, stock +10); view/print/journal render;
supplier ledger shows the PI; order created; invoice-from-order prefills 8 @ 140;
return prefilled from posted invoice and created (PR-26-000003, ref invoice 1);
**return against a cancelled invoice correctly rejected**; cancel invoice flow
works; zero new error-log entries.

## Bugs found & fixed
- `items.company_id` missing in Phase-1 schema — added in migration 005.
- `InventoryEngine::receive()` call in the invoice service lost its
  `document_type` key during a refactor → restored.
- `PurchaseReturnController` missing `use` for `PurchaseInvoiceService` (class
  looked up in the wrong namespace).
- Accounting-defaults count assertion updated for the new Tax Receivable default
  (16 → 17).
- Supplier-outstanding assertion made delta-based (Phase-4 balances pre-exist).

## Next phase (7)
**Sales** — Sales Invoices (direct / from order / from quotation) posting stock
out + COGS + receivables/cash + tax through the same engines, plus Sales Orders
and Quotations — mirroring the purchase cycle.
