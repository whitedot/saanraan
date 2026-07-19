<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';
$quizReactionAvailable = sr_module_enabled($pdo, 'reaction')
    && is_file(SR_ROOT . '/modules/reaction/public-reaction.php');
if ($quizReactionAvailable) {
    require_once SR_ROOT . '/modules/reaction/public-reaction.php';
}
$quizIdentityVerificationModuleAvailable = sr_module_enabled($pdo, 'identity_verification')
    && is_file(SR_ROOT . '/modules/identity_verification/helpers.php');
if ($quizIdentityVerificationModuleAvailable) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/quiz/settings';
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), $permissionPath, 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = (array) ($flashResult['errors'] ?? []);
$notice = (string) ($flashResult['notice'] ?? '');
$assetOptions = sr_quiz_asset_options($pdo);
$publicLayoutOptions = sr_quiz_layout_options($pdo);
$publicThemeOptions = sr_quiz_theme_options();
$reactionPresetOptions = $quizReactionAvailable && function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$editorOptions = sr_editor_options($pdo);
$quizIdentityViewAvailable = $quizIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'quiz.view');
$quizIdentityViewAdultAvailable = $quizIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'quiz.view.adult');
$siteMenuOptions = sr_module_contract_invoke(
    $pdo,
    'site_menu',
    'site-menu-provider.php',
    'options_function',
    [],
    []
);
$siteMenuOptions = is_array($siteMenuOptions) ? $siteMenuOptions : [];
$quizSidebarMenuTypeOptions = sr_quiz_sidebar_menu_type_options($siteMenuOptions !== []);
$settings = sr_quiz_settings($pdo);
$adminFormDraftKey = 'quiz.settings';
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
            sr_admin_redirect_with_result(sr_admin_action_result([], '퀴즈 환경설정 입력값을 임시저장했습니다.'), $permissionPath);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'quiz_settings_draft_save_failed');
            sr_admin_redirect_with_result(sr_admin_action_result(['임시저장 중 오류가 발생했습니다.'], ''), $permissionPath);
        }
    }
    if ($adminFormAction === 'discard_draft') {
        sr_admin_form_draft_delete($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext);
        sr_admin_redirect_with_result(sr_admin_action_result([], '퀴즈 환경설정 임시저장본을 삭제했습니다.'), $permissionPath);
    }

    $postedSidebarMenuType = sr_post_string('sidebar_menu_type', 30);
    $postedSidebarPopularLimit = sr_admin_post_int_in_range('sidebar_popular_limit', 1, 10);
    $postedSidebarCommentsLimit = sr_admin_post_int_in_range('sidebar_comments_limit', 1, 10);
    $settings = sr_quiz_settings_from_post();
    $errors = sr_quiz_settings_validation_errors($pdo, $settings, $assetOptions);
    if ($postedSidebarMenuType !== (string) ($settings['sidebar_menu_type'] ?? '')
        || !isset($quizSidebarMenuTypeOptions[(string) ($settings['sidebar_menu_type'] ?? '')])) {
        $errors[] = '퀴즈 사이드 메뉴 유형이 올바르지 않습니다.';
    }
    if ($postedSidebarPopularLimit === null || $postedSidebarCommentsLimit === null) {
        $errors[] = '퀴즈 사이드 표시 개수는 1~10 사이로 입력하세요.';
    }
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
            sr_admin_form_draft_delete($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext);
            sr_admin_redirect_with_result(sr_admin_action_result([], '퀴즈 환경설정을 저장했습니다.'), $permissionPath);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'quiz_settings_save_failed');
            $errors[] = '퀴즈 환경설정 저장 중 오류가 발생했습니다.';
        }
    }
    sr_admin_redirect_with_result(sr_admin_action_result($errors, ''), $permissionPath);
}
$adminFormDraft = sr_admin_form_draft_with_state(
    sr_admin_form_draft_get($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext),
    $adminFormDraftFingerprint
);
if (is_array($adminFormDraft)) {
    $settings = sr_admin_form_draft_with_post((array) $adminFormDraft['payload'], static function (): array {
        return sr_quiz_settings_from_post();
    });
}
$couponRewardDefinitions = sr_quiz_reward_coupon_definitions($pdo, (int) ($settings['default_reward_coupon_definition_id'] ?? 0));

$adminPageTitle = '퀴즈 환경설정';
include SR_ROOT . '/modules/quiz/views/admin-settings.php';
