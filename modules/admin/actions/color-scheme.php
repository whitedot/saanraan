<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/settings', 'edit');
sr_require_csrf();

$colorScheme = sr_post_string('ui_color_scheme', 20);
$options = sr_color_scheme_options();

header('Content-Type: application/json; charset=utf-8');

if (!isset($options[$colorScheme])) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'errors' => ['관리자 UI 색상 모드 값이 올바르지 않습니다.'],
    ], JSON_UNESCAPED_UNICODE);
    sr_finish_response();
}

$adminSettings = sr_admin_settings($pdo);
$previousColorScheme = sr_admin_color_scheme($adminSettings);
if ($colorScheme !== $previousColorScheme) {
    sr_admin_save_color_scheme($pdo, $colorScheme);

    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'admin.settings.updated',
        'target_type' => 'module',
        'target_id' => 'admin',
        'result' => 'success',
        'message' => 'Admin settings updated.',
        'metadata' => [
            'before' => [
                'admin_color_scheme' => $previousColorScheme,
            ],
            'after' => [
                'admin_color_scheme' => $colorScheme,
            ],
        ],
    ]);
}

echo json_encode([
    'ok' => true,
    'ui_color_scheme' => $colorScheme,
], JSON_UNESCAPED_UNICODE);
sr_finish_response();
