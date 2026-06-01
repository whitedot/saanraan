<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    $empty = [
        'posts' => [],
        'comments' => [],
        'attachments' => [],
        'reports' => [],
        'messages' => [],
        'nickname' => [],
        'scraps' => [],
        'series' => [],
        'series_items' => [],
        'level' => [],
        'level_logs' => [],
        'access_entitlements' => [],
        'asset_logs' => [],
    ];

    if ($accountId < 1) {
        return $empty;
    }

    if (!function_exists('sr_community_categories_supported')) {
        require_once SR_ROOT . '/modules/community/helpers.php';
    }
    $categorySupported = sr_community_categories_supported($pdo);
    $categorySelectSql = $categorySupported
        ? 'p.category_id, cat.category_key, cat.title AS category_title'
        : 'NULL AS category_id, NULL AS category_key, NULL AS category_title';
    $categoryJoinSql = $categorySupported ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';
    $stmt = $pdo->prepare(
        /* M8 category export extends the legacy allowlist: SELECT id, board_id, title, body_text, body_format, status, created_at, updated_at */
        'SELECT p.id, p.board_id, ' . $categorySelectSql . ',
                p.title, p.body_text, p.body_format, p.status, p.created_at, p.updated_at
         FROM sr_community_posts p
         ' . $categoryJoinSql . '
         WHERE p.author_account_id = :account_id
         ORDER BY p.id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['posts'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, post_id, body_text, status, created_at, updated_at
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
        'SELECT nickname, created_at, updated_at
         FROM sr_community_member_nicknames
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $nickname = $stmt->fetch();
    $empty['nickname'] = is_array($nickname) ? $nickname : [];

    $stmt = $pdo->prepare(
        'SELECT id, post_id, created_at
         FROM sr_community_scraps
         WHERE account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $empty['scraps'] = $stmt->fetchAll();

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

        $stmt = $pdo->prepare(
            'SELECT id, account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, group_policy_snapshot_json, created_at
             FROM sr_community_asset_logs
             WHERE account_id = :account_id
             ORDER BY id ASC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $empty['asset_logs'] = $stmt->fetchAll();
    } catch (Throwable $exception) {
        $empty['level'] = [];
        $empty['level_logs'] = [];
        $empty['access_entitlements'] = [];
        $empty['asset_logs'] = [];
    }

    return $empty;
};
