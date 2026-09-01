<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Request;
use app\Core\Response;
use app\Services\CompanyContextService;
use app\Services\PermissionService;
use app\Services\ReportService;

/**
 * Reports — the reusable reporting engine with filters, sorting and
 * Excel/CSV/print export. Every figure derives from the ledgers/documents.
 */
final class ReportController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('reports', 'report', 'view');

        return $this->view('reports/index', [
            'title'     => 'Reports',
            'catalogue' => ReportService::catalogue(),
        ])->render();
    }

    /** Run a report and render it (HTML). */
    public function show(string $report): mixed
    {
        $this->requirePermission('reports', 'report', 'view');

        $method = $this->resolveMethod($report);
        if ($method === null) {
            flash('error', 'Unknown report.');
            return $this->response->redirect($this->request->url('/reports'));
        }

        $companyId = CompanyContextService::currentCompanyId();
        $data = ReportService::$method($companyId, $this->filters());

        return $this->view('reports/show', [
            'title' => $data['title'],
            'data'  => $data,
            'report' => $report,
            'filters' => $this->filters(),
            'canExport' => PermissionService::can('reports', 'report', 'export'),
        ])->render();
    }

    /** Export a report to Excel (.xls HTML-table format — no library needed). */
    public function excel(string $report): mixed
    {
        $this->requirePermission('reports', 'report', 'export');

        $method = $this->resolveMethod($report);
        if ($method === null) {
            flash('error', 'Unknown report.');
            return $this->response->redirect($this->request->url('/reports'));
        }

        $companyId = CompanyContextService::currentCompanyId();
        $data = ReportService::$method($companyId, $this->filters());

        $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8">'
            . '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>'
            . '<x:Name>' . htmlspecialchars($data['title'], ENT_QUOTES) . '</x:Name>'
            . '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>'
            . '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>'
            . '<table border="1"><thead><tr>';
        foreach ($data['columns'] as $label) {
            $html .= '<th>' . htmlspecialchars($label, ENT_QUOTES) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($this->tableRows($data) as $row) {
            $html .= '<tr>';
            foreach (array_keys($data['columns']) as $col) {
                $v = $row[$col] ?? '';
                $html .= '<td>' . (is_numeric($v) ? $v : htmlspecialchars((string) $v, ENT_QUOTES)) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        $filename = $this->safeName($data['title']) . '_' . date('Ymd_His') . '.xls';
        return $this->response
            ->header('Content-Type', 'application/vnd.ms-excel; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->html($html);
    }

    /** Export a report to CSV (respects filters + sort). */
    public function csv(string $report): mixed
    {
        $this->requirePermission('reports', 'report', 'export');

        $method = $this->resolveMethod($report);
        if ($method === null) {
            flash('error', 'Unknown report.');
            return $this->response->redirect($this->request->url('/reports'));
        }

        $companyId = CompanyContextService::currentCompanyId();
        $data = ReportService::$method($companyId, $this->filters());

        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_values($data['columns']), ',', '"', '\\');
        foreach ($this->tableRows($data) as $row) {
            $line = [];
            foreach (array_keys($data['columns']) as $col) {
                $line[] = $row[$col] ?? '';
            }
            fputcsv($out, $line, ',', '"', '\\');
        }
        rewind($out);
        $body = stream_get_contents($out);
        fclose($out);

        $filename = $this->safeName($data['title']) . '_' . date('Ymd_His') . '.csv';
        return $this->response
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->html($body);
    }

    /** Printable view of a report. */
    public function print(string $report): mixed
    {
        $this->requirePermission('reports', 'report', 'view');

        $method = $this->resolveMethod($report);
        if ($method === null) {
            flash('error', 'Unknown report.');
            return $this->response->redirect($this->request->url('/reports'));
        }

        $companyId = CompanyContextService::currentCompanyId();
        $data = ReportService::$method($companyId, $this->filters());

        return $this->view('reports/print', [
            'title'   => $data['title'],
            'data'    => $data,
            'filters' => $this->filters(),
            'company' => CompanyContextService::currentCompany(),
        ])->layout('print')->render();
    }

    /* ------------------------------------------------------------------ */

    private function filters(): array
    {
        return [
            'from' => (string) $this->request->query('from', ''),
            'to'   => (string) $this->request->query('to', ''),
            'sort' => (string) $this->request->query('sort', ''),
            'dir'  => (string) $this->request->query('dir', ''),
        ];
    }

    private function resolveMethod(string $report): ?string
    {
        foreach (ReportService::catalogue() as $group) {
            if (isset($group[$report])) {
                return $group[$report][1];
            }
        }
        return null;
    }

    /** Flatten report data into uniform rows for export. */
    private function tableRows(array $data): array
    {
        if (!empty($data['rows'])) {
            return $data['rows'];
        }
        // Section-based reports (P&L, balance sheet, cash/bank handled above)
        $rows = [];
        foreach (['income', 'expenses', 'assets', 'liabilities', 'equity'] as $section) {
            foreach ($data[$section] ?? [] as $row) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function safeName(string $title): string
    {
        return preg_replace('/[^A-Za-z0-9_ -]/', '', $title) ?: 'report';
    }
}
