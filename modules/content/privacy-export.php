<?php

declare(strict_types=1);

if (!function_exists('sr_content_privacy_asset_settlement_summary')) {
    function sr_content_privacy_asset_settlement_summary(array $row): array
    {
        $snapshot = [];
        $snapshotJson = (string) ($row['purchase_power_snapshot_json'] ?? '');
        if ($snapshotJson !== '') {
            $decoded = json_decode($snapshotJson, true);
            $snapshot = is_array($decoded) ? $decoded : [];
        }

        return [
            'asset_module' => (string) ($row['asset_module'] ?? ''),
            'asset_amount' => (int) ($row['amount'] ?? 0),
            'settlement_amount' => (int) ($row['settlement_amount'] ?? 0),
            'settlement_currency' => (string) ($row['settlement_currency'] ?? ''),
            'settlement_kind' => (string) ($row['settlement_kind'] ?? ''),
            'snapshot_schema_version' => (string) ($row['snapshot_schema_version'] ?? ''),
            'rounding_policy_version' => (string) ($row['rounding_policy_version'] ?? ''),
            'purchase_power' => [
                'asset_units' => (int) ($snapshot['asset_units'] ?? 0),
                'settlement_units' => (int) ($snapshot['settlement_units'] ?? 0),
                'settlement_currency' => (string) ($snapshot['settlement_currency'] ?? ''),
                'currency_min_unit' => (int) ($snapshot['currency_min_unit'] ?? 0),
                'rounding_policy_version' => (string) ($snapshot['rounding_policy_version'] ?? ($snapshot['policy_version'] ?? '')),
            ],
        ];
    }
}

if (!function_exists('sr_content_privacy_add_asset_settlement_summaries')) {
    function sr_content_privacy_add_asset_settlement_summaries(array $rows): array
    {
        foreach ($rows as &$row) {
            if (is_array($row)) {
                $row['settlement_summary'] = sr_content_privacy_asset_settlement_summary($row);
            }
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('sr_content_privacy_log_ids_from_json')) {
    function sr_content_privacy_log_ids_from_json(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('sr_content_privacy_add_file_download_settlement_summaries')) {
    function sr_content_privacy_add_file_download_settlement_summaries(PDO $pdo, array $rows): array
    {
        foreach ($rows as &$row) {
            if (is_array($row)) {
                $row['settlement_summaries'] = [];
            }
        }
        unset($row);

        if ($rows === []
            || !function_exists('sr_content_asset_access_logs_table_exists')
            || !sr_content_asset_access_logs_table_exists($pdo)
            || !function_exists('sr_content_asset_access_log_columns')) {
            return $rows;
        }

        $idsByLogIndex = [];
        $allIds = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $ids = sr_content_privacy_log_ids_from_json($row['asset_access_log_ids_json'] ?? '[]');
            if ($ids === []) {
                continue;
            }
            $idsByLogIndex[(int) $index] = $ids;
            foreach ($ids as $id) {
                $allIds[$id] = $id;
            }
        }
        if ($allIds === []) {
            return $rows;
        }

        $columns = sr_content_asset_access_log_columns($pdo);
        foreach (['id', 'content_id', 'account_id', 'asset_module', 'reference_type', 'reference_id', 'access_kind', 'amount'] as $requiredColumn) {
            if (!isset($columns[$requiredColumn])) {
                return $rows;
            }
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($allIds) as $index => $id) {
            $key = 'id_' . (string) $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $id;
        }

        $stmt = $pdo->prepare(
            'SELECT id, content_id, account_id, asset_module, reference_type, reference_id, access_kind, amount,
                    settlement_amount,
                    settlement_currency,
                    purchase_power_snapshot_json,
                    settlement_kind,
                    snapshot_schema_version,
                    rounding_policy_version
             FROM sr_content_asset_access_logs
             WHERE id IN (' . implode(', ', $placeholders) . ')
             ORDER BY id ASC'
        );
        $stmt->execute($params);

        $assetLogsById = [];
        foreach ($stmt->fetchAll() as $assetLog) {
            $assetLogsById[(int) ($assetLog['id'] ?? 0)] = $assetLog;
        }

        foreach ($idsByLogIndex as $index => $ids) {
            $summaries = [];
            $downloadLog = $rows[$index] ?? [];
            if (!is_array($downloadLog)) {
                continue;
            }
            foreach ($ids as $id) {
                $assetLog = $assetLogsById[$id] ?? null;
                if (!is_array($assetLog)) {
                    continue;
                }
                if ((int) ($assetLog['content_id'] ?? 0) !== (int) ($downloadLog['content_id'] ?? 0)
                    || (int) ($assetLog['account_id'] ?? 0) !== (int) ($downloadLog['account_id'] ?? 0)
                    || (string) ($assetLog['reference_type'] ?? '') !== 'content.download'
                    || (int) ($assetLog['reference_id'] ?? 0) !== (int) ($downloadLog['file_id'] ?? 0)
                    || (string) ($assetLog['access_kind'] ?? '') !== 'download') {
                    continue;
                }
                $summaries[] = sr_content_privacy_asset_settlement_summary($assetLog);
            }
            $rows[$index]['settlement_summaries'] = $summaries;
        }

        return $rows;
    }
}

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return [
            'access_entitlements' => [],
            'asset_access_logs' => [],
            'file_download_logs' => [],
            'asset_action_logs' => [],
            'submissions' => [],
            'author_applications' => [],
            'author_reward_logs' => [],
            'comments' => [],
            'series' => [],
            'series_items' => [],
        ];
    }

    require_once SR_ROOT . '/modules/content/helpers.php';

    $accessEntitlements = [];
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
                l.settlement_amount, l.settlement_currency, l.purchase_power_snapshot_json,
                l.settlement_kind, l.snapshot_schema_version, l.rounding_policy_version,
                l.group_policy_snapshot_json, l.created_at
         FROM sr_content_asset_access_logs l
         LEFT JOIN sr_content_items p ON p.id = l.content_id
         WHERE l.account_id = :account_id
         ORDER BY l.id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);

    $accessLogs = sr_content_privacy_add_asset_settlement_summaries($stmt->fetchAll());

    $fileDownloadLogs = [];
    if (function_exists('sr_content_file_download_logs_table_exists') && sr_content_file_download_logs_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT d.id, d.content_id, COALESCE(NULLIF(p.slug, \'\'), NULLIF(d.content_slug_snapshot, \'\')) AS slug,
                    COALESCE(NULLIF(p.title, \'\'), NULLIF(d.content_title_snapshot, \'\')) AS title,
                    d.file_id, COALESCE(NULLIF(f.title, \'\'), NULLIF(d.file_title_snapshot, \'\')) AS file_title,
                    COALESCE(NULLIF(f.original_name, \'\'), NULLIF(d.file_original_name_snapshot, \'\')) AS original_name,
                    d.account_id, d.download_type, d.charge_policy,
                    d.asset_module, d.amount, d.asset_access_log_ids_json,
                    d.refund_status, d.refund_transaction_ids_json, d.refund_note, d.refunded_by_account_id, d.refunded_at, d.access_revoked_at,
                    d.created_at
             FROM sr_content_file_download_logs d
             LEFT JOIN sr_content_items p ON p.id = d.content_id
             LEFT JOIN sr_content_files f ON f.id = d.file_id
             WHERE d.account_id = :account_id
             ORDER BY d.id ASC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $fileDownloadLogs = sr_content_privacy_add_file_download_settlement_summaries($pdo, $stmt->fetchAll());
    }

    $stmt = $pdo->prepare(
        'SELECT l.id, l.content_id, p.slug, p.title, l.account_id, l.asset_module, l.transaction_id,
                l.reference_type, l.reference_id, l.action_key, l.direction, l.amount,
                l.settlement_amount, l.settlement_currency, l.purchase_power_snapshot_json,
                l.settlement_kind, l.snapshot_schema_version, l.rounding_policy_version,
                l.group_policy_snapshot_json, l.created_at
         FROM sr_content_asset_action_logs l
         LEFT JOIN sr_content_items p ON p.id = l.content_id
         WHERE l.account_id = :account_id
         ORDER BY l.id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $actionLogs = sr_content_privacy_add_asset_settlement_summaries($stmt->fetchAll());

    $submissions = [];
    if (function_exists('sr_content_optional_table_exists') && sr_content_optional_table_exists($pdo, 'sr_content_submissions')) {
        $submissionStmt = $pdo->prepare(
            'SELECT s.id, s.content_id, p.slug AS content_slug, p.title AS content_title,
                    s.content_group_id, g.group_key, g.title AS group_title,
                    s.author_account_id, s.slug, s.title, s.summary, s.body_text, s.body_format,
                    s.review_status, s.publish_target_status, s.review_note,
                    s.reviewed_by, s.reviewed_at, s.created_at, s.updated_at
             FROM sr_content_submissions s
             LEFT JOIN sr_content_items p ON p.id = s.content_id
             LEFT JOIN sr_content_groups g ON g.id = s.content_group_id
             WHERE s.author_account_id = :account_id
             ORDER BY s.id ASC
             LIMIT 1000'
        );
        $submissionStmt->execute(['account_id' => $accountId]);
        $submissions = $submissionStmt->fetchAll();
    }

    $authorRewardLogs = [];
    $authorApplications = [];
    if (function_exists('sr_content_optional_table_exists') && sr_content_optional_table_exists($pdo, 'sr_content_author_applications')) {
        $applicationStmt = $pdo->prepare(
            'SELECT id, account_id, application_note, status, review_note,
                    reviewed_by, reviewed_at, created_at, updated_at
             FROM sr_content_author_applications
             WHERE account_id = :account_id
             ORDER BY id ASC
             LIMIT 1000'
        );
        $applicationStmt->execute(['account_id' => $accountId]);
        $authorApplications = $applicationStmt->fetchAll();
    }

    if (function_exists('sr_content_optional_table_exists') && sr_content_optional_table_exists($pdo, 'sr_content_author_reward_logs')) {
        $rewardStmt = $pdo->prepare(
            'SELECT r.id, r.submission_id, r.content_id, p.slug, p.title,
                    r.author_account_id, r.asset_module, r.amount, r.transaction_id,
                    r.status, r.failure_reason, r.created_by_account_id, r.created_at, r.updated_at
             FROM sr_content_author_reward_logs r
             LEFT JOIN sr_content_items p ON p.id = r.content_id
             WHERE r.author_account_id = :account_id
             ORDER BY r.id ASC
             LIMIT 1000'
        );
        $rewardStmt->execute(['account_id' => $accountId]);
        $authorRewardLogs = $rewardStmt->fetchAll();
    }

    $series = [];
    $seriesItems = [];
    if (sr_content_series_supported($pdo)) {
        $seriesStmt = $pdo->prepare(
            'SELECT id, series_key, title, description, status, visibility, sort_order, created_at, updated_at
             FROM sr_content_series
             WHERE created_by = :account_id OR updated_by = :updated_account_id
             ORDER BY id ASC
             LIMIT 1000'
        );
        $seriesStmt->execute([
            'account_id' => $accountId,
            'updated_account_id' => $accountId,
        ]);
        $series = $seriesStmt->fetchAll();

        $seriesItemStmt = $pdo->prepare(
            'SELECT id, series_id, content_id, active_content_id, episode_label, item_status, sort_order, created_at, updated_at
             FROM sr_content_series_items
             WHERE created_by = :account_id
             ORDER BY id ASC
             LIMIT 1000'
        );
        $seriesItemStmt->execute(['account_id' => $accountId]);
        $seriesItems = $seriesItemStmt->fetchAll();
    }

    $comments = [];
    if (function_exists('sr_content_comments_table_exists') && sr_content_comments_table_exists($pdo)) {
        $commentStmt = $pdo->prepare(
            'SELECT c.id, c.content_id, c.parent_comment_id, c.thread_root_id, c.depth, p.slug, p.title, c.author_account_id,
                    c.author_public_name_snapshot, c.body_text, c.is_secret, c.status, c.created_at, c.updated_at
             FROM sr_content_comments c
             LEFT JOIN sr_content_items p ON p.id = c.content_id
             WHERE c.author_account_id = :account_id
             ORDER BY c.id ASC
             LIMIT 1000'
        );
        $commentStmt->execute(['account_id' => $accountId]);
        $comments = $commentStmt->fetchAll();
    }

    return [
        'access_entitlements' => $accessEntitlements,
        'asset_access_logs' => $accessLogs,
        'file_download_logs' => $fileDownloadLogs,
        'asset_action_logs' => $actionLogs,
        'submissions' => $submissions,
        'author_applications' => $authorApplications,
        'author_reward_logs' => $authorRewardLogs,
        'comments' => $comments,
        'series' => $series,
        'series_items' => $seriesItems,
    ];
};
