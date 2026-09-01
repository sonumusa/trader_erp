<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Services\AuditService;
use app\Services\ClosingPeriodService;
use app\Services\CompanyContextService;
use app\Services\PermissionService;

/**
 * Closing period management — set / clear the "Closed To" date.
 * Setting requires settings.closing.set; normal users are then locked out of
 * closed dates; settings.closing.override allows authorized bypass (audited).
 */
final class ClosingController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('settings', 'closing', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        return $this->view('settings/closing', [
            'title'     => 'Closing Period',
            'closedTo'  => ClosingPeriodService::closedTo($companyId),
            'canSet'    => PermissionService::can('settings', 'closing', 'set'),
            'canOverride' => PermissionService::can('settings', 'closing', 'override'),
        ])->render();
    }

    public function update(): mixed
    {
        $this->requirePermission('settings', 'closing', 'set');
        $companyId = CompanyContextService::currentCompanyId();

        $closedTo = (string) $this->request->input('closed_to', '');
        $action = 'set';

        if ($closedTo === '') {
            // Clear the closing period
            ClosingPeriodService::set(null, $companyId);
            AuditService::log('closing_clear', 'settings', 'closing', null, 'Cleared the closing period');
            flash('success', 'Closing period cleared. All dates are now open.');
            return $this->response->redirect($this->request->url('/settings/closing'));
        }

        if (!Validator::isValidDate($closedTo)) {
            flash('error', 'Enter a valid date.');
            return $this->response->back();
        }

        ClosingPeriodService::set($closedTo, $companyId);
        AuditService::log('closing_set', 'settings', 'closing', null, "Closed the period to {$closedTo}");
        flash('success', 'Closing period set to ' . format_date($closedTo) . '. Transactions on or before this date are locked for normal users.');
        return $this->response->redirect($this->request->url('/settings/closing'));
    }
}
