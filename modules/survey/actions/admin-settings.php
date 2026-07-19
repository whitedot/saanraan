<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';
$surveyReactionAvailable = sr_module_enabled($pdo, 'reaction')
    && is_file(SR_ROOT . '/modules/reaction/public-reaction.php');
if ($surveyReactionAvailable) {
    require_once SR_ROOT . '/modules/reaction/public-reaction.php';
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
$editorOptions = sr_editor_options($pdo);
$surveyIdentityViewAvailable = $surveyIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'survey.view');
$surveyIdentityViewAdultAvailable = $surveyIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'survey.view.adult');
$surveyLayoutOptions = sr_survey_layout_options($pdo);
$publicThemeOptions = sr_survey_theme_options();
$siteMenuOptions = sr_module_contract_invoke(
    $pdo,
    'site_menu',
    'site-menu-provider.php',
    'options_function',
    [],
    []
);
$siteMenuOptions = is_array($siteMenuOptions) ? $siteMenuOptions : [];
$surveySidebarMenuTypeOptions = sr_survey_sidebar_menu_type_options($siteMenuOptions !== []);
$settings = sr_survey_settings($pdo);
$adminFormDraftKey = 'survey.settings';
$adminFormDraftContext = 'default';
$adminFormDraftFingerprint = sr_admin_form_draft_fingerprint($settings);
$adminFormDraft = null;

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), $permissionPath, 'edit');
    $adminFormAction = sr_post_string('admin_form_action', 30);
    if ($adminFormAction === 'save_draft') {
        try {
            sr_admin_form_draft_save($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext, $_POST, $adminFormDraftFingerprint);
            sr_admin_redirect_with_result(sr_admin_action_result([], '설문 환경설정 입력값을 임시저장했습니다.'), $permissionPath);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'survey_settings_draft_save_failed');
            sr_admin_redirect_with_result(sr_admin_action_result(['임시저장 중 오류가 발생했습니다.'], ''), $permissionPath);
        }
    }
    if ($adminFormAction === 'discard_draft') {
        sr_admin_form_draft_delete($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext);
        sr_admin_redirect_with_result(sr_admin_action_result([], '설문 환경설정 임시저장본을 삭제했습니다.'), $permissionPath);
    }
    $postedSidebarMenuType = sr_post_string('sidebar_menu_type', 30);
    $postedSidebarPopularLimit = sr_admin_post_int_in_range('sidebar_popular_limit', 1, 10);
    $postedSidebarCommentsLimit = sr_admin_post_int_in_range('sidebar_comments_limit', 1, 10);
    $settings = sr_survey_settings_from_post();
    $errors = sr_survey_settings_validation_errors($pdo, $settings);
    if ($postedSidebarMenuType !== (string) ($settings['sidebar_menu_type'] ?? '')
        || !isset($surveySidebarMenuTypeOptions[(string) ($settings['sidebar_menu_type'] ?? '')])) {
        $errors[] = '설문 사이드 메뉴 유형이 올바르지 않습니다.';
    }
    if ($postedSidebarPopularLimit === null || $postedSidebarCommentsLimit === null) {
        $errors[] = '설문 사이드 표시 개수는 1~10 사이로 입력하세요.';
    }
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
        sr_admin_form_draft_delete($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext);
        sr_admin_redirect_with_result(sr_admin_action_result([], '설문 환경설정을 저장했습니다.'), $permissionPath);
    }
}
$adminFormDraft = sr_admin_form_draft_with_state(
    sr_admin_form_draft_get($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext),
    $adminFormDraftFingerprint
);
if (is_array($adminFormDraft)) {
    $settings = sr_admin_form_draft_with_post((array) $adminFormDraft['payload'], static function (): array {
        return sr_survey_settings_from_post();
    });
}

$adminPageTitle = '설문 환경설정';
include SR_ROOT . '/modules/survey/views/admin-settings.php';
