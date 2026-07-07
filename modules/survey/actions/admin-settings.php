<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';
$surveyReactionAvailable = sr_module_enabled($pdo, 'reaction')
    && is_file(SR_ROOT . '/modules/reaction/helpers.php');
if ($surveyReactionAvailable) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}
$surveyIdentityVerificationModuleAvailable = sr_module_enabled($pdo, 'identity_verification')
    && is_file(SR_ROOT . '/modules/identity_verification/helpers.php');
if ($surveyIdentityVerificationModuleAvailable) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/surveys/settings';
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), $permissionPath, 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = (array) ($flashResult['errors'] ?? []);
$notice = (string) ($flashResult['notice'] ?? '');
$reactionPresetOptions = $surveyReactionAvailable && function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$surveyIdentityViewAvailable = $surveyIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'survey.view');
$surveyIdentityViewAdultAvailable = $surveyIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'survey.view.adult');
$surveyLayoutOptions = sr_survey_layout_options($pdo);
$publicThemeOptions = sr_survey_theme_options();
$siteMenuOptions = [];
if (sr_module_enabled($pdo, 'site_menu') && is_file(SR_ROOT . '/modules/site_menu/helpers.php')) {
    require_once SR_ROOT . '/modules/site_menu/helpers.php';
    $siteMenuOptions = sr_site_menu_options($pdo);
}
$settings = sr_survey_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), $permissionPath, 'edit');
    $settings = sr_survey_settings_from_post();
    $errors = sr_survey_settings_validation_errors($pdo, $settings);
    if ($errors === []) {
        sr_survey_save_settings($pdo, $settings);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($account['id'] ?? 0),
            'actor_type' => 'admin',
            'event_type' => 'survey.settings.updated',
            'target_type' => 'module',
            'target_id' => 'survey',
            'result' => 'success',
            'message' => 'Survey settings updated.',
            'metadata' => $settings,
        ]);
        sr_admin_redirect_with_result(sr_admin_action_result([], '설문 환경설정을 저장했습니다.'), $permissionPath);
    }
}

$adminPageTitle = '설문 환경설정';
include SR_ROOT . '/modules/survey/views/admin-settings.php';
