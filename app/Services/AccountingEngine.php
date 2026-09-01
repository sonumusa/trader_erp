<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Central double-entry accounting engine.
 *
 * EVERY journal entry in the system goes through post() — modules never write
 * accounting rows themselves. Invariants enforced here:
 *   * Total Debit === Total Credit (an unbalanced entry is impossible)
 *   * lines reference active, non-group (leaf) accounts
 *   * every line is one-sided (debit XOR credit)
 *   * posting is atomic (database transaction; nested calls share the outer tx)
 *
 * The account_ledger is a materialized running-balance ledger rebuilt
 * deterministically per affected account after every post/reversal.
 */
final class AccountingEngine
{
    /** Rounding precision for balancing. */
    private const ROUND = 4;

    /* ------------------------------------------------------------------
     * Posting
     * ------------------------------------------------------------------ */

    /**
     * Post a balanced journal entry.
     *
     * $data:
     *   company_id int, branch_id int|null, entry_date 'Y-m-d',
     *   voucher_type string, entry_no string|null (null → next journal series),
     *   source_document_type string|null, source_document_id int|null,
     *   narration string,
     *   lines: list of [
     *     account_id int, debit float, credit float,
     *     party_type 'customer'|'supplier'|null, party_id int|null, narration string
     *   ]
     *
     * @return array{id:int, entry_no:string}
     */
    public static function post(array $data): array
    {
        $companyId = (int) $data['company_id'];
        if ($companyId <= 0) {
            throw new \RuntimeException('Accounting requires a company context.');
        }

        $lines = $data['lines'] ?? [];
        if (count($lines) < 2) {
            throw new \RuntimeException('A journal entry needs at least two lines (debit and credit).');
        }

        // --- Normalize & validate lines ---
        $normalized = [];
        foreach ($lines as $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $debit = round((float) ($line['debit'] ?? 0), self::ROUND);
            $credit = round((float) ($line['credit'] ?? 0), self::ROUND);

            if ($accountId <= 0) {
                throw new \RuntimeException('A journal line is missing its account.');
            }
            if ($debit < 0 || $credit < 0) {
                throw new \RuntimeException('Debit and credit amounts cannot be negative.');
            }
            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                throw new \RuntimeException('Each journal line must have exactly one side (debit or credit).');
            }

            $account = Database::row(
                'SELECT id, name, is_group, is_active, company_id FROM accounts WHERE id = ? AND deleted_at IS NULL LIMIT 1',
                [$accountId]
            );
            if (!$account) {
                throw new \RuntimeException('Journal line references an account that does not exist.');
            }
            if ((int) $account['company_id'] !== $companyId) {
                throw new \RuntimeException('Journal line references an account from another company.');
            }
            if ((int) $account['is_group'] === 1) {
                throw new \RuntimeException(
                    'Cannot post to a group account "' . $account['name'] . '". Post to a leaf account instead.'
                );
            }
            if ((int) $account['is_active'] !== 1) {
                throw new \RuntimeException('Cannot post to an inactive account.');
            }

            $normalized[] = [
                'account_id' => $accountId,
                'debit'      => $debit,
                'credit'     => $credit,
                'party_type' => in_array($line['party_type'] ?? null, ['customer', 'supplier'], true) ? $line['party_type'] : null,
                'party_id'   => !empty($line['party_id']) ? (int) $line['party_id'] : null,
                'narration'  => mb_substr((string) ($line['narration'] ?? ''), 0, 255),
            ];
        }

        // --- Balance check (the invariant) ---
        $totalDebit = array_sum(array_column($normalized, 'debit'));
        $totalCredit = array_sum(array_column($normalized, 'credit'));
        if (round($totalDebit, self::ROUND) !== round($totalCredit, self::ROUND)) {
            throw new \RuntimeException(
                sprintf(
                    'The journal entry does not balance (debit %.2f ≠ credit %.2f). No changes were made.',
                    $totalDebit,
                    $totalCredit
                )
            );
        }

        $entryDate = (string) $data['entry_date'];
        if (!\app\Core\Validator::isValidDate($entryDate)) {
            throw new \RuntimeException('Invalid entry date.');
        }

        // --- Entry number ---
        $entryNo = isset($data['entry_no']) && $data['entry_no'] !== ''
            ? (string) $data['entry_no']
            : DocumentNumberService::next('journal_entry', $companyId, $data['branch_id'] ?? null);

        return Database::transaction(function () use ($data, $normalized, $companyId, $entryDate, $entryNo) {
            $now = date('Y-m-d H:i:s');
            Database::execute(
                'INSERT INTO journal_entries
                    (company_id, branch_id, entry_no, entry_date, voucher_type,
                     source_document_type, source_document_id, narration,
                     status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'posted\', ?, ?, ?)',
                [
                    $companyId,
                    $data['branch_id'] ?? null,
                    $entryNo,
                    $entryDate,
                    $data['voucher_type'] ?? 'journal',
                    $data['source_document_type'] ?? null,
                    $data['source_document_id'] ?? null,
                    mb_substr((string) ($data['narration'] ?? ''), 0, 500),
                    AuthService::id(),
                    $now,
                    $now,
                ]
            );
            $entryId = (int) Database::lastInsertId();

            foreach ($normalized as $line) {
                Database::execute(
                    'INSERT INTO journal_entry_lines
                        (journal_entry_id, account_id, debit, credit, party_type, party_id, narration)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        $entryId,
                        $line['account_id'],
                        $line['debit'],
                        $line['credit'],
                        $line['party_type'],
                        $line['party_id'],
                        $line['narration'],
                    ]
                );
            }

            self::rebuildLedgers($companyId, array_unique(array_column($normalized, 'account_id')));

            return ['id' => $entryId, 'entry_no' => $entryNo];
        });
    }

    /* ------------------------------------------------------------------
     * Reversal / cancellation (never physical deletion)
     * ------------------------------------------------------------------ */

    /**
     * Reverse a posted journal entry with a mirror reversal entry.
     * The original stays fully visible in the ledger; the reversal nets it
     * to zero. Used by transaction edit/cancel flows and by accounting
     * corrections.
     *
     * @return array{reversal_id:int, entry_no:string}
     */
    public static function reverse(int $journalEntryId, string $reason, ?int $userId = null): array
    {
        return Database::transaction(function () use ($journalEntryId, $reason, $userId) {
            $entry = Database::row('SELECT * FROM journal_entries WHERE id = ?', [$journalEntryId]);
            if (!$entry) {
                throw new \RuntimeException('Journal entry not found.');
            }
            if ($entry['status'] === 'reversed') {
                throw new \RuntimeException('This journal entry has already been reversed.');
            }

            $lines = Database::query(
                'SELECT * FROM journal_entry_lines WHERE journal_entry_id = ?',
                [$journalEntryId]
            );
            if (count($lines) < 2) {
                throw new \RuntimeException('Cannot reverse an entry without lines.');
            }

            $now = date('Y-m-d H:i:s');
            Database::execute(
                'INSERT INTO journal_entries
                    (company_id, branch_id, entry_no, entry_date, voucher_type,
                     source_document_type, source_document_id, narration,
                     status, reversed_from_id, reversal_reason, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'reversed\', ?, ?, ?, ?, ?)',
                [
                    $entry['company_id'],
                    $entry['branch_id'],
                    $entry['entry_no'] . ' (R)',
                    $entry['entry_date'],
                    $entry['voucher_type'],
                    $entry['source_document_type'],
                    $entry['source_document_id'],
                    mb_substr('Reversal of ' . $entry['entry_no'] . ': ' . $reason, 0, 500),
                    $journalEntryId,
                    mb_substr($reason, 0, 255),
                    $userId ?? AuthService::id(),
                    $now,
                    $now,
                ]
            );
            $reversalId = (int) Database::lastInsertId();

            foreach ($lines as $line) {
                Database::execute(
                    'INSERT INTO journal_entry_lines
                        (journal_entry_id, account_id, debit, credit, party_type, party_id, narration)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        $reversalId,
                        $line['account_id'],
                        $line['credit'], // mirror
                        $line['debit'],  // mirror
                        $line['party_type'],
                        $line['party_id'],
                        'Reversal',
                    ]
                );
            }

            Database::execute(
                'UPDATE journal_entries SET status = \'reversed\', updated_at = ? WHERE id = ?',
                [$now, $journalEntryId]
            );

            self::rebuildLedgers((int) $entry['company_id'], array_unique(array_column($lines, 'account_id')));

            return ['reversal_id' => $reversalId, 'entry_no' => $entry['entry_no'] . ' (R)'];
        });
    }

    /* ------------------------------------------------------------------
     * Opening balances
     * ------------------------------------------------------------------ */

    /**
     * Post opening balances as a balanced opening entry.
     * Debit-normal accounts (asset/expense) with a positive opening balance
     * are debited; credit-normal (liability/income/equity) are credited.
     * The entry balances itself on the Opening Balance Equity account.
     *
     * $balances: list of ['account_id' => int, 'amount' => float]
     *            (amount positive; direction implied by account type)
     */
    public static function postOpeningBalances(int $companyId, array $balances): array
    {
        $lines = [];
        $totalDr = 0.0;
        $totalCr = 0.0;

        foreach ($balances as $b) {
            $account = Database::row(
                'SELECT id, account_type, is_group, is_active FROM accounts WHERE id = ? AND company_id = ? AND deleted_at IS NULL',
                [(int) $b['account_id'], $companyId]
            );
            if (!$account || (int) $account['is_group'] === 1) {
                throw new \RuntimeException('Opening balance references an invalid account.');
            }
            $amount = round((float) ($b['amount'] ?? 0), 2);
            if ($amount == 0) {
                continue;
            }
            if ($amount < 0) {
                $amount = abs($amount);
            }

            $debitNormal = in_array($account['account_type'], ['asset', 'expense'], true);
            if ($debitNormal) {
                $lines[] = ['account_id' => (int) $account['id'], 'debit' => $amount, 'credit' => 0];
                $totalDr += $amount;
            } else {
                $lines[] = ['account_id' => (int) $account['id'], 'debit' => 0, 'credit' => $amount];
                $totalCr += $amount;
            }
        }

        if ($lines === []) {
            throw new \RuntimeException('Nothing to post — all opening balances are zero.');
        }

        $openingAccountId = (int) AccountDefaultService::get($companyId, 'opening_balance');
        if ($openingAccountId <= 0) {
            throw new \RuntimeException('No Opening Balance Equity account is configured.');
        }

        $difference = round($totalDr - $totalCr, 2);
        if ($difference > 0) {
            $lines[] = ['account_id' => $openingAccountId, 'debit' => 0, 'credit' => $difference];
        } elseif ($difference < 0) {
            $lines[] = ['account_id' => $openingAccountId, 'debit' => abs($difference), 'credit' => 0];
        }

        return self::post([
            'company_id' => $companyId,
            'entry_date' => date('Y-m-d'),
            'voucher_type' => 'opening_balance',
            'entry_no'   => 'OPENING',
            'source_document_type' => 'opening_balance',
            'source_document_id'   => null,
            'narration'  => 'Opening balances',
            'lines'      => $lines,
        ]);
    }

    /**
     * Post a customer/supplier opening balance against the party's
     * receivable/payable account (party-tagged) and the Opening Balance
     * Equity account. Positive amount = the party owes us (receivable debit /
     * payable credit); negative = the reverse (advance / prepayment).
     */
    public static function postPartyOpening(
        int $companyId,
        string $partyType,
        int $partyId,
        float $amount,
        ?int $branchId = null,
    ): array {
        if (!in_array($partyType, ['customer', 'supplier'], true)) {
            throw new \RuntimeException('Invalid party type for opening balance.');
        }
        $amount = round($amount, 2);
        if ($amount == 0) {
            throw new \RuntimeException('Opening balance amount cannot be zero.');
        }

        $accountKey = $partyType === 'customer' ? 'receivable' : 'payable';
        $partyAccountId = (int) AccountDefaultService::get($companyId, $accountKey, $branchId);
        if ($partyAccountId <= 0) {
            throw new \RuntimeException('No ' . ($partyType === 'customer' ? 'Accounts Receivable' : 'Accounts Payable') . ' account is configured.');
        }
        $openingAccountId = (int) AccountDefaultService::get($companyId, 'opening_balance', $branchId);
        if ($openingAccountId <= 0) {
            throw new \RuntimeException('No Opening Balance Equity account is configured.');
        }

        // Customer receivable: positive opening → debit AR (they owe us).
        // Supplier payable: positive opening → credit AP (we owe them).
        $debitParty = $partyType === 'customer' ? $amount > 0 : $amount < 0;
        $partyLine = [
            'account_id' => $partyAccountId,
            'debit'      => $debitParty ? abs($amount) : 0,
            'credit'     => $debitParty ? 0 : abs($amount),
            'party_type' => $partyType,
            'party_id'   => $partyId,
            'narration'  => 'Opening balance',
        ];
        $equityLine = [
            'account_id' => $openingAccountId,
            'debit'      => $debitParty ? 0 : abs($amount),
            'credit'     => $debitParty ? abs($amount) : 0,
        ];

        return self::post([
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'entry_date' => date('Y-m-d'),
            'voucher_type' => 'opening_balance',
            'entry_no'   => 'OPENING',
            'source_document_type' => $partyType . '_opening_balance',
            'source_document_id'   => $partyId,
            'narration'  => 'Opening balance — ' . $partyType . ' #' . $partyId,
            'lines'      => [$partyLine, $equityLine],
        ]);
    }

    /* ------------------------------------------------------------------
     * Ledger materialization
     * ------------------------------------------------------------------ */

    /**
     * Rebuild the account_ledger for a set of accounts.
     * Deterministic: running balance is recomputed in (entry_date, id) order
     * from ALL journal lines (posted + reversal lines, which net to zero).
     */
    public static function rebuildLedgers(int $companyId, array $accountIds): void
    {
        if ($accountIds === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($accountIds as $accountId) {
            $accountId = (int) $accountId;

            Database::execute('DELETE FROM account_ledger WHERE account_id = ?', [$accountId]);

            $rows = Database::query(
                'SELECT jel.id AS line_id, je.entry_date, je.entry_no, je.voucher_type,
                        jel.debit, jel.credit, jel.party_type, jel.party_id,
                        je.source_document_type, je.source_document_id,
                        je.company_id, je.id AS journal_entry_id
                 FROM journal_entry_lines jel
                 JOIN journal_entries je ON je.id = jel.journal_entry_id
                 WHERE jel.account_id = ?
                 ORDER BY je.entry_date ASC, je.id ASC, jel.id ASC',
                [$accountId]
            );

            $balance = 0.0;
            foreach ($rows as $row) {
                $balance = round($balance + (float) $row['debit'] - (float) $row['credit'], 2);
                Database::execute(
                    'INSERT INTO account_ledger
                        (company_id, account_id, entry_date, voucher_no, voucher_type,
                         journal_entry_id, line_id, debit, credit, balance,
                         party_type, party_id, source_document_type, source_document_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        (int) $row['company_id'],
                        $accountId,
                        $row['entry_date'],
                        $row['entry_no'],
                        $row['voucher_type'],
                        (int) $row['journal_entry_id'],
                        (int) $row['line_id'],
                        (float) $row['debit'],
                        (float) $row['credit'],
                        $balance,
                        $row['party_type'],
                        $row['party_id'],
                        $row['source_document_type'],
                        $row['source_document_id'],
                        $now,
                    ]
                );
            }
        }
    }

    /** Fetch a journal entry with its lines (for viewing / printing). */
    public static function entry(int $journalEntryId): ?array
    {
        $entry = Database::row('SELECT * FROM journal_entries WHERE id = ?', [$journalEntryId]);
        if (!$entry) {
            return null;
        }
        $entry['lines'] = Database::query(
            'SELECT jel.*, a.code AS account_code, a.name AS account_name, a.account_type
             FROM journal_entry_lines jel
             JOIN accounts a ON a.id = jel.account_id
             WHERE jel.journal_entry_id = ?
             ORDER BY jel.id',
            [$journalEntryId]
        );
        return $entry;
    }
}
