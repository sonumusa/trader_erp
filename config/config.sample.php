<?php

/**
 * TradeERP — Sample Configuration
 * --------------------------------
 * Copy this file to config/config.php and fill in your values,
 * OR (recommended) run the web installer at /install.php which
 * generates config/config.php for you.
 *
 * NEVER commit real credentials. This file is ignored after install.
 */

return [

    'app' => [
        'name'      => 'TradeERP',
        'env'       => 'production',              // production | local
        'debug'     => false,                     // NEVER true in production
        'url'       => '',                        // e.g. https://example.com (empty = auto-detect)
        'timezone'  => 'Asia/Karachi',
        'locale'    => 'en',
        'currency'  => 'PKR',
        'version'   => '0.1.0',
    ],

    'database' => [
        'host'      => 'localhost',
        'port'      => 3306,
        'name'      => 'erp_database',
        'user'      => 'erp_user',
        'pass'      => '',
        'charset'   => 'utf8mb4',
    ],

    'session' => [
        'name'      => 'tradeerp_session',
        'lifetime'  => 7200,                      // absolute lifetime in seconds
        'inactive'  => 1800,                      // idle timeout in seconds
        'secure'    => false,                     // set true when served over HTTPS
        'httponly'  => true,
        'samesite'  => 'Lax',
    ],

    'security' => [
        'login_max_attempts'  => 5,               // failed attempts before lock
        'login_lock_minutes'  => 15,              // lock duration
        'password_min_length' => 8,
    ],

];
