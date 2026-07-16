<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/markdown_editor/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/markdown-editor/settings', 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_markdown_editor_settings($pdo);
if ($errors !== [] && is_array($flashResult['data']['settings'] ?? null)) {
    $settings = sr_markdown_editor_settings($pdo, $flashResult['data']['settings']);
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/markdown-editor/settings', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent !== 'save_settings') {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    $postedSettings = sr_markdown_editor_settings($pdo, sr_markdown_editor_settings_from_post());
    $errors = array_merge($errors, sr_markdown_editor_validate_settings($postedSettings));

    if ($errors === []) {
        sr_markdown_editor_save_settings($pdo, $postedSettings);
        $settings = sr_markdown_editor_settings($pdo);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'markdown_editor.settings.updated',
            'target_type' => 'module',
            'target_id' => 'markdown_editor',
            'result' => 'success',
            'message' => 'Markdown editor settings updated.',
            'metadata' => [
                'profile_hash' => sr_markdown_editor_profile_hash($pdo),
            ],
        ]);
        $notice = 'Markdown 편집기 설정을 저장했습니다.';
    }

    $resultData = $errors !== [] ? ['settings' => $postedSettings] : [];
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice, $resultData), '/admin/markdown-editor/settings');
}

include SR_ROOT . '/modules/markdown_editor/views/admin-settings.php';
