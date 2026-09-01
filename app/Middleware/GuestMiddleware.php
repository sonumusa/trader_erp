<?php

declare(strict_types=1);

namespace app\Middleware;

use app\Core\Middleware;
use app\Core\Request;
use app\Core\Response;
use app\Core\Session;
use app\Services\AuthService;

/** Redirects already-authenticated users away from login/register pages. */
final class GuestMiddleware extends Middleware
{
    public function handle(Request $request, Response $response): bool
    {
        if (AuthService::check()) {
            $response->redirect($request->url('/'));
            return false;
        }
        return true;
    }
}
