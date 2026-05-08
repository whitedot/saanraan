<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$boardKey = toy_get_string('key', 60);
$board = toy_community_board_by_key($pdo, $boardKey);
if (!is_array($board) || (string) $board['status'] !== 'enabled') {
    toy_render_error(404, '게시판을 찾을 수 없습니다.');
}
$account = toy_member_current_account($pdo);
if (!is_array($account) && toy_community_board_requires_login($board)) {
    $account = toy_member_require_login($pdo);
}
if (!toy_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)) {
    toy_render_error(403, '이 게시판을 볼 수 없습니다.');
}

$settings = toy_module_settings($pdo, 'community');
$postsPerPage = max(1, min(100, (int) ($settings['posts_per_page'] ?? 20)));
$posts = toy_community_public_posts($pdo, (int) $board['id'], $postsPerPage);
$skinKey = toy_community_skin_key();
$skinView = toy_community_skin_view($skinKey, 'list');

include $skinView;
