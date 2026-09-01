# Phase 14 — Responsive / Mobile Optimization

**Goal:** make the mobile experience genuinely usable (not merely technically
responsive) across every key form, and fix a critical asset-serving bug found in
the dev environment.

## What was built

### Mobile CSS polish (`public/assets/css/app.css`)
- **Touch targets**: buttons min-height 40px (34px for small), nav items taller,
  form controls min-height 42px.
- **iOS zoom prevention**: form controls use `font-size: 16px` on mobile so iOS
  doesn't zoom the page when focusing an input.
- **Fixed save bar**: every document form's action row (`form > .d-flex.mb-4`)
  becomes a **fixed bottom bar** on ≤768px — Post/Cancel always reachable while
  scrolling a long invoice/voucher form. `.erp-content` gains bottom padding so
  content clears the bar.
- **Bottom-sheet modals** on phones: modals slide up full-width, rounded top,
  scrollable body.
- **Item suggestion list** full-width on mobile; compact stat cards (hide the
  note line); smooth horizontal scroll for tables; 575px tier refines the page
  header, card headers, auth card.
- **Print rules**: hide sidebar/topbar/alerts/no-print chrome, reset layout,
  bordered stat cards.

### Item picker (mobile-friendly)
- **Enter selects the first suggestion** (works with mobile keyboards and barcode
  scanners); **Escape closes** the list.

### Critical fix: static assets under the dev server
- PHP's built-in server routes *every* request through the router script, so
  CSS/JS were returning **404** — the live preview was unstyled. The front
  controller now short-circuits `cli-server` requests for existing files
  (`return false` → the built-in server sends them with proper MIME types).
  Apache/nginx (Hostinger) are unaffected (`.htaccess` serves files directly).
- Verified: `app.css`, `app.js`, `item-picker.js`, Bootstrap CSS/JS and the
  icon font all return 200.

## Verification

`php bin/test.php` — **392/392 PASS** (380 previous + 12 Phase 14).

- responsive breakpoints (991/767/575px) present; off-canvas sidebar;
  fixed save-bar rule; bottom-sheet modal rule; 16px input zoom prevention;
  print rules hide chrome
- viewport meta on every page; hamburger present
- **all 12 document forms** carry the mobile save-bar pattern
- item picker Enter-to-select + Escape-close
- dev-server static fallthrough regression check

HTTP: all static assets resolve 200 (Bootstrap CSS/JS, icons, app CSS/JS,
item picker); the sales invoice form renders the save-bar pattern; zero new web
error-log entries.

## Next phase (15)
**Security & performance hardening** — login throttling, file upload
validation, security header pass, query/index review, and a full regression run.
