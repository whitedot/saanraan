<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/page/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
sr_require_csrf();

$pageId = (int) sr_post_string('page_id', 20);
$page = sr_page_by_id($pdo, $pageId);
if (!is_array($page)) {
    sr_render_error(404, '숨김 처리할 페이지를 찾을 수 없습니다.');
}

sr_page_hide($pdo, $pageId, (int) $account['id']);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'admin',
    'event_type' => 'page.hidden',
    'target_type' => 'page',
    'target_id' => (string) $pageId,
    'result' => 'success',
    'message' => 'Page hidden.',
    'metadata' => [
        'slug' => (string) $page['slug'],
    ],
]);

$_SESSION['sr_page_admin_notice'] = '페이지를 숨김 처리했습니다.';
sr_redirect('/admin/pages');
