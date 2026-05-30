<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return [
            'access_entitlements' => [],
            'asset_access_logs' => [],
            'file_download_logs' => [],
            'asset_action_logs' => [],
        ];
    }

    $accessEntitlements = [];
    if (!function_exists('sr_content_access_entitlements_table_exists')) {
        require_once SR_ROOT . '/modules/content/helpers.php';
    }
    if (sr_content_access_entitlements_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT e.id, e.account_id, e.content_id, p.slug, p.title, e.subject_type, e.subject_id,
                    e.access_kind, e.source_kind, e.source_asset_module, e.source_charge_policy,
                    e.source_reference, e.granted_at, e.created_at
             FROM sr_content_access_entitlements e
             LEFT JOIN sr_content_items p ON p.id = e.content_id
             WHERE e.account_id = :account_id
             ORDER BY e.id ASC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $accessEntitlements = $stmt->fetchAll();
    }

    $stmt = $pdo->prepare(
        'SELECT l.id, l.content_id, p.slug, p.title, l.account_id, l.asset_module, l.transaction_id,
                l.reference_type, l.reference_id, l.access_kind, l.charge_policy, l.amount,
                l.group_policy_snapshot_json, l.created_at
         FROM sr_content_asset_access_logs l
         LEFT JOIN sr_content_items p ON p.id = l.content_id
         WHERE l.account_id = :account_id
         ORDER BY l.id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);

    $accessLogs = $stmt->fetchAll();

    $fileDownloadLogs = [];
    if (function_exists('sr_content_file_download_logs_table_exists') && sr_content_file_download_logs_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT d.id, d.content_id, p.slug, p.title, d.file_id, f.title AS file_title,
                    f.original_name, d.account_id, d.download_type, d.charge_policy,
                    d.asset_module, d.amount, d.asset_access_log_ids_json,
                    d.refund_status, d.refund_transaction_ids_json, d.refund_note,
                    d.refunded_by_account_id, d.refunded_at, d.access_revoked_at,
                    d.created_at
             FROM sr_content_file_download_logs d
             LEFT JOIN sr_content_items p ON p.id = d.content_id
             LEFT JOIN sr_content_files f ON f.id = d.file_id
             WHERE d.account_id = :account_id
             ORDER BY d.id ASC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $fileDownloadLogs = $stmt->fetchAll();
    }

    $stmt = $pdo->prepare(
        'SELECT l.id, l.content_id, p.slug, p.title, l.account_id, l.asset_module, l.transaction_id,
                l.reference_type, l.reference_id, l.action_key, l.direction, l.amount,
                l.group_policy_snapshot_json, l.created_at
         FROM sr_content_asset_action_logs l
         LEFT JOIN sr_content_items p ON p.id = l.content_id
         WHERE l.account_id = :account_id
         ORDER BY l.id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'access_entitlements' => $accessEntitlements,
        'asset_access_logs' => $accessLogs,
        'file_download_logs' => $fileDownloadLogs,
        'asset_action_logs' => $stmt->fetchAll(),
    ];
};
