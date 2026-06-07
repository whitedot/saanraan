<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/quiz/settings';
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), $permissionPath, 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = (array) ($flashResult['errors'] ?? []);
$notice = (string) ($flashResult['notice'] ?? '');
$assetOptions = sr_quiz_asset_options($pdo);
$couponRewardDefinitions = sr_quiz_reward_coupon_definitions($pdo);
$settings = sr_quiz_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), $permissionPath, 'edit');

    $settings = sr_quiz_settings_from_post();
    $errors = sr_quiz_settings_validation_errors($pdo, $settings, $assetOptions);
    if ($errors === []) {
        try {
            sr_quiz_save_settings($pdo, $settings);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) ($account['id'] ?? 0),
                'actor_type' => 'admin',
                'event_type' => 'quiz.settings.updated',
                'target_type' => 'module',
                'target_id' => 'quiz',
                'result' => 'success',
                'message' => 'Quiz settings updated.',
                'metadata' => $settings,
            ]);
            sr_admin_redirect_with_result(sr_admin_action_result([], '퀴즈 환경설정을 저장했습니다.'), $permissionPath);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'quiz_settings_save_failed');
            $errors[] = '퀴즈 환경설정 저장 중 오류가 발생했습니다.';
        }
    }
}

$adminPageTitle = '퀴즈 환경설정';
include SR_ROOT . '/modules/quiz/views/admin-settings.php';
