# Phase 10 — Costing

**Goal:** harden the inventory costing system — a **controlled, audited**
costing-method change flow (Moving Average / Weighted Average / FIFO / LIFO),
independent valuation verification, and an item-wise cost report. The four
costing engines themselves were built in Phase 5; this phase makes changing
between them safe and provable.

## What was built

### Controlled costing-method change (`InventoryEngine::changeCostingMethod`)
- Replays the company's **entire stock_ledger history** through the new method
  inside **one database transaction** — a failure mid-replay rolls back, so
  historical valuation can never be left half-migrated.
- Records every change in the new **`costing_method_history`** table:
  old method → new method, movements replayed, user, note, timestamp.
- Writes an **audit entry** (`costing_change`) with the same details.
- Same-method requests are no-ops; unknown methods are rejected.

### Valuation verification (`InventoryEngine::verifyValuation` + `simulateValuation`)
- `simulateValuation()` replays the stock ledger in **pure-PHP array math**
  through the current method — an independent code path from the DB layer engine.
- `verifyValuation()` cross-checks every item+warehouse:
  - **layer qty == ledger net qty** (the hard integrity invariant)
  - **layer value == simulated value** within a documented relative tolerance
    (the engine rounds rates to 4dp per step; simulation doesn't — real drift
    is orders of magnitude larger).
- Available to auditors and used by the test suite after every method switch.

### Costing-method change UI
- The **Settings** form no longer writes `inventory_cost_method` directly
  (that would bypass revaluation). The method is shown read-only with a
  **"Change"** button opening a confirm modal: warning banner, new-method
  select, optional note, and a "Change & Revalue" action posting to
  `POST /inventory/costing/update` (permission: `settings.setting.edit`).
- The Settings page also lists the **costing change history** (old → new,
  movements, by whom, when).

### Item-wise cost report (`/inventory/item-cost`)
- Per item: on-hand qty, **average rate**, **valuation**, last purchase rate,
  last issue rate, default sales rate — all from stock_ledger + stock_layers
  (never parallel totals), with search and a filtered total.

### Support
- Migration `010_costing.sql` (costing_method_history); sidebar
  **Inventory → Item-wise Cost**; INVENTORY nav section restored (was missing).

## Verification

`php bin/test.php` — **343/343 PASS** (327 previous + 16 Phase 10).

- method change records history (old→new, user, note, movements replayed) + audit
- **revaluation is deterministic**: FIFO value stable across FIFO→LIFO→FIFO switches
- active-method COGS: FIFO consumes the oldest lot (50 @ 80 = 4,000); LIFO consumes
  the newest lot (30 @ 150 = 4,500) — both verified with fresh lots
- weighted average accepted; its valuation equals moving average (same layer math)
- `verifyValuation()` clean after all switches (qty + simulated value match)
- unknown method rejected; item-wise cost rows + history listed

HTTP end-to-end: settings page shows the disabled select + Change modal +
history; costing POST switched MA→FIFO (history row: 48 movements replayed,
audit entry with the note), restored to MA; item-cost report renders with real
valuation (110,145) and per-item rows; all 12 core routes 200; zero new web
error-log entries.

## Bugs found & fixed
- `InventoryController::itemCost()` referenced `SettingsService` without the
  import — added.
- `verifyValuation()` originally compared layer value against **original** ledger
  rates — wrong after revaluation (the ledger is a chronological audit; layers
  reflect the current method). Replaced with the independent simulation check.
- The LIFO test lot was dated before earlier test history, so it wasn't the
  newest layer — re-dated the lot past all prior history.

## Next phase (11)
**Reports** — the reusable reporting engine: Sales/Purchase registers, ledgers,
outstanding + aging, stock reports, Trial Balance, Profit & Loss, Balance Sheet,
Cash/Bank books, with filters, ASC/DESC sorting and Excel/PDF/CSV export.
