<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
$sourceContentId = (int) sr_get_string('id', 20);
if (sr_request_method() === 'POST') {
    $sourceContentId = (int) sr_post_string('content_id', 20);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'edit');
    sr_require_csrf();
} else {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'view');
}

$sourceContent = sr_content_by_id($pdo, $sourceContentId);
if (!is_array($sourceContent)) {
    sr_render_error(404, '복사할 콘텐츠를 찾을 수 없습니다.');
}

$suggestion = sr_content_copy_suggestion($sourceContent);
$values = [
    'title' => sr_request_method() === 'POST' ? sr_post_string('title', 160) : (string) $suggestion['title'],
    'slug' => sr_request_method() === 'POST' ? sr_post_string('slug', 120) : (string) $suggestion['slug'],
];
$errors = [];

if (sr_request_method() === 'POST') {
    try {
        $newContentId = sr_content_copy($pdo, $sourceContentId, $values, (int) $account['id']);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'content.copied',
            'target_type' => 'content',
            'target_id' => (string) $newContentId,
            'result' => 'success',
            'message' => 'Content copied.',
            'metadata' => [
                'source_content_id' => $sourceContentId,
                'slug' => (string) $values['slug'],
            ],
        ]);
        $_SESSION['sr_content_admin_notice'] = '콘텐츠 복사본을 만들었습니다.';
        sr_redirect('/admin/content/edit?id=' . (string) $newContentId);
    } catch (InvalidArgumentException $exception) {
        $errors = preg_split('/\n+/', $exception->getMessage()) ?: [$exception->getMessage()];
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_copy_failed');
        }
        $errors = ['콘텐츠 복사 중 오류가 발생했습니다.'];
    }
}

include SR_ROOT . '/modules/content/views/admin-content-copy.php';
