<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/page/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/pages', 'delete');
sr_require_csrf();

$pageId = (int) sr_post_string('page_id', 20);
$page = sr_page_by_id($pdo, $pageId);
if (!is_array($page)) {
    sr_render_error(404, sr_t('page::action.error.page_hide_not_found'));
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
