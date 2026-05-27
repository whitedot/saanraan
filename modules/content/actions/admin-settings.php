<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/settings', 'view');

$errors = [];
$notice = '';
$settings = sr_content_settings($pdo);
$editorOptions = sr_editor_options($pdo);

if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/settings', 'edit');
    sr_require_csrf();

    $postedEditorInput = sr_post_string('editor', 30);
    $postedOnceHistoryPolicyInput = sr_post_string('once_history_policy', 40);
    $postedSettings = [
        'editor' => sr_editor_normalize_key($postedEditorInput),
        'once_history_policy' => sr_content_once_history_policy($postedOnceHistoryPolicyInput),
    ];

    if ($postedEditorInput !== (string) $postedSettings['editor'] || !array_key_exists((string) $postedSettings['editor'], $editorOptions)) {
        $errors[] = '본문 에디터 값이 올바르지 않습니다.';
    }
    if ($postedOnceHistoryPolicyInput !== (string) $postedSettings['once_history_policy']) {
        $errors[] = '최초 1회 과거 이용 인정 기준 값이 올바르지 않습니다.';
    }

    if ($errors === []) {
        $beforeSettings = $settings;
        sr_content_save_settings($pdo, $postedSettings);
        $settings = sr_content_settings($pdo);

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'content.settings.updated',
            'target_type' => 'module',
            'target_id' => 'content',
            'result' => 'success',
            'message' => 'Content settings updated.',
            'metadata' => [
                'before' => $beforeSettings,
                'after' => $settings,
            ],
        ]);

        $notice = '콘텐츠 환경설정을 저장했습니다.';
    } else {
        $settings = array_merge($settings, $postedSettings);
    }
}

include SR_ROOT . '/modules/content/views/admin-settings.php';
