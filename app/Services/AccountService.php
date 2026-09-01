<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Account service — COA tree, ledger queries, deletion safety.
 * Reports in later phases derive from the account_ledger (never parallel
 * totals), so this service is the single gateway for account data.
 */
final class AccountService
{
    /** Accounts of a company as a flat, code-ordered list. */
    public static function all(int $companyId): array
    {
        return Database::query(
            'SELECT * FROM accounts WHERE company_id = ? AND deleted_at IS NULL ORDER BY code',
            [$companyId]
        );
    }

    /** Nested COA tree: each node has 'children'. */
    public static function tree(int $companyId): array
    {
        $rows = self::all($companyId);
        $byId = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $byId[(int) $row['id']] = $row;
        }
        $tree = [];
        foreach ($byId as $id => &$node) {
            $pid = (int) ($node['parent_id'] ?? 0);
            if ($pid && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);
        return $tree;
    }

    /** Count of accounts in the company's COA. */
    public static function count(int $companyId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM accounts WHERE company_id = ? AND deleted_at IS NULL',
            [$companyId]
        );
    }

    /** Leaf accounts only (postable). */
    public static function leafAccounts(int $companyId, ?string $accountType = null): array
    {
        $sql = 'SELECT * FROM accounts WHERE company_id = ? AND is_group = 0 AND is_active = 1 AND deleted_at IS NULL';
        $params = [$companyId];
        if ($accountType !== null) {
            $sql .= ' AND account_type = ?';
            $params[] = $accountType;
        }
        $sql .= ' ORDER BY code';
        return Database::query($sql, $params);
    }

    /** All account ids: the account itself plus every descendant. */
    public static function idSetIncludingDescendants(int $accountId): array
    {
        $ids = [(int) $accountId];
        $queue = [(int) $accountId];
        while ($queue !== []) {
            $parent = array_shift($queue);
            $children = Database::query(
                'SELECT id FROM accounts WHERE parent_id = ? AND deleted_at IS NULL',
                [$parent]
            );
            foreach ($children as $c) {
                $ids[] = (int) $c['id'];
                $queue[] = (int) $c['id'];
            }
        }
        return array_unique($ids);
    }

    /**
     * Paginated ledger for an account (group accounts roll up their
     * descendants). Columns: date, voucher no., description, debit, credit,
     * balance, source document.
     */
    public static function ledger(
        int $accountId,
        ?string $from = null,
        ?string $to = null,
        int $page = 1,
        int $perPage = 50,
    ): array {
        $ids = self::idSetIncludingDescendants($accountId);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $where = "WHERE al.account_id IN ({$placeholders})";
        $params = $ids;
        if ($from !== null && \app\Core\Validator::isValidDate($from)) {
            $where .= ' AND al.entry_date >= ?';
            $params[] = $from;
        }
        if ($to !== null && \app\Core\Validator::isValidDate($to)) {
            $where .= ' AND al.entry_date <= ?';
            $params[] = $to;
        }

        $total = (int) Database::value(
            "SELECT COUNT(*) FROM account_ledger al {$where}",
            $params
        );

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            "SELECT al.*, a.code AS account_code, a.name AS account_name, a.account_type
             FROM account_ledger al
             JOIN accounts a ON a.id = al.account_id
             {$where}
             ORDER BY al.entry_date ASC, al.id ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // Totals over the filtered range (debit/credit/opening/closing)
        $totals = Database::row(
            "SELECT COALESCE(SUM(al.debit), 0) AS debit_total,
                    COALESCE(SUM(al.credit), 0) AS credit_total
             FROM account_ledger al {$where}",
            $params
        );

        return [
            'account' => Database::row('SELECT * FROM accounts WHERE id = ?', [$accountId]),
            'rows'    => $rows,
            'total'   => $total,
            'pages'   => max(1, (int) ceil($total / $perPage)),
            'page'    => $page,
            'per'     => $perPage,
            'debit_total' => (float) ($totals['debit_total'] ?? 0),
            'credit_total' => (float) ($totals['credit_total'] ?? 0),
        ];
    }

    /** Net balance of an account (with descendants) as of now. */
    public static function balance(int $accountId): float
    {
        $ids = self::idSetIncludingDescendants($accountId);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $row = Database::row(
            "SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS net
             FROM account_ledger WHERE account_id IN ({$placeholders})",
            $ids
        );
        return (float) ($row['net'] ?? 0);
    }

    /** Trial-balance style debit/credit sums per account (used by reports). */
    public static function trialBalanceSums(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $where = 'WHERE al.company_id = ?';
        $params = [$companyId];
        if ($from !== null) {
            $where .= ' AND al.entry_date >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where .= ' AND al.entry_date <= ?';
            $params[] = $to;
        }
        return Database::query(
            "SELECT al.account_id, a.code, a.name, a.account_type,
                    COALESCE(SUM(al.debit),0) AS debit, COALESCE(SUM(al.credit),0) AS credit
             FROM account_ledger al
             JOIN accounts a ON a.id = al.account_id
             {$where}
             GROUP BY al.account_id, a.code, a.name, a.account_type
             ORDER BY a.code",
            $params
        );
    }

    /** Whether an account is safe to delete (not system, no children, no ledger). */
    public static function canDelete(int $accountId): array
    {
        $account = Database::row('SELECT * FROM accounts WHERE id = ? AND deleted_at IS NULL', [$accountId]);
        if (!$account) {
            return [false, 'Account not found.'];
        }
        if ((int) $account['is_system'] === 1) {
            return [false, 'This is a system account and cannot be deleted.'];
        }
        $children = (int) Database::value('SELECT COUNT(*) FROM accounts WHERE parent_id = ? AND deleted_at IS NULL', [$accountId]);
        if ($children > 0) {
            return [false, 'This account has child accounts. Move or delete them first.'];
        }
        $used = (int) Database::value(
            'SELECT COUNT(*) FROM journal_entry_lines WHERE account_id = ?',
            [$accountId]
        );
        if ($used > 0) {
            return [false, 'This account has transactions. Instead of deleting, deactivate it to keep history intact.'];
        }
        return [true, 'OK'];
    }
}
