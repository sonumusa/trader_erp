<?php

/**
 * TradeERP — system role grant syncer.
 *
 * Applies config/role_grants.php to system roles ADDITIVELY: rows that are
 * missing get inserted, existing grants (including custom edits) are kept.
 * Used by the installer and by bin/migrate.php on upgrades.
 *
 * @param PDO $pdo
 * @return int number of grants added
 */
function ensure_role_grants(PDO $pdo): int
{
    $grants = require dirname(__DIR__, 2) . '/config/role_grants.php';

    // Load all permissions keyed module.document.action => id
    $permStmt = $pdo->query('SELECT id, module, document, action FROM permissions');
    $permIds = [];
    foreach ($permStmt as $row) {
        $permIds[$row['module'] . '.' . $row['document'] . '.' . $row['action']] = (int) $row['id'];
    }

    $roleStmt = $pdo->query("SELECT id, slug FROM roles WHERE deleted_at IS NULL");
    $grantedStmt = $pdo->prepare('SELECT 1 FROM permission_role WHERE role_id = ? AND permission_id = ?');
    $insertStmt = $pdo->prepare('INSERT INTO permission_role (role_id, permission_id) VALUES (?, ?)');

    $added = 0;
    foreach ($roleStmt as $role) {
        $slug = $role['slug'];
        $rid = (int) $role['id'];
        if (!isset($grants[$slug])) {
            continue;
        }
        $set = $grants[$slug];
        if ($set === '*') {
            continue; // super admin holds the runtime wildcard; no rows needed
        }
        foreach ($set as $key => $_) {
            if (str_ends_with($key, '.*')) {
                $prefix = rtrim($key, '*');
                foreach ($permIds as $pkey => $pid) {
                    if (str_starts_with($pkey, $prefix)) {
                        $grantedStmt->execute([$rid, $pid]);
                        if (!$grantedStmt->fetchColumn()) {
                            $insertStmt->execute([$rid, $pid]);
                            $added++;
                        }
                    }
                }
            } elseif (isset($permIds[$key])) {
                $grantedStmt->execute([$rid, $permIds[$key]]);
                if (!$grantedStmt->fetchColumn()) {
                    $insertStmt->execute([$rid, $permIds[$key]]);
                    $added++;
                }
            }
        }
    }

    return $added;
}
