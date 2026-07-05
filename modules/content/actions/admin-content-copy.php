<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'edit');
sr_require_csrf();

$sourceContentId = (int) sr_post_string('content_id', 20);
$returnTo = sr_post_string('return_to', 300);
$returnTo = sr_admin_safe_get_url($returnTo, '/admin/content');

$sourceContent = sr_content_by_id($pdo, $sourceContentId);
if (!is_array($sourceContent)) {
    sr_admin_redirect_with_result(sr_admin_action_result(['복사할 콘텐츠를 찾을 수 없습니다.'], ''), $returnTo);
}
if ((string) ($sourceContent['status'] ?? '') === 'deleted') {
    sr_admin_redirect_with_result(sr_admin_action_result(['삭제된 콘텐츠는 복사할 수 없습니다.'], ''), $returnTo);
}

$values = [
    'title' => sr_post_string('title', 160),
    'slug' => sr_post_string('slug', 120),
    'copy_series' => ($_POST['copy_series'] ?? '') === '1',
    'series_keys' => is_array($_POST['content_series_keys'] ?? null) ? $_POST['content_series_keys'] : [],
    'series_titles' => is_array($_POST['content_series_titles'] ?? null) ? $_POST['content_series_titles'] : [],
];
$errors = [];

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
    sr_admin_redirect_with_result(sr_admin_action_result([], '콘텐츠 복사본을 만들었습니다.'), '/admin/content/edit?id=' . (string) $newContentId);
} catch (InvalidArgumentException $exception) {
    $errors = preg_split('/\n+/', $exception->getMessage()) ?: [$exception->getMessage()];
} catch (Throwable $exception) {
    if (function_exists('sr_log_exception')) {
        sr_log_exception($exception, 'content_copy_failed');
    }
    $errors = ['콘텐츠 복사 중 오류가 발생했습니다.'];
}

sr_admin_redirect_with_result(sr_admin_action_result($errors, ''), $returnTo);
