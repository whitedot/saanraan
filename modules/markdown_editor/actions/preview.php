<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/markdown_editor/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/markdown-editor/settings', 'edit');

if (sr_request_method() !== 'POST') {
    sr_json_response(['ok' => false, 'message' => 'method_not_allowed'], 405);
}

try {
    sr_require_csrf();
} catch (Throwable) {
    sr_json_response(['ok' => false, 'message' => 'csrf_failed'], 403);
}

$postedSettings = sr_markdown_editor_settings($pdo, sr_markdown_editor_settings_from_post());
$errors = sr_markdown_editor_validate_settings($postedSettings);
if ($errors !== []) {
    sr_json_response(['ok' => false, 'errors' => $errors], 422, ['Cache-Control: no-store']);
}

$markdown = sr_post_string_without_truncation('markdown', 100000);
if (!is_string($markdown) || trim($markdown) === '') {
    $markdown = sr_markdown_editor_sample_markdown();
}

$result = sr_markdown_editor_render($pdo, $markdown, 'full', ['settings_override' => $postedSettings]);
sr_json_response([
    'ok' => true,
    'html' => (string) ($result['html'] ?? ''),
    'css' => sr_markdown_editor_css($pdo, $postedSettings),
    'profile_hash' => (string) ($result['profile_hash'] ?? ''),
], 200, ['Cache-Control: no-store']);
