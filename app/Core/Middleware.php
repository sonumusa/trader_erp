<?php

declare(strict_types=1);

namespace app\Core;

/**
 * Middleware registry + base contract.
 * A middleware returns false to stop the request (after writing its
 * response) or true/null to continue down the chain.
 */
abstract class Middleware
{
    /** @var array<string,class-string<Middleware>> */
    private static array $registry = [
        'auth'   => \app\Middleware\AuthMiddleware::class,
        'guest'  => \app\Middleware\GuestMiddleware::class,
        'csrf'   => \app\Middleware\CsrfMiddleware::class,
        'noauth' => \app\Middleware\GuestMiddleware::class,
    ];

    public static function register(string $name, string $class): void
    {
        self::$registry[$name] = $class;
    }

    public static function resolve(string $name): Middleware
    {
        if (!isset(self::$registry[$name])) {
            throw new \RuntimeException("Middleware not registered: {$name}");
        }
        $class = self::$registry[$name];
        return new $class();
    }

    abstract public function handle(Request $request, Response $response): bool;
}
