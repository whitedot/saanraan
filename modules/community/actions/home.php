<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_current_account($pdo);
$settings = sr_community_settings($pdo);
$config = isset($config) && is_array($config) ? $config : sr_runtime_config();
$memberSettings = sr_member_settings($pdo);
$boards = [];
foreach (sr_community_enabled_boards($pdo) as $board) {
    if (sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)) {
        $boards[] = $board;
    }
}
$latestPosts = [];
$popularPosts = [];
$latestComments = [];
$recentSeries = [];
$communitySeriesSupported = sr_community_series_supported($pdo);
$homeSidebarMenuHtml = '';
$homeExcerptAllowedByBoardId = [];
$homePostImageUrl = static function (array $post, array $board, bool $homeExcerptAllowed) use ($pdo, $settings): string {
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
};
foreach ($boards as $board) {
    $paidReadConfig = sr_community_asset_event_config($pdo, $board, $settings, 'paid_read', 'once');
    $homeExcerptAllowed = !sr_community_asset_event_required($paidReadConfig);
    $homeExcerptAllowedByBoardId[(int) ($board['id'] ?? 0)] = $homeExcerptAllowed;
    foreach (sr_community_board_posts($pdo, (int) $board['id'], 5, 0, '', 0, 'latest') as $post) {
        $post['home_excerpt_allowed'] = $homeExcerptAllowed;
        $post['home_image_url'] = $homePostImageUrl($post, $board, $homeExcerptAllowed);
        $latestPosts[] = $post;
    }
    foreach (sr_community_board_posts($pdo, (int) $board['id'], 5, 0, '', 0, 'views') as $post) {
        $post['home_excerpt_allowed'] = $homeExcerptAllowed;
        $post['home_image_url'] = $homePostImageUrl($post, $board, $homeExcerptAllowed);
        $popularPosts[] = $post;
    }
}
usort($latestPosts, static function (array $a, array $b): int {
    return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
});
$latestPosts = array_slice($latestPosts, 0, 10);
usort($popularPosts, static function (array $a, array $b): int {
    $viewCompare = (int) ($b['view_count'] ?? 0) <=> (int) ($a['view_count'] ?? 0);
    return $viewCompare !== 0 ? $viewCompare : ((int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0));
});
$popularPosts = array_slice($popularPosts, 0, 5);
$readableBoardIds = array_values(array_unique(array_map(static fn (array $board): int => (int) ($board['id'] ?? 0), $boards)));
if ($readableBoardIds !== []) {
    $boardPlaceholders = [];
    $commentParams = [];
    foreach ($readableBoardIds as $index => $boardId) {
        if ($boardId < 1) {
            continue;
        }
        $paramKey = 'board_id_' . (string) $index;
        $boardPlaceholders[] = ':' . $paramKey;
        $commentParams[$paramKey] = $boardId;
    }
    if ($boardPlaceholders !== []) {
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
             LIMIT 10'
        );
        foreach ($commentParams as $paramKey => $boardId) {
            $stmt->bindValue($paramKey, $boardId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $latestComments = $stmt->fetchAll();
    }
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
            if (sr_community_series_can_view($pdo, $series, is_array($account) ? $account : null)) {
                $recentSeries[] = $series;
            }
            if (count($recentSeries) >= 5) {
                break;
            }
        }
    }
}
$communityLayoutKey = sr_community_layout_key($settings, $site ?? null, $pdo);
$homeSidebarMenuKey = sr_community_clean_layout_menu_key((string) ($settings['layout_secondary_menu_key'] ?? ''));
if ($homeSidebarMenuKey !== '') {
    $homeSidebarMenuHtml = sr_render_output_slot($pdo, [
        'module_key' => 'core',
        'point_key' => 'site.community_home',
        'slot_key' => 'secondary_navigation',
        'menu_key' => $homeSidebarMenuKey,
    ]);
}
$layoutHomeView = sr_community_layout_home_view($communityLayoutKey, $pdo);

include $layoutHomeView;
