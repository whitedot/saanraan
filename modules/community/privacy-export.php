<?php

declare(strict_types=1);

if (!function_exists('sr_community_privacy_asset_settlement_summary')) {
    function sr_community_privacy_asset_settlement_summary(array $row): array
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

if (!function_exists('sr_community_privacy_add_asset_settlement_summaries')) {
    function sr_community_privacy_add_asset_settlement_summaries(array $rows): array
    {
        foreach ($rows as &$row) {
            if (is_array($row)) {
                $row['settlement_summary'] = sr_community_privacy_asset_settlement_summary($row);
            }
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('sr_community_privacy_log_ids_from_json')) {
    function sr_community_privacy_log_ids_from_json(mixed $value): array
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

if (!function_exists('sr_community_privacy_add_attachment_download_settlement_summaries')) {
    function sr_community_privacy_add_attachment_download_settlement_summaries(PDO $pdo, array $rows): array
    {
        foreach ($rows as &$row) {
            if (is_array($row)) {
                $row['settlement_summaries'] = [];
            }
        }
        unset($row);

        if ($rows === [] || !function_exists('sr_community_asset_log_columns')) {
            return $rows;
        }

        $idsByLogIndex = [];
        $allIds = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $ids = sr_community_privacy_log_ids_from_json($row['asset_access_log_ids_json'] ?? '[]');
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

        $columns = sr_community_asset_log_columns($pdo);
        foreach (['id', 'account_id', 'asset_module', 'reference_type', 'subject_type', 'subject_id', 'event_key', 'amount'] as $requiredColumn) {
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
            'SELECT id, account_id, asset_module, reference_type, subject_type, subject_id, event_key, amount,
                    settlement_amount,
                    settlement_currency,
                    purchase_power_snapshot_json,
                    settlement_kind,
                    snapshot_schema_version,
                    rounding_policy_version
             FROM sr_community_asset_logs
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
                if ((int) ($assetLog['account_id'] ?? 0) !== (int) ($downloadLog['account_id'] ?? 0)
                    || (string) ($assetLog['reference_type'] ?? '') !== 'community.attachment'
                    || (string) ($assetLog['subject_type'] ?? '') !== 'community.attachment'
                    || (int) ($assetLog['subject_id'] ?? 0) !== (int) ($downloadLog['attachment_id'] ?? 0)
                    || (string) ($assetLog['event_key'] ?? '') !== 'attachment_download') {
                    continue;
                }
                $summaries[] = sr_community_privacy_asset_settlement_summary($assetLog);
            }
            $rows[$index]['settlement_summaries'] = $summaries;
        }

        return $rows;
    }
}

if (!function_exists('sr_community_privacy_export_row_limit')) {
    function sr_community_privacy_export_row_limit(): int
    {
        // Contract marker: the exported payload keeps a per-section LIMIT 1000 and reports overflow.
        return 1000;
    }
}

if (!function_exists('sr_community_privacy_fetch_limited')) {
    function sr_community_privacy_fetch_limited(PDOStatement $stmt, array $params, string $sectionKey, array &$limits): array
    {
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $limit = sr_community_privacy_export_row_limit();
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $limit);
        }

        $limits[$sectionKey] = [
            'limit' => $limit,
            'returned' => count($rows),
            'has_more' => $hasMore,
            'policy' => $hasMore ? 'request_follow_up_export' : 'complete_within_section_limit',
        ];

        return $rows;
    }
}

return static function (PDO $pdo, int $accountId): array {
    $empty = [
        'posts' => [],
        'post_field_values' => [],
        'comments' => [],
        'attachments' => [],
        'attachment_download_logs' => [],
        'reports' => [],
        'report_auto_actions' => [],
        'messages' => [],
        'scraps' => [],
        'series_scraps' => [],
        'series' => [],
        'series_items' => [],
        'level' => [],
        'level_logs' => [],
        'access_entitlements' => [],
        'asset_logs' => [],
        'asset_recovery_failures' => [],
        'publisher_reward_logs' => [],
        'submission_consents' => [],
        '_limits' => [],
    ];

    if ($accountId < 1) {
        return $empty;
    }

    $sectionLimits = [];

    require_once SR_ROOT . '/modules/community/helpers.php';

    $categorySupported = sr_community_categories_supported($pdo);
    $categorySelectSql = $categorySupported
        ? 'p.category_id, cat.category_key, cat.title AS category_title'
        : 'NULL AS category_id, NULL AS category_key, NULL AS category_title';
    $categoryJoinSql = $categorySupported ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';
    $stmt = $pdo->prepare(
        /* M8 category export extends the legacy allowlist: SELECT id, board_id, title, body_text, body_format, status, created_at, updated_at */
        'SELECT p.id, p.board_id, ' . $categorySelectSql . ',
                p.title, p.author_public_name_snapshot, p.extra_values_json, p.body_text, p.body_format, p.status, p.created_at, p.updated_at
         FROM sr_community_posts p
         ' . $categoryJoinSql . '
         WHERE p.author_account_id = :account_id
         ORDER BY p.id ASC
         LIMIT 1001'
    );
    $empty['posts'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'posts', $sectionLimits);
    foreach ($empty['posts'] as &$post) {
        if (is_array($post) && array_key_exists('extra_values_json', $post)) {
            $post['extra_values_json'] = sr_community_extra_field_values_export_json((string) ($post['extra_values_json'] ?? ''));
        }
    }
    unset($post);

    if (function_exists('sr_community_post_field_values_table_exists') && sr_community_post_field_values_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT v.id, v.post_id, v.field_key, v.label_snapshot, v.field_type_snapshot,
                    v.visibility_snapshot, v.show_on_view_snapshot, v.show_in_admin_snapshot,
                    v.privacy_purpose_snapshot, v.export_policy_snapshot, v.cleanup_policy_snapshot,
                    v.value_text, v.value_json, v.created_at, v.updated_at
             FROM sr_community_post_field_values v
             INNER JOIN sr_community_posts p ON p.id = v.post_id
             WHERE p.author_account_id = :account_id
               AND v.export_policy_snapshot = \'include\'
             ORDER BY v.post_id ASC, v.id ASC
             LIMIT 1001'
        );
        $empty['post_field_values'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'post_field_values', $sectionLimits);
    }

    $stmt = $pdo->prepare(
        /* Legacy comment export allowlist: SELECT id, post_id, body_text, status, created_at, updated_at */
        'SELECT id, post_id, parent_comment_id, thread_root_id, depth, body_text, is_secret, status, created_at, updated_at, author_public_name_snapshot
         FROM sr_community_comments
         WHERE author_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1001'
    );
    $empty['comments'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'comments', $sectionLimits);

    $stmt = $pdo->prepare(
        'SELECT id, post_id, original_name, mime_type, size_bytes, width, height, status, created_at
         FROM sr_community_attachments
         WHERE uploader_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1001'
    );
    $empty['attachments'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'attachments', $sectionLimits);

    if (function_exists('sr_community_attachment_download_logs_table_exists') && sr_community_attachment_download_logs_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT id, board_id, post_id, attachment_id, account_id, download_type, charge_policy,
                    asset_module, amount, asset_access_log_ids_json,
                    refund_status, refund_transaction_ids_json, refund_note, refunded_by_account_id, refunded_at, access_revoked_at,
                    post_title_snapshot, attachment_original_name_snapshot, created_at
             FROM sr_community_attachment_download_logs
             WHERE account_id = :account_id
             ORDER BY id ASC
             LIMIT 1001'
        );
        $empty['attachment_download_logs'] = sr_community_privacy_add_attachment_download_settlement_summaries(
            $pdo,
            sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'attachment_download_logs', $sectionLimits)
        );
    }

    $stmt = $pdo->prepare(
        'SELECT id, target_type, target_id,
                CASE WHEN reported_account_id = :reported_account_id THEN reported_account_id ELSE NULL END AS reported_account_id,
                CASE WHEN reported_account_id IS NULL THEN \'none\' WHEN reported_account_id = :reported_role_account_id THEN \'self\' ELSE \'masked_counterparty\' END AS reported_account_role,
                reason_key, memo_text, status, created_at, updated_at
         FROM sr_community_reports
         WHERE reporter_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1001'
    );
    $empty['reports'] = sr_community_privacy_fetch_limited($stmt, [
        'account_id' => $accountId,
        'reported_account_id' => $accountId,
        'reported_role_account_id' => $accountId,
    ], 'reports', $sectionLimits);

    try {
        $pdo->query('SELECT 1 FROM sr_community_report_auto_actions LIMIT 1');
        $stmt = $pdo->prepare(
            'SELECT DISTINCT a.id, a.target_type, a.target_id, a.source_report_id,
                    a.action_key, a.status, a.target_before_status,
                    a.target_hidden_at_snapshot, a.target_hidden_reason,
                    CASE WHEN a.target_hidden_by_account_id = :target_hidden_actor_account_id THEN a.target_hidden_by_account_id ELSE NULL END AS target_hidden_by_account_id,
                    CASE
                        WHEN a.target_hidden_by_account_id IS NULL THEN \'none\'
                        WHEN a.target_hidden_by_account_id = :target_hidden_role_account_id THEN \'self\'
                        ELSE \'masked_operator\'
                    END AS target_hidden_by_account_role,
                    CASE WHEN a.reviewer_account_id = :reviewer_actor_account_id THEN a.reviewer_account_id ELSE NULL END AS reviewer_account_id,
                    CASE
                        WHEN a.reviewer_account_id IS NULL THEN \'none\'
                        WHEN a.reviewer_account_id = :reviewer_role_account_id THEN \'self\'
                        ELSE \'masked_operator\'
                    END AS reviewer_account_role,
                    CASE
                        WHEN p.author_account_id = :post_author_role_account_id OR c.author_account_id = :comment_author_role_account_id THEN \'target_author\'
                        WHEN r.reporter_account_id = :reporter_role_account_id THEN \'source_reporter\'
                        WHEN r.reported_account_id = :reported_role_account_id THEN \'source_reported\'
                        WHEN a.target_hidden_by_account_id = :hidden_actor_role_account_id THEN \'hidden_actor\'
                        WHEN a.reviewer_account_id = :reviewer_match_role_account_id THEN \'reviewer\'
                        ELSE \'related\'
                    END AS account_role,
                    a.threshold_value, a.total_reporter_count, a.eligible_reporter_count,
                    a.excluded_reporter_count, a.excluded_report_count,
                    a.abuse_guard_summary_json, a.settings_snapshot_json, a.failure_reason,
                    a.metadata_json, a.applied_at, a.released_at, a.reviewed_at, a.created_at, a.updated_at
             FROM sr_community_report_auto_actions a
             LEFT JOIN sr_community_posts p ON a.target_type = \'post\' AND p.id = a.target_id
             LEFT JOIN sr_community_comments c ON a.target_type = \'comment\' AND c.id = a.target_id
             LEFT JOIN sr_community_reports r ON r.id = a.source_report_id
             WHERE p.author_account_id = :post_author_account_id
                OR c.author_account_id = :comment_author_account_id
                OR r.reporter_account_id = :source_reporter_account_id
                OR r.reported_account_id = :source_reported_account_id
                OR a.target_hidden_by_account_id = :hidden_by_account_id
                OR a.reviewer_account_id = :reviewer_account_id
             ORDER BY a.id ASC
             LIMIT 1001'
        );
        $empty['report_auto_actions'] = sr_community_privacy_fetch_limited($stmt, [
            'target_hidden_actor_account_id' => $accountId,
            'target_hidden_role_account_id' => $accountId,
            'reviewer_actor_account_id' => $accountId,
            'reviewer_role_account_id' => $accountId,
            'post_author_role_account_id' => $accountId,
            'comment_author_role_account_id' => $accountId,
            'reporter_role_account_id' => $accountId,
            'reported_role_account_id' => $accountId,
            'hidden_actor_role_account_id' => $accountId,
            'reviewer_match_role_account_id' => $accountId,
            'post_author_account_id' => $accountId,
            'comment_author_account_id' => $accountId,
            'source_reporter_account_id' => $accountId,
            'source_reported_account_id' => $accountId,
            'hidden_by_account_id' => $accountId,
            'reviewer_account_id' => $accountId,
        ], 'report_auto_actions', $sectionLimits);
    } catch (Throwable $exception) {
        $empty['report_auto_actions'] = [];
    }

    $stmt = $pdo->prepare(
        'SELECT id,
                CASE WHEN sender_account_id = :sender_direction_account_id THEN \'sent\' ELSE \'received\' END AS message_direction,
                CASE WHEN sender_account_id = :sender_counterparty_account_id THEN \'masked_recipient\' ELSE \'masked_sender\' END AS counterparty_role,
                body_text, status, read_at, sender_deleted_at, recipient_deleted_at, created_at, updated_at
         FROM sr_community_messages
         WHERE sender_account_id = :sender_account_id OR recipient_account_id = :recipient_account_id
         ORDER BY id ASC
         LIMIT 1001'
    );
    $empty['messages'] = sr_community_privacy_fetch_limited($stmt, [
        'sender_direction_account_id' => $accountId,
        'sender_counterparty_account_id' => $accountId,
        'sender_account_id' => $accountId,
        'recipient_account_id' => $accountId,
    ], 'messages', $sectionLimits);

    $stmt = $pdo->prepare(
        'SELECT id, post_id, created_at
         FROM sr_community_scraps
         WHERE account_id = :account_id
         ORDER BY id ASC
         LIMIT 1001'
    );
    $empty['scraps'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'scraps', $sectionLimits);

    if (sr_community_series_scraps_supported($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT id, series_id, created_at
             FROM sr_community_series_scraps
             WHERE account_id = :account_id
             ORDER BY id ASC
             LIMIT 1001'
        );
        $empty['series_scraps'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'series_scraps', $sectionLimits);
    }

    if (sr_community_series_supported($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT id, board_id, title, description, status, visibility, created_at, updated_at
             FROM sr_community_series
             WHERE owner_account_id = :account_id
             ORDER BY id ASC
             LIMIT 1001'
        );
        $empty['series'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'series', $sectionLimits);

        $stmt = $pdo->prepare(
            'SELECT si.id, si.series_id, si.post_id, si.active_post_id, si.episode_label, si.item_status, si.sort_order, si.created_at, si.updated_at
             FROM sr_community_series_items si
             INNER JOIN sr_community_posts p ON p.id = si.post_id
             WHERE p.author_account_id = :account_id
             ORDER BY si.id ASC
             LIMIT 1001'
        );
        $empty['series_items'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'series_items', $sectionLimits);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT account_id, level_value, score_value, post_count, comment_count, evaluated_at, created_at, updated_at
             FROM sr_community_account_levels
             WHERE account_id = :account_id
             LIMIT 1'
        );
        $stmt->execute(['account_id' => $accountId]);
        $level = $stmt->fetch();
        $empty['level'] = is_array($level) ? $level : [];

        $stmt = $pdo->prepare(
            'SELECT id, old_level_value, new_level_value, old_score_value, new_score_value, reason_key, created_at
             FROM sr_community_level_logs
             WHERE account_id = :account_id
             ORDER BY id ASC
             LIMIT 1001'
        );
        $empty['level_logs'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'level_logs', $sectionLimits);

        if (!function_exists('sr_community_access_entitlements_table_exists')) {
            require_once SR_ROOT . '/modules/community/helpers/assets.php';
        }
        if (sr_community_access_entitlements_table_exists($pdo)) {
            $stmt = $pdo->prepare(
                'SELECT id, account_id, subject_type, subject_id, event_key, source_kind,
                        source_asset_module, source_charge_policy, source_reference, granted_at, created_at
                 FROM sr_community_access_entitlements
                 WHERE account_id = :account_id
                 ORDER BY id ASC
                 LIMIT 1001'
            );
            $empty['access_entitlements'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'access_entitlements', $sectionLimits);
        }

        $stmt = $pdo->prepare(
            'SELECT id, account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, settlement_kind, snapshot_schema_version, rounding_policy_version, group_policy_snapshot_json, created_at
             FROM sr_community_asset_logs
             WHERE account_id = :account_id
             ORDER BY id ASC
             LIMIT 1001'
        );
        $empty['asset_logs'] = sr_community_privacy_add_asset_settlement_summaries(
            sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'asset_logs', $sectionLimits)
        );

        try {
            $pdo->query('SELECT 1 FROM sr_community_asset_recovery_failures LIMIT 1');
            $stmt = $pdo->prepare(
                'SELECT id, account_id, asset_module, original_asset_log_id, original_transaction_id,
                        subject_type, subject_id, grant_event_key, reversal_event_key, operation_event_key,
                        attempted_amount, recovered_amount, unrecovered_amount, failure_reason, status,
                        actor_account_id, actor_type, operation_context_json, attempt_count,
                        created_at, updated_at, last_attempted_at, resolved_at
                 FROM sr_community_asset_recovery_failures
                 WHERE account_id = :account_id OR actor_account_id = :actor_account_id
                 ORDER BY id ASC
                 LIMIT 1001'
            );
            $empty['asset_recovery_failures'] = sr_community_privacy_fetch_limited($stmt, [
                'account_id' => $accountId,
                'actor_account_id' => $accountId,
            ], 'asset_recovery_failures', $sectionLimits);
        } catch (Throwable $exception) {
            $empty['asset_recovery_failures'] = [];
        }

        $stmt = $pdo->prepare(
            'SELECT id, charge_asset_log_id, charge_transaction_id, reward_transaction_id, reversal_transaction_id,
                    post_id, attachment_id,
                    CASE WHEN downloader_account_id = :downloader_account_id THEN downloader_account_id ELSE NULL END AS downloader_account_id,
                    CASE WHEN publisher_account_id = :publisher_account_id THEN publisher_account_id ELSE NULL END AS publisher_account_id,
                    CASE WHEN downloader_account_id = :downloader_role_account_id THEN \'downloader\' ELSE \'publisher\' END AS account_role,
                    asset_module, charge_amount, reward_rate, reward_amount, status, created_at, updated_at
             FROM sr_community_publisher_reward_logs
             WHERE downloader_account_id = :account_id OR publisher_account_id = :account_id
             ORDER BY id ASC
             LIMIT 1001'
        );
        $empty['publisher_reward_logs'] = sr_community_privacy_fetch_limited($stmt, [
            'account_id' => $accountId,
            'downloader_account_id' => $accountId,
            'publisher_account_id' => $accountId,
            'downloader_role_account_id' => $accountId,
        ], 'publisher_reward_logs', $sectionLimits);

        if (function_exists('sr_community_submission_consents_table_exists') && sr_community_submission_consents_table_exists($pdo)) {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM sr_community_submission_consents
                 WHERE account_id = :account_id
                 ORDER BY id ASC
                 LIMIT 1001'
            );
            $empty['submission_consents'] = sr_community_privacy_fetch_limited($stmt, ['account_id' => $accountId], 'submission_consents', $sectionLimits);
        }
    } catch (Throwable $exception) {
        $empty['level'] = [];
        $empty['level_logs'] = [];
        $empty['access_entitlements'] = [];
        $empty['asset_logs'] = [];
        $empty['asset_recovery_failures'] = [];
        $empty['publisher_reward_logs'] = [];
        $empty['attachment_download_logs'] = [];
        $empty['report_auto_actions'] = [];
        $empty['submission_consents'] = [];
    }

    $empty['_limits'] = $sectionLimits;

    return $empty;
};
