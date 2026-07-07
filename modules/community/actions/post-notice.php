<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$postIdValue = sr_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = sr_community_admin_post_by_id($pdo, $postId);
if (!is_array($post) || (string) ($post['status'] ?? '') !== 'published') {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

$board = sr_community_board_by_id($pdo, (int) ($post['board_id'] ?? 0));
if (!is_array($board) || (string) ($board['status'] ?? '') !== 'enabled') {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

$isAdminWriter = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit');
if (!sr_community_account_can_write_notice($pdo, $board, $account, $isAdminWriter)) {
    sr_render_error(403, '공지사항으로 지정할 권한이 없습니다.');
}

$intent = sr_post_string('intent', 20);
$isNotice = $intent !== 'remove';
$beforeIsNotice = (int) ($post['is_notice'] ?? 0) === 1;
sr_community_update_post_notice($pdo, $postId, $isNotice);

sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => $isAdminWriter ? 'admin' : 'community_board_manager',
    'event_type' => $isNotice ? 'community.post.notice_set' : 'community.post.notice_removed',
    'target_type' => 'community_post',
    'target_id' => (string) $postId,
    'result' => 'success',
    'message' => $isNotice ? 'Community post marked as notice.' : 'Community post notice mark removed.',
    'metadata' => [
        'board_key' => (string) ($post['board_key'] ?? ''),
        'before_is_notice' => $beforeIsNotice ? 1 : 0,
        'after_is_notice' => $isNotice ? 1 : 0,
        'permission_source' => $isAdminWriter ? 'admin' : 'board_manager',
    ],
]);

$_SESSION['sr_community_post_notice'] = $isNotice ? '게시글을 공지사항으로 지정했습니다.' : '게시글 공지사항 지정을 해제했습니다.';
sr_redirect('/community/post?id=' . (string) $postId);
