<?php

declare(strict_types=1);

namespace app\Middleware;

use app\Core\Middleware;
use app\Core\Request;
use app\Core\Response;
use app\Core\Session;
use app\Services\AuthService;

/**
 * Requires an authenticated session.
 * Optionally enforces an active-company context.
 */
final class AuthMiddleware extends Middleware
{
    public function __construct(
        private readonly bool $requireCompany = false,
    ) {
    }

    public function handle(Request $request, Response $response): bool
    {
        if (!AuthService::check()) {
            Session::flash('error', 'Please sign in to continue.');
            $response->redirect($request->url('/login'));
            return false;
        }

        if ($this->requireCompany && !AuthService::companyId()) {
            Session::flash('warning', 'No company context is active. Please select a company.');
            $response->redirect($request->url('/settings/company/select'));
            return false;
        }

        return true;
    }
}
