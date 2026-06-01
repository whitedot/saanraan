<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$contentIdValue = sr_post_string('content_id', 20);
$contentId = preg_match('/\A[1-9][0-9]*\z/', $contentIdValue) === 1 ? (int) $contentIdValue : 0;
$page = sr_content_by_id($pdo, $contentId);
if (!is_array($page) || (string) ($page['status'] ?? '') !== 'published') {
    sr_render_error(404, sr_t('content::action.error.content_not_found'));
}
if (sr_content_asset_access_required($page)) {
    $access = sr_content_charge_view_access($pdo, $page, (int) $account['id'], false);
    if (empty($access['allowed'])) {
        sr_render_error(403, '콘텐츠 열람 권한이 있어야 댓글을 작성할 수 있습니다.');
    }
}

$values = sr_content_comment_input_values();
$errors = sr_content_validate_comment_input($values);
if ($errors !== []) {
    $_SESSION['sr_content_comment_errors'] = $errors;
    $_SESSION['sr_content_comment_body'] = is_string($values['body_text'] ?? null) ? (string) $values['body_text'] : '';
    sr_redirect(sr_content_path((string) $page['slug']) . '#content-comments');
}

$commentId = sr_content_create_comment($pdo, $contentId, (int) $account['id'], $values);
sr_content_create_comment_notifications($pdo, $page, $commentId, (string) $values['body_text'], (int) $account['id']);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'content.comment.created',
    'target_type' => 'content_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Content comment created.',
    'metadata' => [
        'content_id' => $contentId,
    ],
]);

$_SESSION['sr_content_comment_notice'] = '댓글을 등록했습니다.';
sr_redirect(sr_content_path((string) $page['slug']) . '#content-comments');
