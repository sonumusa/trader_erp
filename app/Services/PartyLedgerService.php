<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Customer / supplier sub-ledger service.
 *
 * Every receivable/payable journal line carries party_type + party_id, so a
 * party ledger is simply the account_ledger filtered by party. Outstanding
 * and aging derive from the SAME ledger — never parallel totals.
 */
final class PartyLedgerService
{
    /** The default AR / AP account id for a party type. */
    public static function partyAccountId(int $companyId, string $partyType): ?int
    {
        return AccountDefaultService::get(
            $companyId,
            $partyType === 'customer' ? 'receivable' : 'payable'
        );
    }

    /**
     * Paginated party ledger with running balance, opening balance (before
     * the from-date) and closing balance.
     */
    public static function ledger(
        int $companyId,
        string $partyType,
        int $partyId,
        ?string $from = null,
        ?string $to = null,
        int $page = 1,
        int $perPage = 50,
    ): array {
        $where = 'WHERE party_type = ? AND party_id = ?';
        $params = [$partyType, $partyId];
        if ($from !== null && \app\Core\Validator::isValidDate($from)) {
            $where .= ' AND entry_date >= ?';
            $params[] = $from;
        }
        if ($to !== null && \app\Core\Validator::isValidDate($to)) {
            $where .= ' AND entry_date <= ?';
            $params[] = $to;
        }

        $total = (int) Database::value(
            "SELECT COUNT(*) FROM account_ledger {$where}",
            $params
        );

        // Opening balance = net of all entries strictly before $from
        // (sign-flipped for suppliers so a payable reads positive)
        $opening = 0.0;
        if ($from !== null) {
            $net = (float) Database::value(
                'SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0)
                 FROM account_ledger
                 WHERE party_type = ? AND party_id = ? AND entry_date < ?',
                [$partyType, $partyId, $from]
            );
            $opening = $partyType === 'supplier' ? -$net : $net;
        }

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            "SELECT * FROM account_ledger
             {$where}
             ORDER BY entry_date ASC, id ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $totals = Database::row(
            "SELECT COALESCE(SUM(debit),0) AS debit_total, COALESCE(SUM(credit),0) AS credit_total
             FROM account_ledger {$where}",
            $params
        );

        $closing = $opening + ((float) $totals['debit_total'] - (float) $totals['credit_total'])
            * ($partyType === 'supplier' ? -1 : 1);

        return [
            'party_type'    => $partyType,
            'party_id'      => $partyId,
            'rows'          => $rows,
            'total'         => $total,
            'pages'         => max(1, (int) ceil($total / $perPage)),
            'page'          => $page,
            'per'           => $perPage,
            'debit_total'   => (float) $totals['debit_total'],
            'credit_total'  => (float) $totals['credit_total'],
            'opening'       => $opening,
            'closing'       => $closing,
        ];
    }

    /**
     * Outstanding balance of one party.
     * Customer (AR, debit-normal): positive = they owe us.
     * Supplier (AP, credit-normal): positive = we owe them.
     */
    public static function outstanding(int $companyId, string $partyType, int $partyId): float
    {
        $net = (float) Database::value(
            'SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0)
             FROM account_ledger WHERE party_type = ? AND party_id = ?',
            [$partyType, $partyId]
        );
        return $partyType === 'supplier' ? -$net : $net;
    }

    /**
     * Outstanding per party across the company, for list screens:
     * [{party_id, outstanding}] ordered by outstanding desc.
     */
    public static function outstandingByParty(int $companyId, string $partyType): array
    {
        return Database::query(
            'SELECT party_id, COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS outstanding
             FROM account_ledger
             WHERE company_id = ? AND party_type = ?
             GROUP BY party_id
             HAVING outstanding != 0
             ORDER BY outstanding DESC',
            [$companyId, $partyType]
        );
    }

    /**
     * Aging of a party's outstanding balance (FIFO: oldest invoices are
     * settled first). Buckets: 0-30, 31-60, 61-90, 90+.
     *
     * @return array{total:float, buckets:array<string,float>}
     */
    public static function aging(int $companyId, string $partyType, int $partyId, ?string $asOf = null): array
    {
        $asOf = $asOf ?? date('Y-m-d');

        $rows = Database::query(
            'SELECT entry_date, debit, credit
             FROM account_ledger
             WHERE party_type = ? AND party_id = ? AND entry_date <= ?
             ORDER BY entry_date ASC, id ASC',
            [$partyType, $partyId, $asOf]
        );

        // FIFO allocation: track unpaid amounts with their dates.
        // Customer: debit increases the balance (invoice), credit decreases it.
        // Supplier: credit increases the balance (invoice), debit decreases it.
        $open = []; // [date => amount] stack (oldest first)
        $outstanding = 0.0;

        foreach ($rows as $row) {
            $increase = (float) ($partyType === 'supplier' ? $row['credit'] : $row['debit']);
            $decrease = (float) ($partyType === 'supplier' ? $row['debit'] : $row['credit']);

            if ($increase > 0) {
                $open[] = ['date' => $row['entry_date'], 'amount' => $increase];
                $outstanding += $increase;
            }
            if ($decrease > 0) {
                $remaining = $decrease;
                while ($remaining > 0 && $open !== []) {
                    $head = &$open[0];
                    if ($head['amount'] <= $remaining) {
                        $remaining -= $head['amount'];
                        $outstanding -= $head['amount'];
                        array_shift($open);
                    } else {
                        $head['amount'] -= $remaining;
                        $outstanding -= $remaining;
                        $remaining = 0;
                    }
                }
                unset($head);
            }
        }

        $buckets = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
        $daysDiff = static fn(string $d): int => (int) floor((strtotime($asOf) - strtotime($d)) / 86400);

        foreach ($open as $item) {
            $age = $daysDiff($item['date']);
            $key = match (true) {
                $age <= 30  => '0-30',
                $age <= 60  => '31-60',
                $age <= 90  => '61-90',
                default     => '90+',
            };
            $buckets[$key] = round($buckets[$key] + $item['amount'], 2);
        }

        return [
            'total'   => round($outstanding, 2),
            'buckets' => $buckets,
        ];
    }
}
