<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Accounting defaults — automatic account resolution.
 *
 * The UI never forces normal users to pick GL accounts: Cash → Cash in Hand,
 * Sales → Sales, etc. Resolution falls back through:
 *   branch override → company default
 * (item-group / item overrides arrive with the item master in Phase 5).
 */
final class AccountDefaultService
{
    /** Catalogue: key → [label, allowed account type (null = any)]. */
    public const KEYS = [
        'cash'              => ['Cash Account',          'asset'],
        'receivable'        => ['Accounts Receivable',   'asset'],
        'inventory'         => ['Inventory Account',     'asset'],
        'payable'           => ['Accounts Payable',      'liability'],
        'tax_payable'       => ['Tax Payable',           'liability'],
        'tax_receivable'    => ['Tax Receivable',       'asset'],
        'opening_balance'   => ['Opening Balance',       'equity'],
        'sales'             => ['Sales Account',         'income'],
        'sales_return'      => ['Sales Return Account',  'income'],
        'discount_received' => ['Discount Received',     'income'],
        'cogs'              => ['COGS Account',          'expense'],
        'purchase'          => ['Purchase Account',      'expense'],
        'purchase_return'   => ['Purchase Return Account', 'expense'],
        'discount_allowed'  => ['Discount Allowed',      'expense'],
        'round_off'         => ['Round Off',             'expense'],
        'stock_adjustment'  => ['Stock Adjustment',      'expense'],
        'profit_loss'       => ['Profit & Loss',         'equity'],
        'bank'              => ['Bank Account',          'asset'],
    ];

    /** Resolve the account id for a default key (branch → company fallback). */
    public static function get(int $companyId, string $key, ?int $branchId = null): ?int
    {
        if ($branchId !== null) {
            $id = Database::value(
                'SELECT account_id FROM account_defaults WHERE company_id = ? AND branch_id = ? AND `key` = ? LIMIT 1',
                [$companyId, $branchId, $key]
            );
            if ($id !== null) {
                return (int) $id;
            }
        }
        $id = Database::value(
            'SELECT account_id FROM account_defaults WHERE company_id = ? AND branch_id IS NULL AND `key` = ? LIMIT 1',
            [$companyId, $key]
        );
        return $id !== null ? (int) $id : null;
    }

    /**
     * Item-aware account resolution — the fallback hierarchy (spec §14):
     *   item override → item group override → company default
     * $key ∈ inventory | cogs | sales | purchase | stock_adjustment (item has
     * no per-item stock_adjustment column, so that key falls back to company).
     */
    public static function forItem(int $companyId, int $itemId, string $key): ?int
    {
        $columnMap = [
            'inventory' => 'inventory_account_id',
            'cogs'      => 'cogs_account_id',
            'sales'     => 'sales_account_id',
            'purchase'  => 'purchase_account_id',
        ];

        if (isset($columnMap[$key])) {
            $col = $columnMap[$key];
            $item = Database::row(
                "SELECT i.{$col}, i.item_group_id FROM items i WHERE i.id = ? AND i.company_id = ? AND i.deleted_at IS NULL",
                [$itemId, $companyId]
            );
            if ($item && (int) $item[$col] > 0) {
                return (int) $item[$col];
            }
            if ($item && (int) ($item['item_group_id'] ?? 0) > 0) {
                $group = Database::row(
                    "SELECT {$col} FROM item_groups WHERE id = ? AND deleted_at IS NULL",
                    [(int) $item['item_group_id']]
                );
                if ($group && (int) $group[$col] > 0) {
                    return (int) $group[$col];
                }
            }
        }

        return self::get($companyId, $key);
    }

    /** Resolve and return the account row (or null). */
    public static function account(int $companyId, string $key, ?int $branchId = null): ?array
    {
        $id = self::get($companyId, $key, $branchId);
        if ($id === null) {
            return null;
        }
        return Database::row('SELECT * FROM accounts WHERE id = ?', [$id]);
    }

    public static function set(int $companyId, string $key, int $accountId, ?int $branchId = null): void
    {
        if (!isset(self::KEYS[$key])) {
            throw new \RuntimeException("Unknown accounting default key: {$key}");
        }
        $account = Database::row('SELECT id, company_id, is_group FROM accounts WHERE id = ?', [$accountId]);
        if (!$account || (int) $account['company_id'] !== $companyId) {
            throw new \RuntimeException('The selected account is not valid for this company.');
        }

        $now = date('Y-m-d H:i:s');
        $existing = Database::row(
            'SELECT id FROM account_defaults WHERE company_id = ? AND branch_id <=> ? AND `key` = ? LIMIT 1',
            [$companyId, $branchId, $key]
        );
        if ($existing) {
            Database::execute(
                'UPDATE account_defaults SET account_id = ?, updated_at = ? WHERE id = ?',
                [$accountId, $now, (int) $existing['id']]
            );
        } else {
            Database::execute(
                'INSERT INTO account_defaults (company_id, branch_id, `key`, account_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$companyId, $branchId, $key, $accountId, $now, $now]
            );
        }
    }

    /** All defaults for a company (key → row), for the settings screen. */
    public static function all(int $companyId): array
    {
        $rows = Database::query(
            'SELECT ad.*, a.code AS account_code, a.name AS account_name
             FROM account_defaults ad
             JOIN accounts a ON a.id = ad.account_id
             WHERE ad.company_id = ? AND ad.branch_id IS NULL
             ORDER BY ad.id',
            [$companyId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['key']] = $row;
        }
        return $out;
    }

    public static function count(int $companyId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM account_defaults WHERE company_id = ? AND branch_id IS NULL',
            [$companyId]
        );
    }
}
