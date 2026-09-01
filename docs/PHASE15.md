# Phase 15 — Security & Performance Hardening

**Goal:** close the remaining security gaps (login throttling, self-service
password reset, validated uploads, security headers) and verify performance/XSS
posture across the codebase.

## What was built

### Login throttling (spec §44) — `SecurityService`
- DB-backed `login_attempts`: failed attempts per (email, ip) within a window.
- After `security.login_max_attempts` (default 5) failures, the pair is locked
  for `security.login_lock_minutes` (default 15). Locked attempts are rejected
  **before** password verification; the login page explains the wait.
- Successful logins clear the failure history. Verified end-to-end over HTTP:
  5 bad attempts → 6th blocked with "Too many failed attempts".

### Self-service password reset (spec §44) — `PasswordResetService`
- **Forgot password** page → single-use token (sha256 hash stored, 30-min
  expiry, one use only) → emailed (shared-host `mail()`); on hosts without an
  MTA the link is logged server-side and shown in **local env only** (never
  production).
- **Anti-enumeration**: identical response whether or not the email exists;
  unknown emails receive a dummy token that cannot be used.
- Reset page validates the token, applies the new password (min length from
  config), marks the token used; **reuse rejected**, **weak passwords rejected**.
- Every request/reset is audited.

### Validated file uploads (spec §44) — `UploadService` + `attachments`
- Files stored **outside the webroot** (`storage/uploads`) with random names;
  downloads go through an authenticated, permission-checked route.
- **Extension whitelist per category** (image / document) + **finfo MIME
  verification** (content must match extension; OOXML/zip and text sniffing
  tolerated for documents only).
- Size cap 5 MB; `is_uploaded_file` enforced in production.
- Wired into Sales Invoices: upload / list / download / delete with audit.
  Verified: `.php` rejected, valid `.txt` accepted and downloadable, no
  executable ever reaches the uploads dir.

### Security headers + posture audit
- `Content-Security-Policy` (same-origin assets, inline allowed, no external
  third parties), `X-Permitted-Cross-Domain-Policies: none`, plus existing
  nosniff / SAMEORIGIN / referrer-policy.
- **XSS audit**: every view's raw array-access output is `e()`-escaped or
  int-cast or a fixed-string ternary (automated check; the one flagged value
  was escaped in the view).
- **Performance**: added `account_ledger (company_id, account_id)` index for
  the ledger-based reports; report sort columns are whitelisted (no injection).

## Verification

`php bin/test.php` — **420/420 PASS** (392 previous + 28 Phase 15).

- throttle: below/at/different-IP semantics; correct password rejected while
  locked; allowed again after the window; failures cleared on success
- reset: token hashed not plaintext; password changes; token single-use;
  reuse/weak/unknown-email paths rejected; anti-enumeration dummy token
- uploads: `.php` rejected; MIME mismatch rejected; valid `.txt` accepted with
  random stored name; linked to document; outside webroot; oversize rejected
- headers: CSP / X-Permitted / X-Frame-Options present
- XSS: no raw unescaped array outputs in any view

HTTP end-to-end: security headers all present; forgot-password → reset page
(200, token prefilled) → new password login works; token reuse shows
"Invalid or expired reset link"; throttling locks at the 6th attempt;
attachment upload listed + download 200 with correct content + delete removes;
`.php` upload rejected with a clear message; all 23 routes healthy; zero new
web error-log entries.

## Bugs found & fixed
- A `?>` inside a one-line PHP comment **exits PHP mode** and dumps the rest of
  the file as HTML — removed such tokens from the test harness comment.
- `mail()` returns true even with no MTA — local-env link display now keys off
  the environment, not `$sent`.
- The XSS audit regex backtracked (matched `$thi` inside `$this->e()`) — replaced
  with a precise array-output check; the one real flag was escaped.
- The account ledger report query lacked a covering index — added.

## Final phase (16)
**Hostinger deployment package** — deployment guide, environment checklist and
the installer's production hardening (see `docs/DEPLOYMENT.md`).
