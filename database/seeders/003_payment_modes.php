<?php

/**
 * TradeERP — default Payment Modes seeder.
 *
 * Seeds the standard payment modes for a company and binds Cash to the
 * company's Cash default account. Re-runnable (skips existing codes).
 *
 * @param PDO $pdo
 * @param int $companyId
 * @return int number of modes ensured
 */
function seed_default_payment_modes(PDO $pdo, int $companyId): int
{
    $now = date('Y-m-d H:i:s');

    // Cash default account for the automatic binding
    $stmt = $pdo->prepare('SELECT account_id FROM account_defaults WHERE company_id = ? AND `key` = ? AND branch_id IS NULL LIMIT 1');
    $stmt->execute([$companyId, 'cash']);
    $cashAccountId = $stmt->fetchColumn();
    $cashAccountId = $cashAccountId !== false ? (int) $cashAccountId : null;

    $modes = [
        ['Cash',          'CASH',  $cashAccountId, 1, 0, 1],
        ['Bank Transfer', 'BANK',  null,           0, 1, 1],
        ['Cheque',        'CHQ',   null,           0, 1, 1],
        ['Online',        'ONLN',  null,           0, 1, 1],
        ['Credit',        'CRDT',  null,           0, 0, 1],
        ['Other',         'OTH',   null,           0, 0, 1],
    ];

    $ensure = $pdo->prepare(
        'INSERT INTO payment_modes (company_id, name, code, account_id, is_cash, is_bank, is_system, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), account_id = VALUES(account_id), updated_at = VALUES(updated_at)'
    );

    $count = 0;
    foreach ($modes as [$name, $code, $accountId, $isCash, $isBank, $isSystem]) {
        $ensure->execute([$companyId, $name, $code, $accountId, $isCash, $isBank, $isSystem, $now, $now]);
        $count++;
    }

    return $count;
}
