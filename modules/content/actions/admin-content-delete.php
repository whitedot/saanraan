<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/embed_manager/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'delete');
sr_require_csrf();

$pageId = (int) sr_post_string('content_id', 20);
$page = sr_content_by_id($pdo, $pageId);
if (!is_array($page)) {
    sr_render_error(404, sr_t('content::action.error.content_delete_not_found'));
}
if ((string) ($page['status'] ?? '') === 'deleted') {
    sr_render_error(404, sr_t('content::action.error.content_delete_not_found'));
}

$deleteResult = sr_content_delete_redacted($pdo, $pageId, (int) $account['id']);
sr_embed_manager_mark_target_url_cache_stale($pdo, 'content', 'content', $pageId);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'admin',
    'event_type' => 'content.deleted',
    'target_type' => 'content',
    'target_id' => (string) $pageId,
    'result' => 'success',
    'message' => 'Content deleted with originals redacted.',
    'metadata' => [
        'slug' => (string) $page['slug'],
        'before_status' => (string) ($page['status'] ?? ''),
        'body_files_deleted' => (int) ($deleteResult['body_files_deleted'] ?? 0),
        'cover_image_deleted' => !empty($deleteResult['cover_image_deleted']),
        'cover_image_failed' => !empty($deleteResult['cover_image_failed']),
        'files_deleted' => (int) ($deleteResult['files_deleted'] ?? 0),
        'files_failed' => (int) ($deleteResult['files_failed'] ?? 0),
    ],
]);

$_SESSION['sr_content_admin_notice'] = sr_t('content::action.notice.content_deleted');
sr_redirect('/admin/content');
