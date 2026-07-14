<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$settings = sr_community_settings($pdo);
$config = isset($config) && is_array($config) ? $config : sr_runtime_config();
$memberSettings = sr_member_settings($pdo);
$myType = sr_get_string('type', 20);
if (!in_array($myType, ['posts', 'comments'], true)) {
    $myType = 'posts';
}
$myPerPage = 20;
$myPageValue = sr_get_string('page', 20);
$myPage = preg_match('/\A[1-9][0-9]*\z/', $myPageValue) === 1 ? (int) $myPageValue : 1;
$myPage = min($myPage, intdiv(PHP_INT_MAX, $myPerPage));
$myOffset = ($myPage - 1) * $myPerPage;
$myReadableBoards = [];
foreach (sr_community_enabled_boards($pdo) as $board) {
    if (sr_community_account_can_read_board($pdo, $board, $account)) {
        $myReadableBoards[(int) ($board['id'] ?? 0)] = $board;
    }
}
$myReadableBoardIds = array_values(array_filter(array_keys($myReadableBoards), static fn (int $boardId): bool => $boardId > 0));
$myPosts = [];
$myComments = [];
$myHasNextPage = false;
$myNotice = isset($_SESSION['sr_community_post_notice']) && is_string($_SESSION['sr_community_post_notice'])
    ? $_SESSION['sr_community_post_notice']
    : '';
unset($_SESSION['sr_community_post_notice']);

if ($myReadableBoardIds !== []) {
    $myBoardPlaceholders = [];
    $myBoardParams = ['account_id' => (int) $account['id']];
    foreach ($myReadableBoardIds as $index => $boardId) {
        $paramKey = 'board_id_' . (string) $index;
        $myBoardPlaceholders[] = ':' . $paramKey;
        $myBoardParams[$paramKey] = $boardId;
    }
    $myBoardWhereSql = implode(', ', $myBoardPlaceholders);
    $categorySelectSql = sr_community_categories_supported($pdo)
        ? 'p.category_id, cat.category_key, cat.title AS category_title, cat.status AS category_status'
        : 'NULL AS category_id, NULL AS category_key, NULL AS category_title, NULL AS category_status';
    $categoryJoinSql = sr_community_categories_supported($pdo) ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';

    if ($myType === 'posts') {
        $stmt = $pdo->prepare(
            'SELECT p.id, p.board_id, b.board_key, b.title AS board_title, ' . $categorySelectSql . ', p.author_account_id,
                    p.title, p.body_text, p.is_secret, p.status, p.view_count, p.created_at, p.updated_at,
                    (SELECT COUNT(*) FROM sr_community_comments c WHERE c.post_id = p.id AND c.status = \'published\') AS published_comment_count
             FROM sr_community_posts p
             INNER JOIN sr_community_boards b ON b.id = p.board_id
             ' . $categoryJoinSql . '
             WHERE p.author_account_id = :account_id
               AND p.status IN (\'published\', \'pending\')
               AND b.status = \'enabled\'
               AND p.board_id IN (' . $myBoardWhereSql . ')
             ORDER BY p.id DESC
             LIMIT :limit_value OFFSET :offset_value'
        );
        foreach ($myBoardParams as $key => $value) {
            $stmt->bindValue($key, $value, $key === 'account_id' || str_starts_with($key, 'board_id_') ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit_value', $myPerPage + 1, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', $myOffset, PDO::PARAM_INT);
        $stmt->execute();
        $myPosts = $stmt->fetchAll();
        $myHasNextPage = count($myPosts) > $myPerPage;
        if ($myHasNextPage) {
            $myPosts = array_slice($myPosts, 0, $myPerPage);
        }
    } else {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.post_id, c.body_text, c.is_secret, c.status, c.created_at, c.updated_at,
                    p.title AS post_title, p.author_account_id AS post_author_account_id, p.is_secret AS post_is_secret,
                    b.id AS board_id, b.board_key, b.title AS board_title
             FROM sr_community_comments c
             INNER JOIN sr_community_posts p ON p.id = c.post_id
             INNER JOIN sr_community_boards b ON b.id = p.board_id
             WHERE c.author_account_id = :account_id
               AND c.status = \'published\'
               AND p.status = \'published\'
               AND b.status = \'enabled\'
               AND b.id IN (' . $myBoardWhereSql . ')
             ORDER BY c.id DESC
             LIMIT :limit_value OFFSET :offset_value'
        );
        foreach ($myBoardParams as $key => $value) {
            $stmt->bindValue($key, $value, $key === 'account_id' || str_starts_with($key, 'board_id_') ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit_value', $myPerPage + 1, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', $myOffset, PDO::PARAM_INT);
        $stmt->execute();
        $myComments = $stmt->fetchAll();
        $myHasNextPage = count($myComments) > $myPerPage;
        if ($myHasNextPage) {
            $myComments = array_slice($myComments, 0, $myPerPage);
        }
    }
}

$communityLayoutKey = sr_community_layout_key($settings, $site ?? null, $pdo);

include SR_ROOT . '/modules/community/views/my.php';
