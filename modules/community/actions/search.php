<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_current_account($pdo);
$settings = sr_community_settings($pdo);
$keywordValue = sr_get_string_without_truncation('q', 100);
$keyword = is_string($keywordValue) ? trim(preg_replace('/\s+/', ' ', $keywordValue) ?? '') : '';
$pageValue = sr_get_string('page', 20);
$page = preg_match('/\A[1-9][0-9]*\z/', $pageValue) === 1 ? (int) $pageValue : 1;
$page = min($page, 50);
$perPage = 20;
$searchableBoardIds = [];
$searchableBoardsById = [];

foreach (sr_community_enabled_boards($pdo) as $board) {
    if (sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)) {
        $boardId = (int) $board['id'];
        $searchableBoardIds[] = $boardId;
        $searchableBoardsById[$boardId] = $board;
    }
}

$keywordLength = function_exists('mb_strlen') ? mb_strlen($keyword) : strlen($keyword);
$keywordTooShort = $keyword !== '' && $keywordLength < 2;
$posts = [];
$hasNextPage = false;
if ($keyword !== '' && !$keywordTooShort) {
    $posts = sr_community_search_posts($pdo, $searchableBoardIds, $keyword, $perPage + 1, ($page - 1) * $perPage);
    $hasNextPage = count($posts) > $perPage;
    if ($hasNextPage) {
        $posts = array_slice($posts, 0, $perPage);
    }
}
foreach ($posts as $postIndex => $post) {
    $postBoard = $searchableBoardsById[(int) ($post['board_id'] ?? 0)] ?? null;
    $paidReadRequired = false;
    if (is_array($postBoard)) {
        $paidReadConfig = sr_community_asset_event_config($pdo, $postBoard, $settings, 'paid_read', 'once');
        $paidReadRequired = sr_community_asset_event_required($paidReadConfig);
    }
    $isAuthor = is_array($account) && (int) ($post['author_account_id'] ?? 0) > 0 && (int) ($post['author_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
    $hasPaidReadSession = is_array($account) && sr_community_has_paid_read_session((int) ($account['id'] ?? 0), (int) ($post['id'] ?? 0));
    $posts[$postIndex]['search_excerpt_allowed'] = !$paidReadRequired || $isAuthor || $hasPaidReadSession;
}
$communityLayoutKey = sr_community_layout_key($settings, $site ?? null, $pdo);

include SR_ROOT . '/modules/community/views/search.php';
