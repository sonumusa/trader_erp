# Deploying TradeERP on Hostinger Shared Hosting

TradeERP is engineered for Hostinger shared hosting: **Core PHP, MySQL, no Node.js,
no build step**. This guide walks through a clean deployment.

---

## 1. What you need

- Hostinger account (any shared plan with PHP ≥ 8.1 — all current Hostinger plans qualify)
- Your site's domain (or subdomain) and the hPanel credentials
- A MySQL database (create one in hPanel → **Databases → MySQL Databases**)

Verify PHP requirements first: hPanel → **Advanced → PHP Configuration** →
check that these extensions are enabled: `pdo_mysql`, `mbstring`, `openssl`,
`curl`, `json`, `zip`, `gd`.

---

## 2. Upload the files

**Option A — document root at the project folder** (recommended):

1. Upload the whole project into `public_html/` (FTP or File Manager).
   Upload **all** files including `.htaccess` files (File Manager: enable
   *Show hidden files*; FTP: make sure hidden files are transferred).
2. The root `.htaccess` blocks web access to `app/`, `config/`, `database/`,
   `routes/`, `storage/`, `install/` and routes everything else through
   `public/`.

**Option B — subfolder install** (e.g. `https://site.com/erp`):

Upload into `public_html/erp/`. The application detects its base URL
automatically (subfolder installs work out of the box).

**Option C — point the document root at `public/`** (cleanest):

Hostinger hPanel → **Hosting → Websites → [your site] → Manage** →
set the document root to the `public` folder. Then only `public/` is web-visible.

All three options work; A and C are preferred. If you deploy Option A and see
directory listings or the app's private files, your `.htaccess` files were not
uploaded (hidden files missing).

---

## 3. Create the database

In hPanel: **Databases → MySQL Databases**

- Database name: e.g. `u123456_erp`
- Username: e.g. `u123456_erp`
- Password: use the password generator
- Host: `localhost` (Hostinger uses `localhost` for MySQL on shared hosting)

Note the credentials — you'll enter them in the installer.

---

## 4. Run the installer

1. Visit `https://yourdomain.com/install.php` (or `https://site.com/erp/install.php`).
2. **Step 1 — Requirements:** the wizard checks PHP version, extensions and
   writable folders. If `config/` or `storage/` are not writable, change their
   permissions to `755` (or `775` if needed) via File Manager → *Permissions*.
3. **Step 2 — Database:** enter the host (`localhost`), database name, user and
   password from step 3. The wizard tests the connection (and creates the
   database if your user has CREATE privilege).
4. **Step 3 — Admin & Company:** create the super admin account, company name,
   currency and financial year.
5. **Step 4 — Finish:** done. Sign in.

The installer:
- creates all tables with indexes and foreign keys
- seeds 8 roles, the permission catalogue, 17 feature toggles, company + Head
  Office, financial year, default warehouse, settings and your super admin
- writes `config/config.php` (real credentials — keep it private)
- creates `storage/installed.lock` and refuses to run again

---

## 5. Post-install security checklist

- [ ] **Delete `install.php`** and the `install/` folder from the server.
- [ ] Verify `https://yourdomain.com/config/` returns **403 Forbidden**
      (the `.htaccess` denies it; if you see a listing, .htaccess isn't active —
      enable *mod_rewrite* in hPanel → Advanced → Apache configuration, or add
      `AllowOverride All` via support).
- [ ] Confirm `storage/` is not reachable over HTTP.
- [ ] Login as your super admin and go to **Settings** → verify company name/currency.
- [ ] Check **Features** — turn on/off modules as your client needs.
- [ ] Create an `Administrator` account for the client and keep the super admin
      account for yourself.

---

## 6. Maintenance

**Backups:** use hPanel → **Files → Backups** for full snapshots, and export the
database with hPanel → **Databases → phpMyAdmin → Export** (SQL). The in-app
backup module arrives in a later phase.

**Updates:** upload changed files only. `config/config.php` and
`storage/installed.lock` are machine-specific — never overwrite them with
another server's copies.

**Logs:** application errors go to `storage/logs/error.log` and PHP errors to
`storage/logs/php-error.log`. Users never see raw errors — they get a friendly
message instead.

---

## 7. Performance notes for shared hosting

- The app uses indexed queries, pagination and server-side filtering throughout;
  it never loads the whole database into the browser.
- If your database grows large, keep phpMyAdmin's `ANALYZE TABLE` habits and
  let the reporting engine use its indexes.
- Enable OPcache in hPanel → PHP Configuration for faster page loads.
- Static assets (Bootstrap) are served locally — no CDN dependency.
