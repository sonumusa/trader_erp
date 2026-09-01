# Phase 5 — Items, UOMs & the Inventory Engine

**Goal:** the trading side of the system — item master with item-specific UOMs,
a real inventory engine (stock ledger, costing, transfers, adjustments, opening
stock, batches/serials/expiry) and configurable taxes. All stock movements go
through one engine; valuation comes from cost layers; reports never maintain
parallel totals.

## What was built

### Items & item groups
- `ItemController` — full master: code, name, **barcode**, group, brand,
  description, default purchase/sales rates, stock UOM, min stock, reorder,
  batch/serial/expiry flags, status, account overrides (inventory/COGS/sales/
  purchase), tax category.
- **AJAX item search** (`GET /items/search?q=`) — code / name / barcode,
  LIMIT 20, returns the item + **only its configured UOMs** for the picker.
- `ItemGroupController` — CRUD with account overrides feeding the fallback
  hierarchy **item → item group → company default** (`AccountDefaultService::forItem`).

### Item-specific UOM system (spec §21)
- UOMs belong to the item (`item_uoms`). The transaction picker shows **only**
  the selected item's UOMs. Conversion: `base_qty = qty × factor`,
  `rate_per_stock = rate ÷ factor`, amount invariant under conversion.
- Stock UOM (factor 1) + any number of alternatives (1 Carton = 24 Pieces).

### Inventory engine (`InventoryEngine`)
- Single gateway for every movement — `receive()` / `issue()` update the
  **stock_ledger** (running balance per item+warehouse, ordered by entry date/id)
  and the **cost layers**.
- **Costing methods** (setting `inventory_cost_method`): moving average /
  weighted average (continuous weighted-rate layer) and **FIFO / LIFO**
  (distinct layers consumed oldest/newest first). COGS is computed from layers.
- **Controlled revaluation**: changing the method runs `revalueAll()`, which
  replays the full stock_ledger history through the new method — historical
  valuation stays deterministic and consistent (tested).
- **Negative stock guard**: `allow_negative_stock` setting; when off, issues
  beyond available are blocked with a clear item/warehouse/available message.
- **Optional batch / serial / expiry** (per item + system feature toggles):
  batch receipt with qty/rate/mfg/expiry; expiry blocks issue of expired lots;
  serial registration with duplicate detection; issue consumes serials.
- `availableQty`, `valuation`, `averageRate`, paginated stock ledger,
  stock balance (with live valuation), low-stock/reorder list.

### Documents
- **Stock transfer** (`StockTransferService`): issue from source + receive into
  destination under one number (ST-26-000001). **No fake sales/purchase
  accounting** for internal moves (verified by test). Reversal moves stock back
  and marks the transfer cancelled.
- **Stock adjustment** (`StockAdjustmentService`): signed quantities with
  reason; posts REAL balanced accounting (increase → Dr Inventory / Cr Stock
  Adjustment; decrease → reverse) via `AccountingEngine`.
- **Opening stock** (`OpeningStockService`): stock ledger rows + total value
  posted to the books (Dr Inventory / Cr Opening Balance Equity) atomically.

### Taxes
- `TaxController` — configurable rates per company; items carry a tax category;
  sales/purchase documents will use it automatically (Phases 6–7).

### Wiring
- Migration `005_phase5.sql` (stock ledger, layers, batches, serials, transfers,
  adjustments, taxes, `items.company_id` + `tax_id`, group account overrides).
- Permissions `inventory.item / item_group / transfer / adjustment / stock /
  opening_stock`, `settings.tax`; grants updated for admin/manager/inventory/
  accountant. Setup wizard steps: Tax + Opening Stock now live.

## Verification

`php bin/test.php` — **210/210 PASS** (165 previous + 45 Phase 5).

- item + UOM sync (2 UOMs only, 1 CTN = 24 PCS, base/rate conversion,
  unconfigured UOM rejected), searchable by barcode
- opening stock: ledger row + inventory Dr 10,000 + balanced opening entry
- MA COGS (10 @ 110), **FIFO COGS 20 @ 110 = 2,200**, **LIFO COGS 10 @ 140 = 1,400**
- `revalueAll()` switches method and keeps valuation = balance
- negative stock blocked when disabled / allowed when enabled
- transfer posts ST-26-000001, moves stock, **no accounting created**, reversal
  restores destination to zero and marks cancelled
- adjustment increase/decrease post balanced accounting
- batches (qty tracked), serials (issued status), **expired batch issue blocked**,
  **duplicate serial rejected**
- low stock flags, stock balance valuation matches layers

HTTP end-to-end: all 19 routes 200; item create, opening stock, transfer and
adjustment POSTed through the real forms; AJAX search returns item + UOMs;
stock balance shows live valuation (66,881 in test data); item ledger shows
available 423 / valuation 57,281.

## Bugs found & fixed
- `items` table was missing `company_id` (Phase-1 schema oversight) — added in
  migration 005 with FK.
- Bare `e()` calls in two views resolve to the namespaced helper — replaced with
  `htmlspecialchars`.
- Test setup collisions (PCS already created by Phase 2; second warehouse
  missing; batch/serial prerequisites on test items) — made idempotent.
- Missing `SettingsService` import in the harness.

## Next phase (6)
**Purchase** — Purchase Invoice (direct / from order / from quotation), posting
stock in + AP + tax through the accounting engine, purchase returns. Then Sales
(7), Returns (8) and Vouchers (9) complete the transaction cycle.
