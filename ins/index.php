<?php

/**
 * TradeERP — Web Installer
 * ------------------------
 * Self-contained: does NOT depend on the application being configured.
 * Steps: 1) requirements  2) database  3) admin & company  4) done.
 *
 * After a successful install a lock file (storage/installed.lock) is created,
 * config/config.php is written, and the wizard refuses to run again.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('ERP_ROOT', dirname(__DIR__));

session_name('tradeerp_install');
session_start();

/* ---------------- Guard: already installed? ---------------- */
$configExists = is_file(ERP_ROOT . '/config/config.php');
$lockExists   = is_file(ERP_ROOT . '/storage/installed.lock');

$step = (int) ($_GET['step'] ?? $_POST['step'] ?? 1);
if ($step < 1 || $step > 4) {
    $step = 1;
}

if ($step === 4 && !$lockExists) {
    $step = 1;
}

/* ---------------- Helpers ---------------- */
function ih(string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function iheader(string $title, int $activeStep): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . ih($title) . ' — TradeERP Installer</title>'
        . '<style>'
        . '*{box-sizing:border-box}body{margin:0;font-family:Segoe UI,-apple-system,Roboto,Arial,sans-serif;'
        . 'background:#0f172a;color:#1f2937;padding:24px}'
        . '.wrap{max-width:680px;margin:0 auto}'
        . '.card{background:#fff;border-radius:14px;padding:30px 34px;box-shadow:0 20px 50px rgba(0,0,0,.35)}'
        . '.brand{display:flex;align-items:center;gap:12px;margin-bottom:22px}'
        . '.mark{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#2563eb,#4f46e5);'
        . 'color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700}'
        . 'h1{font-size:20px;margin:0}h2{font-size:15px;margin:0 0 6px}.muted{color:#6b7280;font-size:13px}'
        . '.steps{display:flex;gap:6px;margin:16px 0 22px}'
        . '.step{flex:1;text-align:center;font-size:12px;font-weight:600;color:#9ca3af;padding:8px 4px;border-bottom:3px solid #e5e7eb}'
        . '.step.on{color:#1d4ed8;border-color:#2563eb}'
        . '.step.done{color:#059669;border-color:#059669}'
        . 'label{display:block;font-size:13px;font-weight:600;margin:14px 0 4px;color:#374151}'
        . 'input,select{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff}'
        . 'input:focus,select:focus{outline:none;border-color:#93c5fd;box-shadow:0 0 0 3px rgba(37,99,235,.15)}'
        . '.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}'
        . '.btn{display:inline-block;background:#2563eb;color:#fff;border:0;border-radius:8px;padding:11px 22px;'
        . 'font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;margin-top:20px}'
        . '.btn:hover{background:#1d4ed8}.btn-ghost{background:#eef2ff;color:#1d4ed8}'
        . '.ok{color:#059669;font-weight:600}.bad{color:#dc2626;font-weight:600}'
        . '.warn{color:#b45309;font-weight:600}'
        . '.req{display:flex;justify-content:space-between;padding:7px 2px;border-bottom:1px solid #f3f4f6;font-size:14px}'
        . '.box{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;font-size:13px;margin:12px 0}'
        . '.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:12px 16px;font-size:13px;margin:12px 0}'
        . '.okbox{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:8px;padding:14px 16px;font-size:14px;margin:12px 0}'
        . '.foot{color:#64748b;font-size:12px;text-align:center;margin-top:18px}'
        . '.mono{font-family:Consolas,Menlo,monospace;font-size:12.5px}'
        . '@media(max-width:560px){.row{grid-template-columns:1fr}.card{padding:22px 18px}}'
        . '</style></head><body><div class="wrap"><div class="card">'
        . '<div class="brand"><div class="mark">T</div><div><h1>TradeERP</h1>'
        . '<div class="muted">Trading &middot; Inventory &middot; Accounting — Installation</div></div></div>'
        . '<div class="steps">'
        . '<div class="step ' . ($activeStep >= 1 ? ($activeStep > 1 ? 'done' : 'on') : '') . '">1. Requirements</div>'
        . '<div class="step ' . ($activeStep >= 2 ? ($activeStep > 2 ? 'done' : 'on') : '') . '">2. Database</div>'
        . '<div class="step ' . ($activeStep >= 3 ? ($activeStep > 3 ? 'done' : 'on') : '') . '">3. Admin &amp; Company</div>'
        . '<div class="step ' . ($activeStep >= 4 ? 'on' : '') . '">4. Finish</div>'
        . '</div>';
}

function ifooter(): void
{
    echo '<div class="foot">TradeERP &middot; Core PHP + MySQL &middot; works on Hostinger shared hosting</div>'
        . '</div></div></body></html>';
}

/* ================= STEP 1 — REQUIREMENTS ================= */
if ($step === 1) {
    iheader('Requirements', 1);

    $checks = [
        'PHP version ≥ 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'PDO MySQL extension' => extension_loaded('pdo_mysql'),
        'MBString extension' => extension_loaded('mbstring'),
        'OpenSSL extension' => extension_loaded('openssl'),
        'JSON extension' => extension_loaded('json'),
        'cURL extension' => extension_loaded('curl'),
        'ZIP extension (backups / exports)' => extension_loaded('zip'),
        'GD extension (optional — images)' => extension_loaded('gd'),
        'config/ directory writable' => is_writable(ERP_ROOT . '/config'),
        'storage/ directory writable' => is_writable(ERP_ROOT . '/storage'),
        'Apache mod_rewrite (.htaccess)' => (function () {
            $mod = function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules(), true);
            return $mod || strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'nginx') === false; // skip on CLI/unknown
        })(),
    ];

    $ok = true;
    echo '<h2>System requirements</h2><p class="muted">The installer checks that your hosting environment can run TradeERP.</p>';
    echo '<div style="margin-top:6px">';
    foreach ($checks as $label => $pass) {
        if (!$pass) {
            $ok = false;
        }
        echo '<div class="req"><span>' . ih($label) . '</span>'
            . '<span class="' . ($pass ? 'ok' : 'bad') . '">' . ($pass ? '&#10003; OK' : '&#10007; Missing') . '</span></div>';
    }
    echo '</div>';
    echo '<div class="box mono">PHP ' . PHP_VERSION . ' &middot; ' . (php_sapi_name()) . '</div>';

    if (!$ok) {
        echo '<div class="err">Please fix the items above, then refresh this page. TradeERP requires at least PHP 8.1 with the PDO MySQL extension.</div>';
    }
    echo '<form method="post"><input type="hidden" name="step" value="2">'
        . '<button class="btn" ' . ($ok ? '' : 'disabled') . '>Continue — Database setup &rarr;</button></form>';

    ifooter();
    exit;
}

/* ================= STEP 2 — DATABASE ================= */
$dbErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) ($_POST['step'] ?? 0) === 2) {
    $dbHost = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $dbPort = (int) ($_POST['db_port'] ?? 3306);
    $dbName = trim((string) ($_POST['db_name'] ?? ''));
    $dbUser = trim((string) ($_POST['db_user'] ?? ''));
    $dbPass = (string) ($_POST['db_pass'] ?? '');

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        $dbErr = 'Database host, name and user are required.';
    } else {
        try {
            // Test connection (and create the database if missing and permitted)
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $dbName) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (PDOException) {
                // User may lack CREATE privilege — try connecting to the existing DB below
            }
            $pdo->exec("USE `" . str_replace('`', '', $dbName) . "`");

            $_SESSION['db'] = ['host' => $dbHost, 'port' => $dbPort, 'name' => $dbName, 'user' => $dbUser, 'pass' => $dbPass];
            header('Location: install.php?step=3');
            exit;
        } catch (PDOException $e) {
            $code = (string) ($e->getCode() ?? '');
            $dbErr = 'Could not connect to the database: ' . $e->getMessage();
            if (str_contains($code, '1045')) {
                $dbErr = 'Access denied. Check the database username and password. (1045)';
            } elseif (str_contains($code, '2002')) {
                $dbErr = 'Could not reach the MySQL server at ' . ih($dbHost) . '. Check the host name. (2002)';
            }
        }
    }
}

iheader('Database', 2);
echo '<h2>Database connection</h2><p class="muted">These are the MySQL details from your Hostinger control panel (Databases &rarr; MySQL Databases).</p>';
if ($dbErr !== '') {
    echo '<div class="err">' . ih($dbErr) . '</div>';
}
echo '<form method="post">';
echo '<input type="hidden" name="step" value="2">';
echo '<div class="row">';
echo '<div><label>Database host</label><input name="db_host" value="' . ih($_SESSION['db']['host'] ?? 'localhost') . '" placeholder="localhost"></div>';
echo '<div><label>Port</label><input name="db_port" value="' . ih($_SESSION['db']['port'] ?? '3306') . '"></div>';
echo '</div>';
echo '<label>Database name</label><input name="db_name" value="' . ih($_SESSION['db']['name'] ?? '') . '" placeholder="e.g. u123456_erp"></div>';
echo '<div class="row">';
echo '<div><label>Database user</label><input name="db_user" value="' . ih($_SESSION['db']['user'] ?? '') . '" placeholder="e.g. u123456_erp"></div>';
echo '<div><label>Database password</label><input type="password" name="db_pass" value="' . ih($_SESSION['db']['pass'] ?? '') . '"></div>';
echo '</div>';
echo '<button class="btn">Test connection &amp; continue &rarr;</button>';
echo '</form>';
ifooter();
exit;

/* ================= STEP 3 — ADMIN & COMPANY ================= */
$step3Err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) ($_POST['step'] ?? 0) === 3) {
    $adminName  = trim((string) ($_POST['admin_name'] ?? ''));
    $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
    $adminPass  = (string) ($_POST['admin_pass'] ?? '');
    $companyName = trim((string) ($_POST['company_name'] ?? ''));
    $companyCode = trim((string) ($_POST['company_code'] ?? ''));
    $currency   = (string) ($_POST['currency'] ?? 'PKR');
    $fyStart    = (string) ($_POST['fy_start'] ?? '');
    $fyEnd      = (string) ($_POST['fy_end'] ?? '');

    if ($adminName === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($adminPass) < 8) {
        $step3Err = 'Please enter the admin name, a valid email, and a password of at least 8 characters.';
    } elseif ($companyName === '') {
        $step3Err = 'Company name is required.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fyStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fyEnd) || $fyStart >= $fyEnd) {
        $step3Err = 'Enter a valid financial year range (start date before end date).';
    } else {
        try {
            $db = $_SESSION['db'];
            $port = (int) $db['port'];
            $dsn = "mysql:host={$db['host']};port={$port};dbname={$db['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // ---- Run schema (all migrations, in order) ----
            $migrationFiles = glob(ERP_ROOT . '/database/migrations/*.sql');
            sort($migrationFiles);
            if (!$migrationFiles) {
                throw new RuntimeException('No migration files found in database/migrations/.');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($migrationFiles as $sqlFile) {
                $sql = (string) file_get_contents($sqlFile);
                $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));
                foreach ($statements as $statement) {
                    $clean = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));
                    if ($clean !== '') {
                        $pdo->exec($clean);
                    }
                }
                // Record the migration for future upgrade runs
                try {
                    $pdo->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (?, ?)')
                        ->execute([basename($sqlFile), date('Y-m-d H:i:s')]);
                } catch (Throwable $e) {
                    // schema_migrations may not exist yet on the very first migration
                    if (!str_contains($e->getMessage(), 'schema_migrations')) {
                        throw $e;
                    }
                }
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            // ---- Seed defaults ----
            require ERP_ROOT . '/database/seeders/001_defaults.php';
            $fyName = date('Y', strtotime($fyStart)) . '-' . date('Y', strtotime($fyEnd));
            $seed = run_installer_seed($pdo, [
                'company_name'   => $companyName,
                'company_code'   => $companyCode,
                'currency'       => $currency,
                'timezone'       => 'Asia/Karachi',
                'admin_name'     => $adminName,
                'admin_email'    => $adminEmail,
                'admin_password' => $adminPass,
                'fy_name'        => $fyName,
                'fy_start'       => $fyStart,
                'fy_end'         => $fyEnd,
            ]);

            // ---- Default Chart of Accounts + Accounting Defaults ----
            require ERP_ROOT . '/database/seeders/002_coa.php';
            seed_default_coa($pdo, (int) $seed['company_id']);

            // ---- Default payment modes ----
            require ERP_ROOT . '/database/seeders/003_payment_modes.php';
            seed_default_payment_modes($pdo, (int) $seed['company_id']);

            // ---- System role grants (from config/role_grants.php) ----
            require ERP_ROOT . '/database/seeders/004_role_grants.php';
            ensure_role_grants($pdo);

            // ---- Write config/config.php ----
            $configTemplate = <<<'PHP'
<?php

// TradeERP — configuration generated by the installer.
// Keep this file private (never commit real credentials).

return [

    'app' => [
        'name'      => 'TradeERP',
        'env'       => 'production',
        'debug'     => false,
        'url'       => '',
        'timezone'  => 'Asia/Karachi',
        'locale'    => 'en',
        'currency'  => '{{CURRENCY}}',
        'version'   => '0.1.0',
    ],

    'database' => [
        'host'      => '{{DB_HOST}}',
        'port'      => {{DB_PORT}},
        'name'      => '{{DB_NAME}}',
        'user'      => '{{DB_USER}}',
        'pass'      => '{{DB_PASS}}',
        'charset'   => 'utf8mb4',
    ],

    'session' => [
        'name'      => 'tradeerp_session',
        'lifetime'  => 7200,
        'inactive'  => 1800,
        'secure'    => false,
        'httponly'  => true,
        'samesite'  => 'Lax',
    ],

    'security' => [
        'login_max_attempts'  => 5,
        'login_lock_minutes'  => 15,
        'password_min_length' => 8,
    ],

];
PHP;

            $replace = [
                '{{DB_HOST}}'   => $db['host'],
                '{{DB_PORT}}'   => (string) (int) $db['port'],
                '{{DB_NAME}}'   => $db['name'],
                '{{DB_USER}}'   => $db['user'],
                '{{DB_PASS}}'   => $db['pass'],
                '{{CURRENCY}}'  => $currency,
            ];
            $configContent = strtr($configTemplate, $replace);
            $written = @file_put_contents(ERP_ROOT . '/config/config.php', $configContent, LOCK_EX);

            if (!$written) {
                throw new RuntimeException('Could not write config/config.php. Check directory permissions.');
            }

            @chmod(ERP_ROOT . '/config/config.php', 0644);

            // ---- Lock file ----
            @file_put_contents(ERP_ROOT . '/storage/installed.lock', date('Y-m-d H:i:s') . "\n", LOCK_EX);
            @chmod(ERP_ROOT . '/storage/installed.lock', 0644);

            header('Location: install.php?step=4');
            exit;
        } catch (Throwable $e) {
            $step3Err = 'Installation failed: ' . $e->getMessage();
            @error_log('[TradeERP installer] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}

if ($configExists || $lockExists) {
    $step3Err = 'The application appears to be already installed. If you are reinstalling, delete config/config.php and storage/installed.lock first.';
}

iheader('Admin & Company', 3);
echo '<h2>Administrator &amp; Company</h2><p class="muted">The super admin account and the first company (with Head Office).</p>';
if ($step3Err !== '') {
    echo '<div class="err">' . ih($step3Err) . '</div>';
}
echo '<form method="post">';
echo '<input type="hidden" name="step" value="3">';

echo '<h2 style="margin-top:18px;font-size:13px;color:#4b5563">ADMINISTRATOR</h2>';
echo '<div class="row">';
echo '<div><label>Full name</label><input name="admin_name" value="' . ih($_POST['admin_name'] ?? '') . '" required></div>';
echo '<div><label>Email</label><input type="email" name="admin_email" value="' . ih($_POST['admin_email'] ?? '') . '" required></div>';
echo '</div>';
echo '<label>Password</label><input type="password" name="admin_pass" required placeholder="At least 8 characters">';

echo '<h2 style="margin-top:18px;font-size:13px;color:#4b5563">COMPANY</h2>';
echo '<div class="row">';
echo '<div><label>Company name</label><input name="company_name" value="' . ih($_POST['company_name'] ?? '') . '" required></div>';
echo '<div><label>Company code</label><input name="company_code" value="' . ih($_POST['company_code'] ?? '') . '" placeholder="e.g. LHR"></div>';
echo '</div>';
echo '<div class="row">';
echo '<div><label>Currency</label><select name="currency">'
    . '<option value="PKR">PKR — Pakistani Rupee</option>'
    . '<option value="USD">USD — US Dollar</option>'
    . '<option value="SAR">SAR — Saudi Riyal</option>'
    . '<option value="AED">AED — UAE Dirham</option>'
    . '<option value="GBP">GBP — British Pound</option>'
    . '</select></div>';
echo '<div><label>Financial year start</label><input type="date" name="fy_start" value="' . ih($_POST['fy_start'] ?? date('Y') . '-07-01') . '" required></div>';
echo '</div>';
echo '<label>Financial year end</label><input type="date" name="fy_end" value="' . ih($_POST['fy_end'] ?? (date('Y') + 1) . '-06-30') . '" required>';
echo '<div class="box">The financial year is used for document numbering and reporting periods. You can add more years later in Settings.</div>';

echo '<button class="btn">Install TradeERP &rarr;</button>';
echo '</form>';
ifooter();
exit;

/* ================= STEP 4 — DONE ================= */
iheader('Complete', 4);
echo '<div class="okbox"><b>&#10003; TradeERP installed successfully!</b></div>';
echo '<h2>What happens next</h2>';
echo '<ul class="muted" style="line-height:1.9">';
echo '<li>Your company, Head Office, financial year and Super Admin account are ready.</li>';
echo '<li>The default Chart of Accounts, payment modes and document numbering arrive in the next build phase.</li>';
echo '<li>Use the <b>Setup Wizard</b> (coming in Phase 2) to add branches, warehouses, UOMs, taxes and opening balances.</li>';
echo '</ul>';
echo '<div class="box"><b>Security:</b> please delete <span class="mono">install.php</span> (and the <span class="mono">/install</span> directory) from your server now. '
    . 'The installer is locked and cannot run again while <span class="mono">storage/installed.lock</span> exists, but removing the files is the safest practice.</div>';
echo '<a class="btn" href="login">Sign in to TradeERP &rarr;</a>';
ifooter();
exit;
