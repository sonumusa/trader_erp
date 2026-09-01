<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Request;
use app\Core\Response;
use app\Services\AuditService;
use app\Services\FeatureService;
use app\Services\PermissionService;

/**
 * Feature management — enable/disable modules from the UI.
 * Disabled features are hidden from navigation and blocked server-side.
 */
final class FeatureController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('settings', 'feature', 'view');

        return $this->view('settings/features', [
            'title'    => 'Features',
            'features' => FeatureService::all(),
        ])->render();
    }

    public function update(): mixed
    {
        $this->requirePermission('settings', 'feature', 'edit');

        $submitted = $this->request->input('features', []);
        $submitted = is_array($submitted) ? $submitted : [];

        foreach (FeatureService::catalogue() as $key => $meta) {
            $enabled = isset($submitted[$key]) && (int) $submitted[$key] === 1;
            FeatureService::set($key, $enabled);
        }

        AuditService::log('update', 'settings', 'feature', null, 'Updated feature toggles');
        flash('success', 'Feature settings saved.');
        return $this->response->redirect($this->request->url('/settings/features'));
    }

}
