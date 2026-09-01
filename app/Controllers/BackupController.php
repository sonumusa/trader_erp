<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Request;
use app\Core\Response;
use app\Services\AuditService;
use app\Services\BackupService;
use app\Services\PermissionService;

/**
 * Database backups — create, list, download, delete (spec §53).
 * Permission: settings.backup.* (view/create/download/delete).
 * Files live in storage/backups (outside the webroot); downloads are
 * authenticated + permission-gated.
 */
final class BackupController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('settings', 'backup', 'view');

        return $this->view('settings/backups', [
            'title'   => 'Backups',
            'backups' => BackupService::list(),
            'canCreate' => PermissionService::can('settings', 'backup', 'create'),
            'canDownload' => PermissionService::can('settings', 'backup', 'download'),
            'canDelete' => PermissionService::can('settings', 'backup', 'delete'),
        ])->render();
    }

    public function create(): mixed
    {
        $this->requirePermission('settings', 'backup', 'create');

        try {
            $backup = BackupService::create();
        } catch (\Throwable $e) {
            flash('error', 'Backup could not be created. Check storage/backups permissions.');
            return $this->response->back();
        }

        AuditService::log('create', 'settings', 'backup', null, 'Created database backup: ' . $backup['name']);
        flash('success', 'Backup created: ' . $backup['name'] . ' (' . number_format($backup['size']) . ' bytes).');
        return $this->response->redirect($this->request->url('/settings/backups'));
    }

    public function download(string $name): mixed
    {
        $this->requirePermission('settings', 'backup', 'download');

        $backup = BackupService::find($name);
        if (!$backup) {
            flash('error', 'Backup not found.');
            return $this->response->redirect($this->request->url('/settings/backups'));
        }

        AuditService::log('download', 'settings', 'backup', null, 'Downloaded backup: ' . $name);
        return $this->response
            ->header('Content-Type', 'application/sql; charset=utf-8')
            ->download($backup['path'], $backup['name']);
    }

    public function delete(string $name): mixed
    {
        $this->requirePermission('settings', 'backup', 'delete');

        if (BackupService::delete($name)) {
            AuditService::log('delete', 'settings', 'backup', null, 'Deleted backup: ' . $name);
            flash('success', 'Backup deleted.');
        } else {
            flash('error', 'Backup not found.');
        }
        return $this->response->redirect($this->request->url('/settings/backups'));
    }
}
