<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Services\AuditService;
use app\Services\CompanyContextService;
use app\Services\PermissionService;
use app\Services\SettingsService;

/**
 * Global settings — application-level configuration.
 */
final class SettingsController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('settings', 'setting', 'view');

        return $this->view('settings/index', [
            'title'   => 'Settings',
            'groups'  => SettingsService::grouped(),
            'company' => CompanyContextService::currentCompany(),
        ])->render();
    }

    public function update(): mixed
    {
        $this->requirePermission('settings', 'setting', 'edit');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'company_name'    => 'required|max:150',
            'company_code'    => 'max:20',
            'currency'        => 'required|max:10',
            'timezone'        => 'required|max:60',
            'allow_negative_stock' => 'in:0,1',
        ])) {
            flash('error', $v->firstError() ?? 'Validation failed.');
            return $this->response->back();
        }

        $d = $v->data();
        $settings = [
            'company_name'         => $d['company_name'],
            'company_code'         => $d['company_code'] ?? '',
            'currency'             => $d['currency'],
            'timezone'             => $d['timezone'],
            'allow_negative_stock' => ($d['allow_negative_stock'] ?? '0') === '1' ? '1' : '0',
            // NOTE: inventory_cost_method intentionally NOT here — changing it
            // requires a controlled revaluation (POST /inventory/costing/update).
        ];

        foreach ($settings as $key => $value) {
            SettingsService::set($key, $value);
        }

        // Keep the seeded company record in sync for Phase-1
        $company = CompanyContextService::currentCompany();
        if ($company) {
            \app\Core\Database::execute(
                'UPDATE companies SET name = ?, updated_at = ? WHERE id = ?',
                [$d['company_name'], date('Y-m-d H:i:s'), $company['id']]
            );
        }

        AuditService::log('update', 'settings', 'setting', null, 'Updated application settings');
        flash('success', 'Settings saved successfully.');
        return $this->response->redirect($this->request->url('/settings'));
    }

}
