<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';
if (is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/quiz/settings';
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), $permissionPath, 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = (array) ($flashResult['errors'] ?? []);
$notice = (string) ($flashResult['notice'] ?? '');
$assetOptions = sr_quiz_asset_options($pdo);
$couponRewardDefinitions = sr_quiz_reward_coupon_definitions($pdo);
$publicLayoutOptions = sr_public_layout_options($pdo);
$publicThemeOptions = sr_public_theme_options($pdo);
$reactionPresetOptions = function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$siteMenuOptions = [];
if (sr_module_enabled($pdo, 'site_menu') && is_file(SR_ROOT . '/modules/site_menu/helpers.php')) {
    require_once SR_ROOT . '/modules/site_menu/helpers.php';
    $siteMenuOptions = sr_site_menu_options($pdo);
}
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
    sr_admin_redirect_with_result(sr_admin_action_result($errors, ''), $permissionPath);
}

$adminPageTitle = '퀴즈 환경설정';
include SR_ROOT . '/modules/quiz/views/admin-settings.php';
