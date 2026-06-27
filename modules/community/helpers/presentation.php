<?php

declare(strict_types=1);

function sr_community_layout_default_key(): string
{
    return 'community.basic';
}

function sr_community_layout_key(array $settings, ?array $site = null, ?PDO $pdo = null): string
{
    $layoutKey = (string) ($settings['layout_key'] ?? '');
    if ($layoutKey === '') {
        $layoutKey = sr_community_layout_default_key();
    }

    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    if ($layoutKey === sr_community_layout_default_key()) {
        return $layoutKey;
    }

    if ($pdo instanceof PDO) {
        $options = sr_public_layout_options($pdo);
        if (isset($options[$layoutKey])) {
            return $layoutKey;
        }

        $siteLayoutKey = sr_public_layout_key($site, $pdo);
        return isset($options[$siteLayoutKey]) ? $siteLayoutKey : sr_public_layout_default_key();
    }

    return preg_match('/\A[a-z0-9][a-z0-9_]{0,39}\.[a-z0-9][a-z0-9_]{0,39}\z/', $layoutKey) === 1
        ? $layoutKey
        : sr_public_layout_default_key();
}

function sr_community_layout_home_view(string $layoutKey, ?PDO $pdo = null): string
{
    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    $options = sr_public_layout_options($pdo);
    $view = (string) ($options[$layoutKey]['views']['community_home'] ?? '');
    if ($view !== '' && is_file($view)) {
        return $view;
    }

    $fallback = SR_ROOT . '/modules/community/layouts/basic/home.php';
    if (is_file($fallback)) {
        return $fallback;
    }

    throw new RuntimeException(sr_t('community::runtime.layout_home_view_missing'));
}

function sr_community_post_comment_count_html(array $post): string
{
    $count = (int) ($post['published_comment_count'] ?? 0);
    if ($count < 1) {
        return '';
    }

    return '<span class="community-post-comment-count" aria-label="' . sr_e('댓글 ' . number_format($count) . '개') . '">(' . sr_e(number_format($count)) . ')</span>';
}

function sr_community_post_reaction_count_map(PDO $pdo, array $postIds): array
{
    if (!function_exists('sr_reaction_tables_available') || !sr_reaction_tables_available($pdo)) {
        return [];
    }

    $ids = [];
    foreach ($postIds as $postId) {
        $id = (int) $postId;
        if ($id > 0) {
            $ids[(string) $id] = $id;
        }
    }
    if ($ids === []) {
        return [];
    }

    $disabledIds = [];
    if (function_exists('sr_community_post_reaction_preset_columns_exist') && sr_community_post_reaction_preset_columns_exist($pdo) && function_exists('sr_reaction_disabled_preset_key')) {
        $disabledPlaceholders = [];
        $disabledParams = ['disabled_key' => sr_reaction_disabled_preset_key()];
        foreach (array_values($ids) as $index => $id) {
            $paramKey = 'disabled_post_id_' . (string) $index;
            $disabledPlaceholders[] = ':' . $paramKey;
            $disabledParams[$paramKey] = $id;
        }
        $stmt = $pdo->prepare(
            'SELECT id
             FROM sr_community_posts
             WHERE id IN (' . implode(', ', $disabledPlaceholders) . ')
               AND reaction_preset_key = :disabled_key'
        );
        $stmt->execute($disabledParams);
        foreach ($stmt->fetchAll() as $row) {
            $disabledId = (int) ($row['id'] ?? 0);
            if ($disabledId > 0) {
                $disabledIds[$disabledId] = true;
                unset($ids[(string) $disabledId]);
            }
        }
        if ($ids === []) {
            return [];
        }
    }

    $placeholders = [];
    $params = [
        'target_module' => 'community',
        'target_type' => 'post',
    ];
    foreach (array_values($ids) as $index => $id) {
        $paramKey = 'target_id_' . (string) $index;
        $placeholders[] = ':' . $paramKey;
        $params[$paramKey] = (string) $id;
    }

    $stmt = $pdo->prepare(
        'SELECT target_id, COUNT(*) AS count_value
         FROM sr_reaction_records
         WHERE target_module = :target_module
           AND target_type = :target_type
           AND target_id IN (' . implode(', ', $placeholders) . ')
         GROUP BY target_id'
    );
    $stmt->execute($params);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) ($row['target_id'] ?? 0);
        if ($id > 0 && empty($disabledIds[$id])) {
            $counts[$id] = (int) ($row['count_value'] ?? 0);
        }
    }

    return $counts;
}

function sr_community_home_member_summary(PDO $pdo, ?array $account, array $settings, ?array $memberSettings = null): ?array
{
    if (!is_array($account) || (int) ($account['id'] ?? 0) < 1) {
        return null;
    }

    $accountId = (int) $account['id'];
    $memberSettings = is_array($memberSettings) ? $memberSettings : sr_member_settings($pdo);
    $profile = sr_member_profile($pdo, $accountId);
    $displayName = sr_community_public_display_name(array_merge($account, [
        'community_nickname' => sr_community_member_nickname($pdo, $accountId),
    ]), $memberSettings);
    if ($displayName === '') {
        $displayName = (string) ($account['display_name'] ?? '내 계정');
    }
    $initial = $displayName !== ''
        ? (function_exists('mb_substr') ? mb_substr($displayName, 0, 1) : substr($displayName, 0, 1))
        : 'M';

    $levelSnapshot = !empty($settings['level_enabled'])
        ? sr_community_maybe_recalculate_account_level($pdo, $accountId, $settings, 'home_profile_view')
        : sr_community_account_level_snapshot($pdo, $accountId);
    $levelValue = sr_community_normalize_level_value((int) ($levelSnapshot['level_value'] ?? 0), $settings);
    $scoreValue = max(0, (int) ($levelSnapshot['score_value'] ?? 0));
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_community_posts
         WHERE author_account_id = :account_id
           AND status = 'published'"
    );
    $stmt->execute(['account_id' => $accountId]);
    $postCountRow = $stmt->fetch();
    $postCount = is_array($postCountRow) ? max(0, (int) ($postCountRow['count_value'] ?? 0)) : 0;

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS count_value
         FROM sr_community_comments
         WHERE author_account_id = :account_id
           AND status = 'published'"
    );
    $stmt->execute(['account_id' => $accountId]);
    $commentCountRow = $stmt->fetch();
    $commentCount = is_array($commentCountRow) ? max(0, (int) ($commentCountRow['count_value'] ?? 0)) : 0;
    $nextLevelValue = $levelValue < sr_community_max_level_value($settings) ? $levelValue + 1 : 0;
    $currentLevelMinScore = 0;
    $nextLevelMinScore = 0;
    foreach (sr_community_enabled_levels($pdo, $settings) as $level) {
        $definitionLevelValue = (int) ($level['level_value'] ?? 0);
        if ($definitionLevelValue === $levelValue) {
            $currentLevelMinScore = max(0, (int) ($level['min_score'] ?? 0));
        }
        if ($nextLevelValue > 0 && $definitionLevelValue === $nextLevelValue) {
            $nextLevelMinScore = max(0, (int) ($level['min_score'] ?? 0));
        }
    }
    $nextLevelRemaining = $nextLevelMinScore > $scoreValue ? $nextLevelMinScore - $scoreValue : 0;
    $levelScoreRange = max(1, $nextLevelMinScore - $currentLevelMinScore);
    $progressPercent = $nextLevelMinScore > 0 ? min(100, max(0, (int) floor((($scoreValue - $currentLevelMinScore) / $levelScoreRange) * 100))) : 100;
    $avatarSrc = sr_member_avatar_src((string) ($profile['avatar_path'] ?? ''));
    $config = sr_runtime_config();

    return [
        'display_name' => $displayName,
        'initial' => $initial,
        'avatar_src' => $avatarSrc,
        'avatar_color_class' => sr_member_default_avatar_color_class(sr_member_public_account_hash($config, $accountId)),
        'level_value' => $levelValue,
        'level_label' => sr_community_level_label_for_value($pdo, $levelValue, $settings, true),
        'score_value' => $scoreValue,
        'post_count' => $postCount,
        'comment_count' => $commentCount,
        'next_level_value' => $nextLevelValue,
        'next_level_min_score' => $nextLevelMinScore,
        'next_level_remaining' => $nextLevelRemaining,
        'progress_percent' => $progressPercent,
        'level_enabled' => !empty($settings['level_enabled']),
    ];
}

function sr_community_home_post_image_url(PDO $pdo, array $post, array $board, array $settings, bool $homeExcerptAllowed): string
{
    if (!$homeExcerptAllowed || (int) ($post['is_secret'] ?? 0) === 1) {
        return '';
    }

    $attachmentUrl = sr_community_post_list_thumbnail_url($pdo, $post, $board, $settings);
    if ($attachmentUrl !== '') {
        return $attachmentUrl;
    }

    $postId = (int) ($post['id'] ?? 0);
    if ($postId < 1 || (string) ($post['body_format'] ?? 'plain') !== 'html') {
        return '';
    }

    foreach (sr_community_body_file_refs_from_html((string) ($post['body_text'] ?? '')) as $ref) {
        if ((string) ($ref['type'] ?? '') === 'post' && (int) ($ref['post_id'] ?? 0) === $postId) {
            return sr_community_body_file_post_proxy_url($postId, (string) ($ref['file'] ?? ''));
        }
    }

    return '';
}

function sr_community_home_post_feed_from_rows(PDO $pdo, array $rows, array $boardById, array $settings, array $homeExcerptAllowedByBoardId): array
{
    $posts = [];
    foreach ($rows as $post) {
        if (!is_array($post)) {
            continue;
        }
        $boardId = (int) ($post['board_id'] ?? 0);
        if (!isset($boardById[$boardId])) {
            continue;
        }

        $homeExcerptAllowed = !empty($homeExcerptAllowedByBoardId[$boardId]);
        $post['home_excerpt_allowed'] = $homeExcerptAllowed;
        $post['home_image_url'] = sr_community_home_post_image_url($pdo, $post, $boardById[$boardId], $settings, $homeExcerptAllowed);
        if ($homeExcerptAllowed && !array_key_exists('home_excerpt', $post)) {
            $post['home_excerpt'] = !empty($post['is_secret'])
                ? ''
                : sr_community_body_excerpt((string) ($post['body_text'] ?? ''), (string) ($post['body_format'] ?? 'plain'), 160);
        }
        $posts[] = $post;
    }

    return $posts;
}

function sr_community_home_post_feed_live_rows(PDO $pdo, array $boardIds, int $limit, string $sort): array
{
    $limit = max(1, min(20, $limit));
    [$sql, $params] = sr_community_feed_cache_post_feed_query($pdo, array_values($boardIds), $limit, $sort, 'home_board_id_');
    if ($sql === '') {
        return [];
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $value) {
        $stmt->bindValue($paramKey, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_home_post_feed_rows_by_snapshots(PDO $pdo, array $snapshots, array $boardById): array
{
    unset($pdo);

    $rows = [];
    foreach ($snapshots as $snapshot) {
        if (!is_array($snapshot)) {
            continue;
        }
        $postId = (int) ($snapshot['post_id'] ?? 0);
        $boardId = (int) ($snapshot['board_id'] ?? 0);
        if ($postId < 1 || !isset($boardById[$boardId])) {
            continue;
        }

        $thumbnailSource = isset($snapshot['thumbnail_source']) && is_array($snapshot['thumbnail_source'])
            ? $snapshot['thumbnail_source']
            : [];
        $rows[] = [
            'id' => $postId,
            'board_id' => $boardId,
            'category_id' => null,
            'category_key' => null,
            'category_title' => null,
            'category_status' => null,
            'author_account_id' => max(0, (int) ($snapshot['author_account_id'] ?? 0)),
            'author_public_name_snapshot' => '',
            'author_account_status' => '',
            'guest_author_name' => '',
            'guest_password_hash' => null,
            'guest_ip_hash' => null,
            'guest_user_agent_hash' => null,
            'title' => (string) ($snapshot['title'] ?? ''),
            'body_text' => '',
            'body_format' => 'plain',
            'is_secret' => !empty($snapshot['is_secret']) ? 1 : 0,
            'status' => 'published',
            'view_count' => max(0, (int) ($snapshot['view_count'] ?? 0)),
            'last_commented_at' => null,
            'created_at' => (string) ($snapshot['created_at'] ?? ''),
            'updated_at' => (string) ($snapshot['updated_at'] ?? ''),
            'published_comment_count' => max(0, (int) ($snapshot['comment_count'] ?? 0)),
            'active_attachment_count' => empty($thumbnailSource) ? 0 : 1,
            'list_image_attachment_id' => max(0, (int) ($thumbnailSource['attachment_id'] ?? 0)),
            'list_image_storage_driver' => (string) ($thumbnailSource['storage_driver'] ?? 'local'),
            'list_image_storage_key' => (string) ($thumbnailSource['storage_key'] ?? ''),
            'list_image_mime_type' => (string) ($thumbnailSource['mime_type'] ?? ''),
            'list_image_size_bytes' => max(0, (int) ($thumbnailSource['size_bytes'] ?? 0)),
            'list_image_checksum_sha256' => (string) ($thumbnailSource['checksum_sha256'] ?? ''),
            'list_image_width' => max(0, (int) ($thumbnailSource['width'] ?? 0)),
            'list_image_height' => max(0, (int) ($thumbnailSource['height'] ?? 0)),
            'home_excerpt' => (string) ($snapshot['excerpt'] ?? ''),
        ];
    }

    return $rows;
}

function sr_community_home_post_feed(PDO $pdo, array $boards, array $settings, array $homeExcerptAllowedByBoardId, int $limit, string $sort): array
{
    $boardById = [];
    $boardIds = [];
    foreach ($boards as $board) {
        $boardId = (int) ($board['id'] ?? 0);
        if ($boardId < 1) {
            continue;
        }

        $boardById[$boardId] = $board;
        $boardIds[$boardId] = $boardId;
    }
    if ($boardIds === []) {
        return [];
    }

    $limit = max(1, min(20, $limit));
    $cacheContext = sr_community_feed_cache_context_for_home($boards, $homeExcerptAllowedByBoardId, $limit, $sort);
    if ($cacheContext !== []) {
        $cachedSnapshots = sr_community_feed_cache_read($pdo, $cacheContext);
        if (is_array($cachedSnapshots)) {
            return sr_community_home_post_feed_from_rows(
                $pdo,
                sr_community_home_post_feed_rows_by_snapshots($pdo, $cachedSnapshots, $boardById),
                $boardById,
                $settings,
                $homeExcerptAllowedByBoardId
            );
        }
    }

    $rows = sr_community_home_post_feed_live_rows($pdo, array_values($boardIds), $limit, $sort);
    $posts = sr_community_home_post_feed_from_rows($pdo, $rows, $boardById, $settings, $homeExcerptAllowedByBoardId);
    if ($cacheContext !== []) {
        sr_community_feed_cache_write($pdo, $cacheContext, $posts);
    }

    return $posts;
}

function sr_community_home_latest_comment_snapshots_from_rows(array $rows, array $homeExcerptAllowedByBoardId): array
{
    $snapshots = [];
    foreach ($rows as $comment) {
        if (!is_array($comment)) {
            continue;
        }

        $boardId = (int) ($comment['board_id'] ?? 0);
        $excerptAllowed = empty($comment['is_secret'])
            && empty($comment['post_is_secret'])
            && !empty($homeExcerptAllowedByBoardId[$boardId]);
        $snapshots[] = [
            'snapshot_schema_version' => 'community_home_comment_snapshot_v1',
            'id' => max(0, (int) ($comment['id'] ?? 0)),
            'post_id' => max(0, (int) ($comment['post_id'] ?? 0)),
            'board_id' => max(0, $boardId),
            'author_account_id' => max(0, (int) ($comment['author_account_id'] ?? 0)),
            'author_display_name' => sr_clean_single_line((string) ($comment['author_public_name_snapshot'] ?? ''), 120),
            'guest_author_name' => sr_clean_single_line((string) ($comment['guest_author_name'] ?? ''), 120),
            'author_account_status' => sr_clean_single_line((string) ($comment['author_account_status'] ?? ''), 30),
            'body_excerpt' => $excerptAllowed ? sr_community_body_excerpt((string) ($comment['body_text'] ?? ''), 'plain', 50) : '',
            'post_title' => sr_clean_single_line((string) ($comment['post_title'] ?? ''), 160),
            'is_secret' => !empty($comment['is_secret']) ? 1 : 0,
            'post_is_secret' => !empty($comment['post_is_secret']) ? 1 : 0,
            'created_at' => sr_clean_single_line((string) ($comment['created_at'] ?? ''), 40),
            'updated_at' => sr_clean_single_line((string) ($comment['updated_at'] ?? ''), 40),
        ];
    }

    return $snapshots;
}

function sr_community_home_latest_comment_rows_from_snapshots(array $snapshots): array
{
    $rows = [];
    foreach ($snapshots as $snapshot) {
        if (!is_array($snapshot)) {
            continue;
        }

        $commentId = (int) ($snapshot['id'] ?? 0);
        $postId = (int) ($snapshot['post_id'] ?? 0);
        $boardId = (int) ($snapshot['board_id'] ?? 0);
        if ($commentId < 1 || $postId < 1 || $boardId < 1) {
            continue;
        }

        $rows[] = [
            'id' => $commentId,
            'post_id' => $postId,
            'board_id' => $boardId,
            'author_account_id' => max(0, (int) ($snapshot['author_account_id'] ?? 0)),
            'author_display_name' => (string) ($snapshot['author_display_name'] ?? ''),
            'guest_author_name' => (string) ($snapshot['guest_author_name'] ?? ''),
            'guest_password_hash' => null,
            'guest_ip_hash' => null,
            'guest_user_agent_hash' => null,
            'author_account_status' => (string) ($snapshot['author_account_status'] ?? ''),
            'body_text' => (string) ($snapshot['body_excerpt'] ?? ''),
            'is_secret' => !empty($snapshot['is_secret']) ? 1 : 0,
            'created_at' => (string) ($snapshot['created_at'] ?? ''),
            'updated_at' => (string) ($snapshot['updated_at'] ?? ''),
            'post_title' => (string) ($snapshot['post_title'] ?? ''),
            'post_author_account_id' => 0,
            'post_is_secret' => !empty($snapshot['post_is_secret']) ? 1 : 0,
        ];
    }

    return $rows;
}

function sr_community_home_latest_comment_live_rows(PDO $pdo, array $readableBoardIds, int $limit = 10): array
{
    $ids = [];
    foreach ($readableBoardIds as $boardId) {
        $id = (int) $boardId;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    ksort($ids, SORT_NUMERIC);
    if ($ids === []) {
        return [];
    }

    $boardPlaceholders = [];
    $commentParams = [];
    foreach (array_values($ids) as $index => $boardId) {
        $paramKey = 'board_id_' . (string) $index;
        $boardPlaceholders[] = ':' . $paramKey;
        $commentParams[$paramKey] = $boardId;
    }
    $commentParams['limit_value'] = max(1, min(20, $limit));

    $secretCommentSelectSql = sr_community_comment_secret_column_exists($pdo) ? 'c.is_secret,' : '0 AS is_secret,';
    $secretPostSelectSql = sr_community_post_secret_column_exists($pdo) ? 'p.is_secret AS post_is_secret,' : '0 AS post_is_secret,';
    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_comments', 'c');
    $stmt = $pdo->prepare(
        'SELECT c.id, c.post_id, c.author_account_id, ' . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_comments', 'c') . ', author.status AS author_account_status, c.body_text, ' . $secretCommentSelectSql . ' c.created_at, c.updated_at,
                p.title AS post_title, p.author_account_id AS post_author_account_id, ' . $secretPostSelectSql . '
                b.id AS board_id, b.board_key, b.title AS board_title
         FROM sr_community_comments c
         INNER JOIN sr_community_posts p ON p.id = c.post_id
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         LEFT JOIN sr_member_accounts author ON author.id = c.author_account_id
         WHERE c.status = \'published\'
           AND p.status = \'published\'
           AND b.status = \'enabled\'
           AND b.id IN (' . implode(', ', $boardPlaceholders) . ')
         ORDER BY c.id DESC
         LIMIT :limit_value'
    );
    foreach ($commentParams as $paramKey => $value) {
        $stmt->bindValue($paramKey, $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_home_latest_comments(PDO $pdo, array $readableBoardIds, array $homeExcerptAllowedByBoardId, int $limit = 10, bool $usePublicCache = false): array
{
    $context = sr_community_feed_cache_context([
        'feed_key' => 'community.home.latest_comments',
        'board_ids' => $readableBoardIds,
        'sort' => 'activity',
        'display_count' => $limit,
        'fetch_count' => $limit,
        'locale' => 'ko',
        'policy_version' => 'v1',
    ]);

    if ($usePublicCache) {
        $cachedSnapshots = sr_community_feed_cache_read($pdo, $context, ['community_home_comment_snapshot_v1']);
        if (is_array($cachedSnapshots)) {
            return sr_community_home_latest_comment_rows_from_snapshots($cachedSnapshots);
        }
    }

    $rows = sr_community_home_latest_comment_live_rows($pdo, $readableBoardIds, $limit);
    if ($usePublicCache) {
        sr_community_feed_cache_write_snapshots(
            $pdo,
            $context,
            sr_community_home_latest_comment_snapshots_from_rows($rows, $homeExcerptAllowedByBoardId)
        );
    }

    return $rows;
}

function sr_community_home_chrome_data(PDO $pdo, ?array $account, array $settings, ?array $site = null, ?array $memberSettings = null): array
{
    $boards = [];
    foreach (sr_community_enabled_boards($pdo) as $board) {
        if (sr_community_account_can_read_board($pdo, $board, $account)) {
            $boards[] = $board;
        }
    }

    $latestPosts = [];
    $popularPosts = [];
    $popularPostReactionCounts = [];
    $latestComments = [];
    $recentSeries = [];
    $homeSidebarMenuHtml = '';
    $homeMemberSummary = sr_community_home_member_summary($pdo, $account, $settings, $memberSettings);
    $homeExcerptAllowedByBoardId = [];
    $communitySeriesSupported = sr_community_series_supported($pdo);
    foreach ($boards as $board) {
        $paidReadConfig = sr_community_asset_event_config($pdo, $board, $settings, 'paid_read', 'once');
        $homeExcerptAllowed = !sr_community_asset_event_required($paidReadConfig);
        $homeExcerptAllowedByBoardId[(int) ($board['id'] ?? 0)] = $homeExcerptAllowed;
    }
    $latestPosts = sr_community_home_post_feed($pdo, $boards, $settings, $homeExcerptAllowedByBoardId, 10, 'latest');
    $popularPosts = sr_community_home_post_feed($pdo, $boards, $settings, $homeExcerptAllowedByBoardId, 5, 'views');
    $readableBoardIds = array_values(array_unique(array_map(static fn (array $board): int => (int) ($board['id'] ?? 0), $boards)));
    if (!empty($settings['reaction_enabled']) && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
        require_once SR_ROOT . '/modules/reaction/helpers.php';
        $popularPostReactionCounts = sr_community_post_reaction_count_map($pdo, array_map(static fn (array $post): int => (int) ($post['id'] ?? 0), $popularPosts));
    }
    if ($readableBoardIds !== []) {
        $latestComments = sr_community_home_latest_comments($pdo, $readableBoardIds, $homeExcerptAllowedByBoardId, 10, !is_array($account));
    }
    if ($communitySeriesSupported && $readableBoardIds !== []) {
        $seriesPlaceholders = [];
        $seriesParams = [];
        foreach ($readableBoardIds as $index => $boardId) {
            if ($boardId < 1) {
                continue;
            }
            $paramKey = 'series_board_id_' . (string) $index;
            $seriesPlaceholders[] = ':' . $paramKey;
            $seriesParams[$paramKey] = $boardId;
        }
        if ($seriesPlaceholders !== []) {
            $stmt = $pdo->prepare(
                'SELECT s.*, b.title AS board_title,
                        (SELECT COUNT(*) FROM sr_community_series_items si WHERE si.series_id = s.id AND si.item_status = \'active\') AS active_item_count,
                        (SELECT si.post_id
                         FROM sr_community_series_items si
                         INNER JOIN sr_community_posts p ON p.id = si.post_id
                         WHERE si.series_id = s.id
                           AND si.item_status = \'active\'
                           AND p.status = \'published\'
                         ORDER BY si.sort_order ASC, si.id ASC
                         LIMIT 1) AS first_post_id
                 FROM sr_community_series s
                 INNER JOIN sr_community_boards b ON b.id = s.board_id
                 WHERE s.status = \'active\'
                   AND b.status = \'enabled\'
                   AND s.board_id IN (' . implode(', ', $seriesPlaceholders) . ')
                 ORDER BY s.updated_at DESC, s.id DESC
                 LIMIT 20'
            );
            foreach ($seriesParams as $paramKey => $boardId) {
                $stmt->bindValue($paramKey, $boardId, PDO::PARAM_INT);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll() as $series) {
                if (sr_community_series_can_view($pdo, $series, $account)) {
                    $recentSeries[] = $series;
                }
                if (count($recentSeries) >= 5) {
                    break;
                }
            }
        }
    }
    $communityLayoutKey = sr_community_layout_key($settings, $site, $pdo);
    $homeSidebarMenuKey = sr_community_clean_layout_menu_key((string) ($settings['layout_secondary_menu_key'] ?? ''));
    if ($homeSidebarMenuKey === 'sr_community_board_groups' && function_exists('sr_community_layout_menu_html')) {
        $homeSidebarMenuHtml = sr_community_layout_menu_html($pdo, $homeSidebarMenuKey, 'secondary_navigation');
    } elseif ($homeSidebarMenuKey !== '') {
        $homeSidebarMenuHtml = sr_render_output_slot($pdo, [
            'module_key' => 'core',
            'point_key' => 'site.community_home',
            'slot_key' => 'secondary_navigation',
            'menu_key' => $homeSidebarMenuKey,
        ]);
    }

    return [
        'boards' => $boards,
        'latestPosts' => $latestPosts,
        'popularPosts' => $popularPosts,
        'popularPostReactionCounts' => $popularPostReactionCounts,
        'latestComments' => $latestComments,
        'recentSeries' => $recentSeries,
        'communitySeriesSupported' => $communitySeriesSupported,
        'homeSidebarMenuHtml' => $homeSidebarMenuHtml,
        'homeMemberSummary' => $homeMemberSummary,
        'homeExcerptAllowedByBoardId' => $homeExcerptAllowedByBoardId,
        'communityLayoutKey' => $communityLayoutKey,
    ];
}

function sr_community_skin_key(array $boardSettings = []): string
{
    $skinKey = (string) ($boardSettings['skin_key'] ?? 'basic');
    return isset(sr_community_skin_options()[$skinKey]) ? $skinKey : 'basic';
}

function sr_community_skin_files(): array
{
    return [
        'basic' => SR_ROOT . '/modules/community/skins/basic/skin.php',
    ];
}

function sr_community_skin_options(): array
{
    $options = [];
    foreach (sr_community_skin_files() as $skinKey => $file) {
        $definition = sr_community_skin_definition((string) $skinKey);
        if (!sr_community_skin_definition_is_valid((string) $skinKey, $definition)) {
            continue;
        }

        $options[(string) $skinKey] = $definition;
    }

    return $options;
}

function sr_community_board_skin_key(PDO $pdo, array $board): string
{
    $boardId = isset($board['board_id']) ? (int) $board['board_id'] : (int) ($board['id'] ?? 0);
    $skinKey = $boardId > 0 ? sr_community_board_setting_value($pdo, $boardId, 'skin_key') : null;
    return sr_community_skin_key(['skin_key' => is_string($skinKey) ? $skinKey : 'basic']);
}

function sr_community_skin_view(string $skinKey, string $viewKey): string
{
    $options = sr_community_skin_options();
    $view = (string) ($options[$skinKey]['views'][$viewKey] ?? $options['basic']['views'][$viewKey] ?? '');

    if (is_file($view)) {
        return $view;
    }

    $fallback = (string) ($options['basic']['views'][$viewKey] ?? '');
    if (is_file($fallback)) {
        return $fallback;
    }

    throw new RuntimeException(sr_t('community::runtime.skin_view_missing'));
}

function sr_community_skin_definition(string $skinKey): array
{
    $files = sr_community_skin_files();
    $file = (string) ($files[$skinKey] ?? '');
    if ($file === '' || !is_file($file)) {
        return [];
    }

    $definition = include $file;
    if (!is_array($definition)) {
        return [];
    }

    $definition['skin_key'] = $skinKey;
    $definition['skin_dir'] = dirname($file);

    return $definition;
}

function sr_community_skin_definition_is_valid(string $skinKey, array $definition): bool
{
    if ($skinKey === '' || $definition === []) {
        return false;
    }

    $skinDir = (string) ($definition['skin_dir'] ?? '');
    $views = isset($definition['views']) && is_array($definition['views']) ? $definition['views'] : [];
    foreach (sr_community_required_skin_view_keys() as $viewKey) {
        $view = (string) ($views[$viewKey] ?? '');
        if (!sr_community_skin_file_is_inside($view, $skinDir)) {
            error_log('[saanraan] community skin required view is missing or outside skin dir: skin=' . $skinKey . ' view=' . $viewKey);
            return false;
        }
    }

    $actions = isset($definition['actions']) && is_array($definition['actions']) ? $definition['actions'] : [];
    foreach ($actions as $actionKey => $action) {
        if (!is_string($actionKey) || preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $actionKey) !== 1 || !is_array($action)) {
            error_log('[saanraan] community skin action key is invalid: skin=' . $skinKey);
            return false;
        }

        $method = strtoupper((string) ($action['method'] ?? 'POST'));
        $file = (string) ($action['file'] ?? '');
        if (!in_array($method, ['POST'], true) || !sr_community_skin_file_is_inside($file, $skinDir)) {
            error_log('[saanraan] community skin action is invalid: skin=' . $skinKey . ' action=' . $actionKey);
            return false;
        }
    }

    return true;
}

function sr_community_required_skin_view_keys(): array
{
    return ['list', 'post', 'form'];
}

function sr_community_skin_file_is_inside(string $file, string $skinDir): bool
{
    if ($file === '' || $skinDir === '') {
        return false;
    }

    $realFile = realpath($file);
    $realDir = realpath($skinDir);

    return is_string($realFile)
        && is_string($realDir)
        && is_file($realFile)
        && str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR);
}

function sr_community_skin_stylesheets(string $skinKey): array
{
    $options = sr_community_skin_options();
    $stylesheets = $options[$skinKey]['stylesheets'] ?? [];
    if (!is_array($stylesheets)) {
        return [];
    }

    $valid = [];
    foreach ($stylesheets as $stylesheet) {
        if (is_string($stylesheet) && sr_is_safe_relative_url($stylesheet)) {
            $valid[] = $stylesheet;
        }
    }

    return $valid;
}

function sr_community_skin_action(string $skinKey, string $actionKey, string $method): ?array
{
    $options = sr_community_skin_options();
    $actions = isset($options[$skinKey]['actions']) && is_array($options[$skinKey]['actions']) ? $options[$skinKey]['actions'] : [];
    $action = isset($actions[$actionKey]) && is_array($actions[$actionKey]) ? $actions[$actionKey] : null;
    if ($action === null) {
        return null;
    }

    $expectedMethod = strtoupper((string) ($action['method'] ?? 'POST'));
    if ($expectedMethod !== strtoupper($method)) {
        return null;
    }

    return $action;
}
