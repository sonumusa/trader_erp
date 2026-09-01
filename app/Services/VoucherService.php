<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Voucher service.
 *
 * Types (spec §16) with deterministic automatic accounting:
 *
 *   cash_payment   (CPV)  Dr counter / Cr Cash
 *   bank_payment   (BPV)  Dr counter / Cr Bank (mode account)
 *   cash_receipt   (CRV)  Dr Cash / Cr counter
 *   bank_receipt   (BRV)  Dr Bank / Cr counter
 *   journal        (JV)   free balanced multi-line
 *   contra         (CV)   Cash ↔ Bank transfer
 *
 * The non-cash side ("counter") resolves automatically:
 *   party = supplier → Accounts Payable
 *   party = customer → Accounts Receivable
 *   no party         → the user-selected account (required), with sensible
 *                      defaults offered in the UI (expense for payments,
 *                      income for receipts).
 *
 * Every voucher posts a balanced entry through AccountingEngine and can be
 * cancelled (mirror reversal, original kept).
 */
final class VoucherService
{
    public const TYPES = [
        'cash_payment'  => ['label' => 'Cash Payment',   'series' => 'cash_payment',  'prefix' => 'CPV'],
        'bank_payment'  => ['label' => 'Bank Payment',   'series' => 'bank_payment',  'prefix' => 'BPV'],
        'cash_receipt'  => ['label' => 'Cash Receipt',   'series' => 'cash_receipt',  'prefix' => 'CRV'],
        'bank_receipt'  => ['label' => 'Bank Receipt',   'series' => 'bank_receipt',  'prefix' => 'BRV'],
        'journal'       => ['label' => 'Journal Voucher','series' => 'journal_entry', 'prefix' => 'JV'],
        'contra'        => ['label' => 'Contra Voucher', 'series' => 'contra_voucher','prefix' => 'CV'],
    ];

    public static function create(int $companyId, array $data): int
    {
        $type = (string) ($data['voucher_type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            throw new \RuntimeException('Select a valid voucher type.');
        }

        $entryDate = (string) ($data['voucher_date'] ?? '');
        if (!\app\Core\Validator::isValidDate($entryDate)) {
            throw new \RuntimeException('Invalid voucher date.');
        }

        $lines = match ($type) {
            'cash_payment', 'bank_payment' => self::paymentLines($companyId, $data),
            'cash_receipt', 'bank_receipt' => self::receiptLines($companyId, $data),
            'journal'                      => self::journalLines($companyId, $data),
            'contra'                       => self::contraLines($companyId, $data),
        };

        $amount = round((float) ($data['amount'] ?? 0), 2);
        $voucherNo = DocumentNumberService::next(self::TYPES[$type]['series'], $companyId, $data['branch_id'] ?? null);
        ClosingPeriodService::guardDate($entryDate, 'create voucher', 'accounting');
        $userId = AuthService::id();
        $now = date('Y-m-d H:i:s');

        return (int) Database::transaction(function () use ($companyId, $data, $type, $lines, $amount, $voucherNo, $entryDate, $userId, $now) {
            Database::execute(
                'INSERT INTO vouchers
                    (company_id, branch_id, voucher_type, voucher_no, voucher_date, party_type, party_id,
                     payment_mode_id, amount, narration, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'posted\', ?, ?, ?)',
                [
                    $companyId,
                    $data['branch_id'] ?? null,
                    $type,
                    $voucherNo,
                    $entryDate,
                    $data['party_type'] ?? null,
                    !empty($data['party_id']) ? (int) $data['party_id'] : null,
                    !empty($data['payment_mode_id']) ? (int) $data['payment_mode_id'] : null,
                    $amount,
                    mb_substr((string) ($data['narration'] ?? ''), 0, 500),
                    $userId,
                    $now,
                    $now,
                ]
            );
            $voucherId = (int) Database::lastInsertId();

            $stmt = Database::pdo()->prepare(
                'INSERT INTO voucher_lines (voucher_id, account_id, debit, credit, party_type, party_id, narration)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($lines as $line) {
                $stmt->execute([
                    $voucherId,
                    (int) $line['account_id'],
                    $line['debit'],
                    $line['credit'],
                    $line['party_type'] ?? null,
                    $line['party_id'] ?? null,
                    mb_substr((string) ($line['narration'] ?? ''), 0, 255),
                ]);
            }

            AccountingEngine::post([
                'company_id' => $companyId,
                'branch_id'  => $data['branch_id'] ?? null,
                'entry_date' => $entryDate,
                'voucher_type' => $type,
                'entry_no'   => $voucherNo,
                'source_document_type' => 'voucher',
                'source_document_id'   => $voucherId,
                'narration'  => self::TYPES[$type]['label'] . ' ' . $voucherNo . ($data['narration'] ? ' — ' . $data['narration'] : ''),
                'lines'      => $lines,
            ]);

            return $voucherId;
        });
    }

    public static function cancel(int $voucherId, string $reason): void
    {
        $voucher = Database::row('SELECT * FROM vouchers WHERE id = ?', [$voucherId]);
        if (!$voucher || $voucher['status'] === 'cancelled') {
            throw new \RuntimeException('Voucher not found or already cancelled.');
        }

        ClosingPeriodService::guardDate((string) $voucher['voucher_date'], 'cancel voucher', 'accounting');
        Database::transaction(function () use ($voucher, $voucherId, $reason) {
            $userId = AuthService::id();
            $entries = Database::query(
                'SELECT id FROM journal_entries WHERE source_document_type = \'voucher\' AND source_document_id = ? AND status = \'posted\' ORDER BY id',
                [$voucherId]
            );
            foreach ($entries as $entry) {
                AccountingEngine::reverse((int) $entry['id'], 'Cancel ' . $voucher['voucher_no'] . ': ' . $reason, $userId);
            }
            Database::execute(
                'UPDATE vouchers SET status = \'cancelled\', cancelled_by = ?, cancelled_at = ?, cancelled_reason = ?, updated_at = ? WHERE id = ?',
                [$userId, date('Y-m-d H:i:s'), mb_substr($reason, 0, 255), date('Y-m-d H:i:s'), $voucherId]
            );
        });
    }

    /* ------------------------------------------------------------------ */

    private static function paymentLines(int $companyId, array $data): array
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Enter an amount greater than zero.');
        }

        $cashAccountId = self::cashBankAccount($companyId, $data, $data['voucher_type'] === 'bank_payment');
        $counter = self::counterAccount($companyId, $data, 'payment');

        return [
            ['account_id' => $counter['account_id'], 'debit' => $amount, 'credit' => 0, 'party_type' => $counter['party_type'] ?? null, 'party_id' => $counter['party_id'] ?? null, 'narration' => $data['narration'] ?? ''],
            ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => $amount, 'narration' => $data['narration'] ?? ''],
        ];
    }

    private static function receiptLines(int $companyId, array $data): array
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Enter an amount greater than zero.');
        }

        $cashAccountId = self::cashBankAccount($companyId, $data, $data['voucher_type'] === 'bank_receipt');
        $counter = self::counterAccount($companyId, $data, 'receipt');

        return [
            ['account_id' => $cashAccountId, 'debit' => $amount, 'credit' => 0, 'narration' => $data['narration'] ?? ''],
            ['account_id' => $counter['account_id'], 'debit' => 0, 'credit' => $amount, 'party_type' => $counter['party_type'] ?? null, 'party_id' => $counter['party_id'] ?? null, 'narration' => $data['narration'] ?? ''],
        ];
    }

    private static function journalLines(int $companyId, array $data): array
    {
        $rawLines = $data['lines'] ?? [];
        if (!is_array($rawLines) || count($rawLines) < 2) {
            throw new \RuntimeException('A journal voucher needs at least two lines (debit and credit).');
        }

        $lines = [];
        foreach ($rawLines as $raw) {
            $accountId = (int) ($raw['account_id'] ?? 0);
            $debit = round((float) ($raw['debit'] ?? 0), 2);
            $credit = round((float) ($raw['credit'] ?? 0), 2);
            if ($accountId <= 0) {
                throw new \RuntimeException('Each journal line needs an account.');
            }
            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                throw new \RuntimeException('Each journal line must have exactly one side (debit or credit).');
            }
            $lines[] = [
                'account_id' => $accountId,
                'debit'      => $debit,
                'credit'     => $credit,
                'party_type' => in_array($raw['party_type'] ?? null, ['customer', 'supplier'], true) ? $raw['party_type'] : null,
                'party_id'   => !empty($raw['party_id']) ? (int) $raw['party_id'] : null,
                'narration'  => $raw['narration'] ?? '',
            ];
        }

        return $lines;
    }

    private static function contraLines(int $companyId, array $data): array
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Enter an amount greater than zero.');
        }
        $fromAccountId = (int) ($data['from_account_id'] ?? 0);
        $toAccountId = (int) ($data['to_account_id'] ?? 0);
        if ($fromAccountId <= 0 || $toAccountId <= 0 || $fromAccountId === $toAccountId) {
            throw new \RuntimeException('Select two different accounts for the contra transfer.');
        }
        return [
            ['account_id' => $fromAccountId, 'debit' => 0, 'credit' => $amount, 'narration' => 'Contra transfer'],
            ['account_id' => $toAccountId, 'debit' => $amount, 'credit' => 0, 'narration' => 'Contra transfer'],
        ];
    }

    /** Resolve the cash/bank account for cash & bank voucher types. */
    private static function cashBankAccount(int $companyId, array $data, bool $bank): int
    {
        // A bank payment mode (or bank account) on bank vouchers
        $modeId = (int) ($data['payment_mode_id'] ?? 0);
        if ($bank && $modeId > 0) {
            $mode = PaymentModeService::get($modeId);
            if ($mode && (int) $mode['is_bank'] === 1 && (int) ($mode['account_id'] ?? 0) > 0) {
                return (int) $mode['account_id'];
            }
            throw new \RuntimeException('The selected payment mode is not a bank mode with a linked account.');
        }
        // Explicit account selection (contra-style) or default
        if (!empty($data['cash_account_id'])) {
            return (int) $data['cash_account_id'];
        }

        $accountId = (int) AccountDefaultService::get($companyId, $bank ? 'bank' : 'cash');
        $account = $accountId > 0
            ? Database::row('SELECT id, is_group FROM accounts WHERE id = ?', [$accountId])
            : null;
        if (!$account) {
            throw new \RuntimeException('No ' . ($bank ? 'bank' : 'cash') . ' account is configured.');
        }
        if ((int) $account['is_group'] === 1) {
            throw new \RuntimeException(
                'The configured ' . ($bank ? 'bank' : 'cash') . ' default is a group account. '
                . 'Link a leaf bank account to a payment mode (Settings → Payment Modes) or select an account for this voucher.'
            );
        }
        return $accountId;
    }

    /**
     * Resolve the non-cash side of a payment/receipt voucher.
     * party = supplier → AP; party = customer → AR; else user-picked account.
     */
    private static function counterAccount(int $companyId, array $data, string $side): array
    {
        $partyType = $data['party_type'] ?? null;
        $partyId = (int) ($data['party_id'] ?? 0);

        if ($partyType === 'supplier' && $partyId > 0) {
            $apId = (int) AccountDefaultService::get($companyId, 'payable');
            if ($apId <= 0) {
                throw new \RuntimeException('No Accounts Payable account is configured.');
            }
            return ['account_id' => $apId, 'party_type' => 'supplier', 'party_id' => $partyId];
        }
        if ($partyType === 'customer' && $partyId > 0) {
            $arId = (int) AccountDefaultService::get($companyId, 'receivable');
            if ($arId <= 0) {
                throw new \RuntimeException('No Accounts Receivable account is configured.');
            }
            return ['account_id' => $arId, 'party_type' => 'customer', 'party_id' => $partyId];
        }

        $counterId = (int) ($data['counter_account_id'] ?? 0);
        if ($counterId <= 0) {
            throw new \RuntimeException('Select an account for the ' . $side . ' side.');
        }
        $account = Database::row('SELECT id, is_group, company_id, is_active FROM accounts WHERE id = ? AND deleted_at IS NULL', [$counterId]);
        if (!$account || (int) $account['company_id'] !== $companyId || (int) $account['is_group'] === 1 || (int) $account['is_active'] !== 1) {
            throw new \RuntimeException('The selected account is not valid for this voucher.');
        }
        return ['account_id' => $counterId];
    }

    /* ------------------------------------------------------------------ */

    public static function paginate(int $companyId, string $type = '', int $page = 1, int $perPage = 20, ?string $from = null, ?string $to = null): array
    {
        $where = 'WHERE v.company_id = ?';
        $params = [$companyId];
        if ($type !== '' && isset(self::TYPES[$type])) {
            $where .= ' AND v.voucher_type = ?';
            $params[] = $type;
        }
        if ($from !== null) {
            $where .= ' AND v.voucher_date >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where .= ' AND v.voucher_date <= ?';
            $params[] = $to;
        }

        $total = (int) Database::value("SELECT COUNT(*) FROM vouchers v {$where}", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            "SELECT v.*, pm.name AS payment_mode_name,
                    CASE v.party_type WHEN 'customer' THEN c.name WHEN 'supplier' THEN s.name ELSE NULL END AS party_name
             FROM vouchers v
             LEFT JOIN payment_modes pm ON pm.id = v.payment_mode_id
             LEFT JOIN customers c ON c.id = v.party_id AND v.party_type = 'customer'
             LEFT JOIN suppliers s ON s.id = v.party_id AND v.party_type = 'supplier'
             {$where}
             ORDER BY v.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }

    public static function find(int $id): ?array
    {
        $voucher = Database::row('SELECT * FROM vouchers WHERE id = ?', [$id]);
        if (!$voucher) {
            return null;
        }
        $voucher['lines'] = Database::query(
            'SELECT vl.*, a.code AS account_code, a.name AS account_name
             FROM voucher_lines vl
             JOIN accounts a ON a.id = vl.account_id
             WHERE vl.voucher_id = ?
             ORDER BY vl.id',
            [$id]
        );
        $voucher['payment_mode'] = $voucher['payment_mode_id']
            ? Database::row('SELECT * FROM payment_modes WHERE id = ?', [(int) $voucher['payment_mode_id']])
            : null;
        if ($voucher['party_type'] === 'customer') {
            $voucher['party'] = Database::row('SELECT id, name, code FROM customers WHERE id = ?', [(int) $voucher['party_id']]);
        } elseif ($voucher['party_type'] === 'supplier') {
            $voucher['party'] = Database::row('SELECT id, name, code FROM suppliers WHERE id = ?', [(int) $voucher['party_id']]);
        } else {
            $voucher['party'] = null;
        }
        return $voucher;
    }

    /** Cash/bank leaf accounts (for contra and account overrides). */
    public static function cashBankAccounts(int $companyId): array
    {
        return Database::query(
            'SELECT * FROM accounts
             WHERE company_id = ? AND deleted_at IS NULL AND is_group = 0 AND is_active = 1 AND (is_cash = 1 OR is_bank = 1)
             ORDER BY code',
            [$companyId]
        );
    }
}
