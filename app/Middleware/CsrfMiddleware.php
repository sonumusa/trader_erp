<?php

declare(strict_types=1);

namespace app\Middleware;

use app\Core\Csrf;
use app\Core\Middleware;
use app\Core\Request;
use app\Core\Response;

/**
 * Validates the CSRF token on every state-changing request.
 * Safe methods (GET/HEAD/OPTIONS) pass through.
 */
final class CsrfMiddleware extends Middleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Response $response): bool
    {
        if (in_array(strtoupper($request->method()), self::SAFE_METHODS, true)) {
            return true;
        }
        $token = $request->input('_token') ?? $request->header('X-CSRF-Token');
        if (!Csrf::validate(is_string($token) ? $token : null)) {
            if ($request->wantsJson() || $request->isAjax()) {
                $response->json([
                    'success' => false,
                    'message' => 'Your session has expired. Please refresh the page and try again.',
                ], 419);
            } else {
                $response->invalidToken();
            }
            return false;
        }
        return true;
    }
}
