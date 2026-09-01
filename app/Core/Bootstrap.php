<?php

declare(strict_types=1);

/**
 * TradeERP — Application Bootstrap
 * Loads configuration, registers autoloading, starts the session,
 * sets error handling and establishes the database connection.
 */

if (!defined('APP_ROOT')) {
    // app/Core/Bootstrap.php → project root is two levels up
    define('APP_ROOT', dirname(__DIR__, 2));
}
if (!defined('APP_START')) {
    define('APP_START', microtime(true));
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '0.1.0');
}

/* ------------------------------------------------------------------ *
 *  Configuration
 * ------------------------------------------------------------------ */
if (file_exists(APP_ROOT . '/config/config.php')) {
    $config = require APP_ROOT . '/config/config.php';
} elseif (file_exists(APP_ROOT . '/config/config.sample.php')) {
    // Fallback so unit tests can boot without a configured database.
    $config = require APP_ROOT . '/config/config.sample.php';
} else {
    http_response_code(500);
    exit('Application is not configured. Please run the installer.');
}

date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Karachi');

/* ------------------------------------------------------------------ *
 *  Global helper functions
 * ------------------------------------------------------------------ */
require_once APP_ROOT . '/app/Core/Helper.php';
require_once APP_ROOT . '/app/Core/helpers.php';

/* ------------------------------------------------------------------ *
 *  Class autoloading
 *  Composer-style PSR-4 for app\ namespace.
 * ------------------------------------------------------------------ */
spl_autoload_register(static function (string $class): void {
    $prefix = 'app\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/* ------------------------------------------------------------------ *
 *  Error handling — never show raw errors to users
 * ------------------------------------------------------------------ */
error_reporting(E_ALL);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

if ($config['app']['debug']) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

set_exception_handler([\app\Core\ErrorHandler::class, 'exception']);
set_error_handler([\app\Core\ErrorHandler::class, 'error']);

/* ------------------------------------------------------------------ *
 *  Environment helpers
 * ------------------------------------------------------------------ */
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $cfg = null;
        if ($cfg === null) {
            $cfg = isset($GLOBALS['__erp_config'])
                ? $GLOBALS['__erp_config']
                : require APP_ROOT . '/config/config.php';
        }
        $parts = explode('.', $key);
        $value = $cfg;
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        return $value;
    }
}

$GLOBALS['__erp_config'] = $config;

/* ------------------------------------------------------------------ *
 *  Database
 * ------------------------------------------------------------------ */
try {
    \app\Core\Database::boot($config['database']);
} catch (\Throwable $e) {
    \app\Core\ErrorHandler::fatal(
        'Database connection could not be established. ' .
        'Please check your configuration or run the installer.',
        $e
    );
}

/* ------------------------------------------------------------------ *
 *  Session
 * ------------------------------------------------------------------ */
\app\Core\Session::boot($config['session'] ?? []);

/* ------------------------------------------------------------------ *
 *  Request / Response singletons
 * ------------------------------------------------------------------ */
\app\Core\Request::init();

return $config;
