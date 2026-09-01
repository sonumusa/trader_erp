<?php

declare(strict_types=1);

/**
 * TradeERP — Front Controller
 * All requests are routed through this file (see public/.htaccess).
 * The web installer (install.php at the project root) runs *before* the
 * application so an unconfigured install is not reachable.
 */

// Under PHP's built-in dev server every request reaches this router.
// Serve existing static assets directly (Apache/nginx handle this via
// .htaccess/rewrite rules, so this branch only matters for `php -S`).
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    if ($path !== '/' && $path !== '/index.php' && is_file(__DIR__ . $path)) {
        return false; // let the built-in server send the file with proper MIME
    }
}

// If the application is not yet installed, show a maintenance notice.
if (!file_exists(dirname(__DIR__) . '/config/config.php')) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<title>TradeERP — Not installed</title></head>'
        . '<body style="font-family:Segoe UI,Arial,sans-serif;background:#f5f6f8;color:#1f2937;padding:40px">'
        . '<div style="max-width:560px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:28px">'
        . '<h2 style="margin-top:0">TradeERP is not installed yet</h2>'
        . '<p>Please run the installation wizard first.</p>'
        . '<p><a href="/install.php" style="display:inline-block;background:#0d6efd;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">Start Installation</a></p>'
        . '</div></body></html>';
    exit;
}

require dirname(__DIR__) . '/app/Core/Bootstrap.php';

use app\Core\Request;
use app\Core\Response;
use app\Core\Router;

$request  = Request::instance();
$response = new Response();
$router   = new Router($request, $response);

require APP_ROOT . '/routes/web.php';

$response = $router->dispatch();

// Global security headers
$response->header('X-Content-Type-Options', 'nosniff');
$response->header('X-Frame-Options', 'SAMEORIGIN');
$response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
$response->header('X-Permitted-Cross-Domain-Policies', 'none');
// Content-Security-Policy: same-origin assets; inline scripts/styles allowed
// (the app embeds small inline handlers — no external third-party resources).
$response->header(
    'Content-Security-Policy',
    "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'"
);

$response->send();
