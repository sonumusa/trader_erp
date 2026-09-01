<?php

declare(strict_types=1);

namespace app\Core;

use app\Services\FeatureService;
use app\Services\PermissionService;

/**
 * Base controller — shared request/response access, view helper and
 * server-side permission/feature guards.
 */
abstract class Controller
{
    public function __construct(
        protected readonly Request $request,
        protected readonly Response $response,
    ) {
    }

    protected function view(string $view, array $data = []): View
    {
        $data['auth'] = \app\Services\AuthService::user();
        $data['flash'] = Session::pullFlash();
        $data['currentUser'] = $data['auth'];
        $data['request'] = $this->request;
        return View::make($view, $data);
    }

    /** JSON success payload for AJAX endpoints. */
    protected function jsonOk(mixed $data = null, string $message = 'OK'): Response
    {
        return $this->response->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    protected function jsonError(string $message, int $status = 400): Response
    {
        return $this->response->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    /**
     * Server-side permission gate. The UI only hides actions; this is the
     * enforcement. Redirects with a flash message when denied.
     */
    protected function requirePermission(string $module, string $document, string $action): void
    {
        if (!PermissionService::can($module, $document, $action)) {
            flash('error', 'You do not have permission to perform this action.');
            $this->response->redirect($this->request->url('/'))->send();
            exit;
        }
    }

    /** Gate a page on a feature being enabled. */
    protected function requireFeature(string $feature): void
    {
        if (!FeatureService::isEnabled($feature)) {
            flash('error', 'This module is disabled. Enable it under Settings → Features.');
            $this->response->redirect($this->request->url('/'))->send();
            exit;
        }
    }
}
