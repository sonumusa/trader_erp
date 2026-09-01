<?php

/**
 * TradeERP — build a deployable Hostinger package.
 *
 * Creates dist/tradeerp-<version>.zip containing only the files needed for
 * production deployment (no tests, no dev docs, no local config/locks).
 *
 * Usage: php bin/build_package.php
 * Output: dist/tradeerp-<version>.zip
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
$version = '0.1.0';
$dist = $root . '/dist';
if (!is_dir($dist)) {
    mkdir($dist, 0755, true);
}
$out = $dist . '/tradeerp-' . $version . '.zip';
@unlink($out);

/** Directories to include (relative to root). */
$includeDirs = ['app', 'config', 'database', 'routes', 'public', 'storage', 'install'];
/** Files at the root to include. */
$includeFiles = ['install.php', '.htaccess'];

/** Never ship these. */
$excludePatterns = [
    '#^config/config\.php$#',
    '#^storage/installed\.lock$#',
    '#^storage/(logs|cache|uploads|backups|exports)/#',
    '#/\.git/#', '#\.gitignore$#',
    '#^dist/#', '#^bin/#', '#^tests/#', '#^docs/#',
    '#/\.DS_Store$#', '#Thumbs\.db$#',
];

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not create {$out}\n");
    exit(1);
}

$added = 0;
foreach ($includeDirs as $dir) {
    $base = $root . '/' . $dir;
    if (!is_dir($base)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $rel = $dir . '/' . $it->getSubPathName();
        if (selfExcluded($rel, $excludePatterns)) {
            continue;
        }
        $zip->addFile($file->getPathname(), $rel);
        $added++;
    }
}

foreach ($includeFiles as $file) {
    $path = $root . '/' . $file;
    if (is_file($path)) {
        $zip->addFile($path, $file);
        $added++;
    }
}

$zip->close();

$size = filesize($out);
echo "Package built: {$out}\n";
echo "  files: {$added}\n";
echo "  size:  " . number_format($size / 1024, 1) . " KB\n";
echo "Deploy: upload to public_html, run install.php, then delete install.php + /install.\n";

function selfExcluded(string $rel, array $patterns): bool
{
    foreach ($patterns as $p) {
        if (preg_match($p, $rel)) {
            return true;
        }
    }
    return false;
}
