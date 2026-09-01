<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Core\Request;
use app\Core\Response;
use app\Services\AuditService;
use app\Services\PermissionService;

/**
 * Audit log viewer — readable history of who did what.
 */
final class AuditController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('audit', 'audit_log', 'view');

        $page = $this->request->page();
        $per = 25;
        $module = (string) $this->request->query('module', '');
        $action = (string) $this->request->query('action', '');
        $search = (string) $this->request->query('q', '');
        $docType = (string) $this->request->query('doc_type', '');

        $where = 'WHERE 1=1';
        $params = [];
        if ($module !== '') {
            $where .= ' AND a.module = ?';
            $params[] = $module;
        }
        if ($action !== '') {
            $where .= ' AND a.action = ?';
            $params[] = $action;
        }
        if ($docType !== '') {
            $where .= ' AND a.document_type = ?';
            $params[] = $docType;
        }
        if ($search !== '') {
            $where .= ' AND (a.description LIKE ? OR u.name LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total = (int) Database::value(
            "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id {$where}",
            $params
        );
        $offset = max(0, ($page - 1) * $per);
        $rows = Database::query(
            "SELECT a.*, u.name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             {$where}
             ORDER BY a.id DESC
             LIMIT {$per} OFFSET {$offset}",
            $params
        );

        $modules = Database::query('SELECT DISTINCT module FROM audit_logs ORDER BY module');
        $docTypes = Database::query(
            'SELECT DISTINCT document_type FROM audit_logs WHERE document_type IS NOT NULL AND document_type != \'\' ORDER BY document_type'
        );

        return $this->view('audit/index', [
            'title'    => 'Audit Trail',
            'rows'     => $rows,
            'total'    => $total,
            'pages'    => max(1, (int) ceil($total / $per)),
            'page'     => $page,
            'module'   => $module,
            'action'   => $action,
            'search'   => $search,
            'docType'  => $docType,
            'modules'  => $modules,
            'docTypes' => $docTypes,
        ])->render();
    }

}
