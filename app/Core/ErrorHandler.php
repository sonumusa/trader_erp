<?php

declare(strict_types=1);

namespace app\Core;

/**
 * Central error handling.
 * Users never see raw PHP errors — a friendly, generic message is shown
 * and technical details go to storage/logs/error.log (debug mode can
 * surface the details locally).
 */
final class ErrorHandler
{
    public static function exception(\Throwable $e): void
    {
        self::log($e);
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, '[ERROR] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            exit(1);
        }
        self::render($e);
    }

    /** @param int $errno */
    public static function error(int $errno, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        $e = new \ErrorException($message, 0, $errno, $file, $line);
        self::log($e);
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, '[WARN] ' . $message . ' in ' . $file . ':' . $line . "\n");
            return true;
        }
        // In production, warnings for non-fatal issues: log only.
        if (!config('app.debug')) {
            return true;
        }
        return false;
    }

    /** Fatal boot-time failure: database down, config missing etc. */
    public static function fatal(string $userMessage, ?\Throwable $e = null): never
    {
        if ($e !== null) {
            self::log($e);
        } else {
            error_log('[TradeERP] ' . $userMessage);
        }
        http_response_code(500);
        $debug = config('app.debug');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>System error</title></head>'
            . '<body style="font-family:Segoe UI,Arial,sans-serif;background:#f5f6f8;color:#1f2937;padding:40px">'
            . '<div style="max-width:640px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:28px">'
            . '<h2 style="margin-top:0">TradeERP could not start</h2>'
            . '<p>' . e($userMessage) . '</p>';
        if ($debug && $e !== null) {
            echo '<pre style="background:#f9fafb;padding:12px;overflow:auto;font-size:12px">'
                . e($e->getMessage()) . "\n" . e($e->getTraceAsString()) . '</pre>';
        }
        echo '<p><small>If this persists, contact your administrator.</small></p></div></body></html>';
        exit(1);
    }

    private static function log(\Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        @error_log($line, 3, APP_ROOT . '/storage/logs/error.log');
    }

    private static function render(\Throwable $e): void
    {
        http_response_code(500);
        $debug = config('app.debug');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Something went wrong</title></head>'
            . '<body style="font-family:Segoe UI,Arial,sans-serif;background:#f5f6f8;color:#1f2937;padding:40px">'
            . '<div style="max-width:640px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:28px">'
            . '<h2 style="margin-top:0">Something went wrong</h2>'
            . '<p>We could not complete your request. No changes were made.</p>'
            . '<p>Please try again, or contact your administrator if the problem continues.</p>';
        if ($debug) {
            echo '<pre style="background:#f9fafb;padding:12px;overflow:auto;font-size:12px">'
                . e($e->getMessage()) . "\n" . e($e->getTraceAsString()) . '</pre>';
        }
        echo '<p><a href="' . e(Request::instance()->url('/')) . '">Back to home</a></p>'
            . '</div></body></html>';
        exit(1);
    }
}
