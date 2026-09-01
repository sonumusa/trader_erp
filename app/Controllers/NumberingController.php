<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Services\AuditService;
use app\Services\CompanyContextService;
use app\Services\DocumentNumberService;
use app\Services\PermissionService;

/**
 * Document numbering configuration — series list, edit, and the
 * permissioned, audited serial-reset operation.
 */
final class NumberingController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public static function previewNumber(array $series): string
    {
        $parts = [];
        if ((int) $series['include_branch_code'] === 1) {
            $parts[] = $series['branch_code'] ?: 'BR';
        }
        $parts[] = $series['prefix'];
        if ((int) $series['include_financial_year'] === 1) {
            $parts[] = '26';
        }
        $length = max(1, (int) $series['number_length']);
        $parts[] = str_pad((string) (int) $series['next_number'], $length, '0', STR_PAD_LEFT);
        return implode('-', $parts);
    }

    public function index(): string
    {
        $this->requirePermission('settings', 'numbering', 'view');

        $company = CompanyContextService::currentCompany();
        $series = DocumentNumberService::all();

        return $this->view('settings/numbering', [
            'title'   => 'Document Numbering',
            'company' => $company,
            'series'  => $series,
        ])->render();
    }

    public function editForm(int|string $id): string
    {
        $this->requirePermission('settings', 'numbering', 'edit');
        $series = DocumentNumberService::series((int) $id);
        if (!$series) {
            flash('error', 'Numbering series not found.');
            return (string) $this->response->redirect($this->request->url('/settings/numbering'))->body();
        }
        return $this->view('settings/numbering_form', [
            'title'  => 'Edit Numbering',
            'series' => $series,
        ])->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('settings', 'numbering', 'edit');
        $id = (int) $id;

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'prefix'         => 'required|max:30',
            'starting_number' => 'integer|min:1',
            'next_number'    => 'integer|min:1',
            'number_length'  => 'integer|min:1|max:12',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        try {
            $updated = DocumentNumberService::update($id, [
                'prefix'              => $d['prefix'],
                'include_branch_code' => ($d['include_branch_code'] ?? '0') === '1',
                'include_financial_year' => ($d['include_financial_year'] ?? '1') === '1',
                'starting_number'     => (int) $d['starting_number'],
                'next_number'         => (int) $d['next_number'],
                'number_length'       => (int) $d['number_length'],
                'is_active'           => ($d['is_active'] ?? '1') === '1',
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('update', 'settings', 'numbering', $id, "Updated numbering series {$updated['document_type']} (prefix {$updated['prefix']})");
        flash('success', 'Numbering series updated.');
        return $this->response->redirect($this->request->url('/settings/numbering'));
    }

    /** POST /settings/numbering/{id}/reset — authorized + audited serial reset. */
    public function reset(int|string $id): mixed
    {
        $this->requirePermission('settings', 'numbering', 'reset');
        $id = (int) $id;

        $series = DocumentNumberService::series($id);
        if (!$series) {
            flash('error', 'Numbering series not found.');
            return $this->response->back();
        }

        $newNext = (int) $this->request->input('new_next_number');
        if ($newNext < 1) {
            flash('error', 'Enter a valid next number.');
            return $this->response->back();
        }

        try {
            $updated = DocumentNumberService::reset($id, $newNext);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('reset', 'settings', 'numbering', $id, sprintf(
            'Reset numbering for %s: next number set to %d (last used was %d)',
            $series['document_type'],
            $newNext,
            (int) $series['last_used_number']
        ));
        flash('success', 'Numbering reset. The next ' . str_replace('_', ' ', $series['document_type']) . ' will be numbered from ' . $newNext . '.');
        return $this->response->redirect($this->request->url('/settings/numbering'));
    }
}
