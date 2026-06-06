<?php

declare(strict_types=1);

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
        $hasDownloadLogSnapshots = function_exists('sr_content_file_download_log_snapshot_columns_exist') && sr_content_file_download_log_snapshot_columns_exist($pdo);
        $contentTitleSelect = $hasDownloadLogSnapshots ? "COALESCE(NULLIF(p.title, ''), NULLIF(d.content_title_snapshot, ''))" : 'p.title';
        $contentSlugSelect = $hasDownloadLogSnapshots ? "COALESCE(NULLIF(p.slug, ''), NULLIF(d.content_slug_snapshot, ''))" : 'p.slug';
        $fileTitleSelect = $hasDownloadLogSnapshots ? "COALESCE(NULLIF(f.title, ''), NULLIF(d.file_title_snapshot, ''))" : 'f.title';
        $fileOriginalNameSelect = $hasDownloadLogSnapshots ? "COALESCE(NULLIF(f.original_name, ''), NULLIF(d.file_original_name_snapshot, ''))" : 'f.original_name';
        $stmt = $pdo->prepare(
            'SELECT d.id, d.content_id, ' . $contentSlugSelect . ' AS slug, ' . $contentTitleSelect . ' AS title,
                    d.file_id, ' . $fileTitleSelect . ' AS file_title,
                    ' . $fileOriginalNameSelect . ' AS original_name, d.account_id, d.download_type, d.charge_policy,
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
        $snapshotSelectSql = sr_content_comments_author_public_name_snapshot_column_exists($pdo)
            ? 'c.author_public_name_snapshot'
            : "'' AS author_public_name_snapshot";
        $secretSelectSql = sr_content_comments_is_secret_column_exists($pdo)
            ? 'c.is_secret'
            : '0 AS is_secret';
        $commentStmt = $pdo->prepare(
            'SELECT c.id, c.content_id, p.slug, p.title, c.author_account_id, ' . $snapshotSelectSql . ', c.body_text, ' . $secretSelectSql . ', c.status, c.created_at, c.updated_at
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
        'asset_action_logs' => $stmt->fetchAll(),
        'submissions' => $submissions,
        'author_applications' => $authorApplications,
        'author_reward_logs' => $authorRewardLogs,
        'comments' => $comments,
        'series' => $series,
        'series_items' => $seriesItems,
    ];
};
