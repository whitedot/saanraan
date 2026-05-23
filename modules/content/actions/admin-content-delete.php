<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'delete');
sr_require_csrf();

$pageId = (int) sr_post_string('content_id', 20);
$page = sr_content_by_id($pdo, $pageId);
if (!is_array($page)) {
    sr_render_error(404, sr_t('content::action.error.content_hide_not_found'));
}

sr_content_hide($pdo, $pageId, (int) $account['id']);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'admin',
    'event_type' => 'content.hidden',
    'target_type' => 'content',
    'target_id' => (string) $pageId,
    'result' => 'success',
    'message' => 'Content hidden.',
    'metadata' => [
        'slug' => (string) $page['slug'],
    ],
]);

$_SESSION['sr_content_admin_notice'] = '콘텐츠를 숨김 처리했습니다.';
sr_redirect('/admin/content');
