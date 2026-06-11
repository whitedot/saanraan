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

return static function (PDO $pdo, int $accountId): array {
    $empty = [
        'posts' => [],
        'comments' => [],
        'attachments' => [],
        'reports' => [],
        'messages' => [],
        'scraps' => [],
        'series_scraps' => [],
        'series' => [],
        'series_items' => [],
        'level' => [],
        'level_logs' => [],
        'access_entitlements' => [],
        'asset_logs' => [],
        'publisher_reward_logs' => [],
        'submission_consents' => [],
    ];

    if ($accountId < 1) {
        return $empty;
    }

    require_once SR_ROOT . '/modules/community/helpers.php';

    $categorySupported = sr_community_categories_supported($pdo);
    $categorySelectSql = $categorySupported
        ? 'p.category_id, cat.category_key, cat.title AS category_title'
        : 'NULL AS category_id, NULL AS category_key, NULL AS category_title';
    $categoryJoinSql = $categorySupported ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';
    $postSnapshotSelectSql = sr_community_author_public_name_snapshot_column_exists($pdo, 'sr_community_posts')
        ? 'p.author_public_name_snapshot'
        : "'' AS author_public_name_snapshot";
    $commentSnapshotSelectSql = sr_community_author_public_name_snapshot_column_exists($pdo, 'sr_community_comments')
        ? 'author_public_name_snapshot'
        : "'' AS author_public_name_snapshot";
    $commentSecretSelectSql = sr_community_comment_secret_column_exists($pdo)
        ? 'is_secret'
        : '0 AS is_secret';
    $commentThreadSelectSql = function_exists('sr_community_comment_thread_columns_exist') && sr_community_comment_thread_columns_exist($pdo)
        ? 'parent_comment_id, thread_root_id, depth,'
        : 'NULL AS parent_comment_id, id AS thread_root_id, 1 AS depth,';
    $stmt = $pdo->prepare(
        /* M8 category export extends the legacy allowlist: SELECT id, board_id, title, body_text, body_format, status, created_at, updated_at */
        'SELECT p.id, p.board_id, ' . $categorySelectSql . ',
                p.title, ' . $postSnapshotSelectSql . ', p.body_text, p.body_format, p.status, p.created_at, p.updated_at
         FROM sr_community_posts p
         ' . $categoryJoinSql . '
         WHERE p.author_account_id = :account_id
         ORDER BY p.id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['posts'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        /* Legacy comment export allowlist: SELECT id, post_id, body_text, status, created_at, updated_at */
        'SELECT id, post_id, ' . $commentThreadSelectSql . ' body_text, ' . $commentSecretSelectSql . ', status, created_at, updated_at, ' . $commentSnapshotSelectSql . '
         FROM sr_community_comments
         WHERE author_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['comments'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, post_id, original_name, mime_type, size_bytes, width, height, status, created_at
         FROM sr_community_attachments
         WHERE uploader_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['attachments'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, target_type, target_id,
                CASE WHEN reported_account_id = :reported_account_id THEN reported_account_id ELSE NULL END AS reported_account_id,
                CASE WHEN reported_account_id IS NULL THEN \'none\' WHEN reported_account_id = :reported_role_account_id THEN \'self\' ELSE \'masked_counterparty\' END AS reported_account_role,
                reason_key, memo_text, status, created_at, updated_at
         FROM sr_community_reports
         WHERE reporter_account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'reported_account_id' => $accountId,
        'reported_role_account_id' => $accountId,
    ]);
    $empty['reports'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id,
                CASE WHEN sender_account_id = :sender_direction_account_id THEN \'sent\' ELSE \'received\' END AS message_direction,
                CASE WHEN sender_account_id = :sender_counterparty_account_id THEN \'masked_recipient\' ELSE \'masked_sender\' END AS counterparty_role,
                body_text, status, read_at, sender_deleted_at, recipient_deleted_at, created_at, updated_at
         FROM sr_community_messages
         WHERE sender_account_id = :sender_account_id OR recipient_account_id = :recipient_account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute([
        'sender_direction_account_id' => $accountId,
        'sender_counterparty_account_id' => $accountId,
        'sender_account_id' => $accountId,
        'recipient_account_id' => $accountId,
    ]);
    $empty['messages'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, post_id, created_at
         FROM sr_community_scraps
         WHERE account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['scraps'] = $stmt->fetchAll();

    if (sr_community_series_scraps_supported($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT id, series_id, created_at
             FROM sr_community_series_scraps
             WHERE account_id = :account_id
             ORDER BY id ASC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $empty['series_scraps'] = $stmt->fetchAll();
    }

    if (sr_community_series_supported($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT id, board_id, title, description, status, visibility, created_at, updated_at
             FROM sr_community_series
             WHERE owner_account_id = :account_id
             ORDER BY id ASC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $empty['series'] = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT si.id, si.series_id, si.post_id, si.active_post_id, si.episode_label, si.item_status, si.sort_order, si.created_at, si.updated_at
             FROM sr_community_series_items si
             INNER JOIN sr_community_posts p ON p.id = si.post_id
             WHERE p.author_account_id = :account_id
             ORDER BY si.id ASC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $empty['series_items'] = $stmt->fetchAll();
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
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $empty['level_logs'] = $stmt->fetchAll();

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
                 LIMIT 1000'
            );
            $stmt->execute(['account_id' => $accountId]);
            $empty['access_entitlements'] = $stmt->fetchAll();
        }

        $assetLogSettlementMetadataSelect = sr_community_asset_log_settlement_metadata_columns_exist($pdo)
            ? 'settlement_kind, snapshot_schema_version, rounding_policy_version'
            : '\'legacy_unknown\' AS settlement_kind, \'asset_settlement_snapshot_v1\' AS snapshot_schema_version, \'asset_settlement_rounding_v1\' AS rounding_policy_version';
        $stmt = $pdo->prepare(
            'SELECT id, account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, ' . $assetLogSettlementMetadataSelect . ', group_policy_snapshot_json, created_at
             FROM sr_community_asset_logs
             WHERE account_id = :account_id
             ORDER BY id ASC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $empty['asset_logs'] = sr_community_privacy_add_asset_settlement_summaries($stmt->fetchAll());

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
             LIMIT 1000'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'downloader_account_id' => $accountId,
            'publisher_account_id' => $accountId,
            'downloader_role_account_id' => $accountId,
        ]);
        $empty['publisher_reward_logs'] = $stmt->fetchAll();

        if (function_exists('sr_community_submission_consents_table_exists') && sr_community_submission_consents_table_exists($pdo)) {
            $stmt = $pdo->prepare(
                'SELECT id, board_id, subject_type, subject_id, action_key, account_id,
                        consent_title_snapshot, consent_body_snapshot, consent_version_snapshot,
                        consent_required, consent_accepted, ip_hash, user_agent_hash, created_at
                 FROM sr_community_submission_consents
                 WHERE account_id = :account_id
                 ORDER BY id ASC
                 LIMIT 1000'
            );
            $stmt->execute(['account_id' => $accountId]);
            $empty['submission_consents'] = $stmt->fetchAll();
        }
    } catch (Throwable $exception) {
        $empty['level'] = [];
        $empty['level_logs'] = [];
        $empty['access_entitlements'] = [];
        $empty['asset_logs'] = [];
        $empty['publisher_reward_logs'] = [];
        $empty['submission_consents'] = [];
    }

    return $empty;
};
