<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$postIdValue = sr_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$intent = sr_post_string('intent', 20);

if ($intent === 'remove') {
    $removed = sr_community_remove_scrap($pdo, (int) $account['id'], $postId);
    if ($removed) {
        $_SESSION['sr_community_scrap_notice'] = '스크랩을 해제했습니다.';
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.scrap.removed',
            'target_type' => 'community_post',
            'target_id' => (string) $postId,
            'result' => 'success',
            'message' => 'Community scrap removed.',
        ]);
    } else {
        $_SESSION['sr_community_scrap_notice'] = '이미 해제된 스크랩입니다.';
    }
    $post = sr_community_post_for_read($pdo, $postId, $account);
    if (!is_array($post)) {
        sr_redirect('/community/scraps');
    }
    sr_redirect('/community/post?id=' . (string) $postId);
}

$post = sr_community_post_for_read($pdo, $postId, $account);
if (!is_array($post)) {
    sr_render_error(404, '게시글을 찾을 수 없습니다.');
} else {
    $added = sr_community_add_scrap($pdo, (int) $account['id'], $postId);
    if ($added) {
        $_SESSION['sr_community_scrap_notice'] = '게시글을 스크랩했습니다.';
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.scrap.added',
            'target_type' => 'community_post',
            'target_id' => (string) $postId,
            'result' => 'success',
            'message' => 'Community scrap added.',
            'metadata' => [
                'board_key' => (string) $post['board_key'],
            ],
        ]);
    } else {
        $_SESSION['sr_community_scrap_notice'] = '이미 스크랩한 게시글입니다.';
    }
}

sr_redirect('/community/post?id=' . (string) $postId);
