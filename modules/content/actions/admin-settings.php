<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
$contentReactionAvailable = sr_module_enabled($pdo, 'reaction')
    && is_file(SR_ROOT . '/modules/reaction/public-reaction.php');
if ($contentReactionAvailable) {
    require_once SR_ROOT . '/modules/reaction/public-reaction.php';
}
$contentIdentityVerificationModuleAvailable = sr_module_enabled($pdo, 'identity_verification')
    && is_file(SR_ROOT . '/modules/identity_verification/helpers.php');
if ($contentIdentityVerificationModuleAvailable) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}
$contentIdentityContentViewAvailable = $contentIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'content.view');
$contentIdentityContentViewAdultAvailable = $contentIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'content.view.adult');
$contentIdentityAuthorApplicationAvailable = $contentIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'content.author_application');
$contentIdentityAuthorApplicationAdultAvailable = $contentIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'content.author_application.adult');

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/settings', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_content_settings($pdo);
$adminFormDraftKey = 'content.settings';
$adminFormDraftContext = 'default';
$adminFormDraftFingerprint = sr_admin_form_draft_fingerprint($settings);
$adminFormDraft = null;
$assetModuleOptions = sr_content_asset_module_options($pdo);
$editorOptions = sr_editor_options($pdo);
$toolbarPresetOptions = sr_content_toolbar_preset_options();
$reactionPresetOptions = $contentReactionAvailable && function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$publicLayoutOptions = sr_content_layout_options($pdo);
$publicThemeOptions = sr_content_theme_options();
$siteMenuOptions = sr_module_contract_invoke(
    $pdo,
    'site_menu',
    'site-menu-provider.php',
    'options_function',
    [],
    []
);
$siteMenuOptions = is_array($siteMenuOptions) ? $siteMenuOptions : [];
$contentSidebarMenuTypeOptions = sr_content_sidebar_menu_type_options($siteMenuOptions !== []);

if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/settings', 'edit');
    sr_require_csrf();

    $adminFormAction = sr_post_string('admin_form_action', 30);
    if ($adminFormAction === 'save_draft') {
        try {
            sr_admin_form_draft_save($pdo, (int) $account['id'], $adminFormDraftKey, $adminFormDraftContext, $_POST, $adminFormDraftFingerprint);
            sr_admin_redirect_with_result(sr_admin_action_result([], '콘텐츠 환경설정 입력값을 임시저장했습니다.'), '/admin/content/settings');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'content_settings_draft_save_failed');
            sr_admin_redirect_with_result(sr_admin_action_result(['임시저장 중 오류가 발생했습니다.'], ''), '/admin/content/settings');
        }
    }
    if ($adminFormAction === 'discard_draft') {
        sr_admin_form_draft_delete($pdo, (int) $account['id'], $adminFormDraftKey, $adminFormDraftContext);
        sr_admin_redirect_with_result(sr_admin_action_result([], '콘텐츠 환경설정 임시저장본을 삭제했습니다.'), '/admin/content/settings');
    }

    $postedEditorInput = sr_post_string('editor', 30);
    $postedCommentEditorInput = sr_post_string('comment_editor', 30);
    $postedToolbarPresetInput = sr_post_string('editor_toolbar_preset', 80);
    $postedToolbarPreset = sr_content_toolbar_preset_key($postedToolbarPresetInput);
    $postedOnceHistoryPolicyInput = sr_post_string('once_history_policy', 40);
    $postedAuthorRewardEnabled = sr_post_string('member_submission_author_reward_enabled', 1) === '1';
    $postedAuthorRewardAssetModule = sr_content_clean_slug(sr_post_string('member_submission_author_reward_asset_module', 30));
    $postedAuthorRewardAmount = sr_admin_post_int_in_range('member_submission_author_reward_amount', 0, 999999999);
    $postedReactionEnabled = sr_post_string('reaction_enabled', 1) === '1';
    $postedReactionPresetInput = sr_post_string('reaction_preset_key', 80);
    $postedReactionCommentPresetInput = sr_post_string('reaction_comment_preset_key', 80);
    $postedCommentExtraFieldsInput = sr_post_string_without_truncation('comment_extra_fields_json', 20000);
    $postedThemeKey = sr_view_theme_post_key(sr_post_string('theme_key', 80));
    $postedSidebarMenuTypeInput = sr_post_string('sidebar_menu_type', 30);
    $postedSidebarPopularLimit = sr_admin_post_int_in_range('sidebar_popular_limit', 1, 10);
    $postedSidebarCommentsLimit = sr_admin_post_int_in_range('sidebar_comments_limit', 1, 10);
    $postedSettings = [
        'editor' => sr_editor_effective_key($pdo, sr_editor_normalize_key($postedEditorInput)),
        'editor_toolbar_preset' => $postedToolbarPreset,
        'comment_editor' => sr_editor_effective_key($pdo, sr_editor_normalize_key($postedCommentEditorInput)),
        'external_embed_enabled' => sr_post_string('external_embed_enabled', 1) === '1',
        'internal_embed_enabled' => sr_post_string('internal_embed_enabled', 1) === '1',
        'plain_text_auto_link_urls' => sr_post_string('plain_text_auto_link_urls', 1) === '1',
        'plain_text_auto_link_new_tab' => sr_post_string('plain_text_auto_link_new_tab', 1) === '1',
        'secret_comments_enabled' => sr_post_string('secret_comments_enabled', 1) === '1',
        'comment_extra_fields_json' => sr_comment_extra_field_definitions_json(is_string($postedCommentExtraFieldsInput) ? $postedCommentExtraFieldsInput : '[]'),
        'once_history_policy' => sr_content_once_history_policy($postedOnceHistoryPolicyInput),
        'layout_key' => sr_public_layout_normalize_key(sr_post_string('layout_key', 80)),
        'theme_key' => $postedThemeKey,
        'layout_primary_menu_key' => sr_content_clean_layout_menu_key(sr_post_string('layout_primary_menu_key', 60)),
        'layout_extra_menu_keys_json' => sr_content_layout_extra_menu_items_from_pair_values($_POST['layout_extra_menu_area_keys'] ?? [], $_POST['layout_extra_menu_labels'] ?? [], $_POST['layout_extra_menu_keys'] ?? []),
        'sidebar_enabled' => sr_post_string('sidebar_enabled', 1) === '1',
        'sidebar_menu_type' => sr_content_sidebar_menu_type($postedSidebarMenuTypeInput),
        'sidebar_site_menu_key' => sr_content_clean_layout_menu_key(sr_post_string('sidebar_site_menu_key', 60)),
        'sidebar_popular_limit' => $postedSidebarPopularLimit ?? 5,
        'sidebar_comments_limit' => $postedSidebarCommentsLimit ?? 5,
        'business_info_visible' => sr_post_string('business_info_visible', 1) === '1',
        'series_enabled' => sr_post_string('series_enabled', 1) === '1',
        'member_submission_enabled' => sr_post_string('member_submission_enabled', 1) === '1',
        'identity_content_view_required' => sr_post_string('identity_content_view_required', 1) === '1',
        'identity_content_view_adult_required' => sr_post_string('identity_content_view_adult_required', 1) === '1',
        'identity_author_application_required' => sr_post_string('identity_author_application_required', 1) === '1',
        'identity_author_application_adult_required' => sr_post_string('identity_author_application_adult_required', 1) === '1',
        'member_submission_default_review_required' => sr_post_string('member_submission_default_review_required', 1) === '1',
        'member_submission_author_reward_enabled' => $postedAuthorRewardEnabled,
        'member_submission_author_reward_asset_module' => $postedAuthorRewardAssetModule,
        'member_submission_author_reward_amount' => $postedAuthorRewardAssetModule !== '' ? ($postedAuthorRewardAmount ?? 0) : 0,
        'multi_asset_payment_enabled' => sr_post_string('multi_asset_payment_enabled', 1) === '1',
        'reaction_enabled' => $contentReactionAvailable && $postedReactionEnabled,
        'reaction_preset_key' => $contentReactionAvailable && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $postedReactionPresetInput) : '',
        'reaction_comment_preset_key' => $contentReactionAvailable && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $postedReactionCommentPresetInput) : '',
    ];

    $errors = array_merge($errors, sr_comment_extra_field_definition_errors($postedCommentExtraFieldsInput));

    if ($postedEditorInput !== (string) $postedSettings['editor'] || !array_key_exists((string) $postedSettings['editor'], $editorOptions)) {
        $errors[] = '본문 에디터 값이 올바르지 않습니다.';
    }
    if ($postedCommentEditorInput !== (string) $postedSettings['comment_editor'] || !array_key_exists((string) $postedSettings['comment_editor'], $editorOptions)) {
        $errors[] = '댓글 에디터 값이 올바르지 않습니다.';
    }
    if ($postedToolbarPresetInput !== (string) $postedSettings['editor_toolbar_preset'] || !array_key_exists((string) $postedSettings['editor_toolbar_preset'], $toolbarPresetOptions)) {
        $errors[] = '툴바 구성 값이 올바르지 않습니다.';
    }
    if ($postedOnceHistoryPolicyInput !== (string) $postedSettings['once_history_policy']) {
        $errors[] = '기존 이용자 재결제 기준 값이 올바르지 않습니다.';
    }
    if (!isset($publicLayoutOptions[(string) $postedSettings['layout_key']])) {
        $errors[] = '콘텐츠 공개 레이아웃 값이 올바르지 않습니다.';
        $postedSettings['layout_key'] = sr_content_fallback_layout_key($pdo, $site ?? null);
    }
    if (!isset($publicThemeOptions[(string) $postedSettings['theme_key']])) {
        $errors[] = '기본 콘텐츠 테마 값이 올바르지 않습니다.';
        $postedSettings['theme_key'] = sr_content_theme_key((string) ($settings['theme_key'] ?? 'basic'));
    }
    if ($postedEditorInput !== '' && sr_editor_normalize_key($postedEditorInput) !== (string) $postedSettings['editor']) {
        $errors[] = '콘텐츠 본문 에디터 값이 올바르지 않습니다.';
    }
    if (!$contentReactionAvailable) {
        if ($postedReactionEnabled || $postedReactionPresetInput !== '' || $postedReactionCommentPresetInput !== '') {
            $errors[] = '콘텐츠 리액션 설정을 사용하려면 리액션 모듈을 먼저 설치하고 활성화하세요.';
        }
    } else {
        foreach (['reaction_preset_key' => '콘텐츠 리액션 프리셋', 'reaction_comment_preset_key' => '콘텐츠 댓글 리액션 프리셋'] as $reactionSettingKey => $reactionSettingLabel) {
            $reactionPresetKey = (string) ($postedSettings[$reactionSettingKey] ?? '');
            if ($reactionPresetKey !== '' && !isset($reactionPresetOptions[$reactionPresetKey])) {
                $errors[] = $reactionSettingLabel . ' 값이 올바르지 않습니다.';
            }
        }
    }
    foreach (array_merge([(string) $postedSettings['layout_primary_menu_key']], sr_content_layout_extra_menu_keys_from_value($postedSettings['layout_extra_menu_keys_json'] ?? [])) as $menuKey) {
        if ($menuKey !== '' && !isset($siteMenuOptions[$menuKey]) && !sr_content_layout_menu_key_is_builtin($menuKey)) {
            $errors[] = '레이아웃 사이트 메뉴 값이 올바르지 않습니다.';
            break;
        }
    }
    if ($postedSidebarMenuTypeInput !== (string) $postedSettings['sidebar_menu_type']
        || !isset($contentSidebarMenuTypeOptions[(string) $postedSettings['sidebar_menu_type']])) {
        $errors[] = '콘텐츠 사이드 메뉴 유형이 올바르지 않습니다.';
    } elseif ((string) $postedSettings['sidebar_menu_type'] === 'site_menu'
        && ((string) $postedSettings['sidebar_site_menu_key'] === '' || !isset($siteMenuOptions[(string) $postedSettings['sidebar_site_menu_key']])) ) {
        $errors[] = '콘텐츠 사이드에 표시할 사이트 메뉴를 선택하세요.';
    }
    if ($postedSidebarPopularLimit === null || $postedSidebarCommentsLimit === null) {
        $errors[] = '콘텐츠 사이드 표시 개수는 1~10 사이로 입력하세요.';
    }
    if ((string) $postedSettings['member_submission_author_reward_asset_module'] !== '') {
        if (!isset($assetModuleOptions[(string) $postedSettings['member_submission_author_reward_asset_module']])) {
            $errors[] = '작성자 보상 포인트/금액 항목이 올바르지 않습니다.';
        }
        if ($postedAuthorRewardAmount === null || (int) $postedSettings['member_submission_author_reward_amount'] < 1) {
            $errors[] = '작성자 보상 금액은 1 이상으로 입력하세요.';
        }
    } elseif (!empty($postedSettings['member_submission_author_reward_enabled'])) {
        $errors[] = '작성자 보상 포인트/금액 항목을 선택하세요.';
    }
    if (!$contentIdentityContentViewAvailable && !empty($postedSettings['identity_content_view_required'])) {
        $errors[] = '콘텐츠 열람 본인확인을 사용하려면 본인확인 사용을 켜고 콘텐츠 열람 목적을 지원하는 제공자를 설정하세요.';
        $postedSettings['identity_content_view_required'] = false;
    }
    if (!$contentIdentityContentViewAdultAvailable && !empty($postedSettings['identity_content_view_adult_required'])) {
        $errors[] = '콘텐츠 열람 성인 본인확인을 사용하려면 본인확인 사용, 생년월일 사용, 성인 열람 목적 제공자를 설정하세요.';
        $postedSettings['identity_content_view_adult_required'] = false;
    }
    if (!$contentIdentityAuthorApplicationAvailable && !empty($postedSettings['identity_author_application_required'])) {
        $errors[] = '작성자 신청 본인확인을 사용하려면 본인확인 사용을 켜고 작성자 신청 목적을 지원하는 제공자를 설정하세요.';
        $postedSettings['identity_author_application_required'] = false;
    }
    if (!$contentIdentityAuthorApplicationAdultAvailable && !empty($postedSettings['identity_author_application_adult_required'])) {
        $errors[] = '작성자 신청 성인 본인확인을 사용하려면 본인확인 사용, 생년월일 사용, 작성자 신청 성인 목적 제공자를 설정하세요.';
        $postedSettings['identity_author_application_adult_required'] = false;
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

        sr_admin_form_draft_delete($pdo, (int) $account['id'], $adminFormDraftKey, $adminFormDraftContext);
        $notice = '콘텐츠 환경설정을 저장했습니다.';
    } else {
        $settings = array_merge($settings, $postedSettings);
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/content/settings');
}
$adminFormDraft = sr_admin_form_draft_with_state(
    sr_admin_form_draft_get($pdo, (int) $account['id'], $adminFormDraftKey, $adminFormDraftContext),
    $adminFormDraftFingerprint
);
if (is_array($adminFormDraft)) {
    $contentDraftBooleanKeys = [
        'external_embed_enabled', 'internal_embed_enabled', 'plain_text_auto_link_urls', 'plain_text_auto_link_new_tab',
        'secret_comments_enabled', 'sidebar_enabled', 'business_info_visible', 'series_enabled', 'member_submission_enabled',
        'identity_content_view_required', 'identity_content_view_adult_required', 'identity_author_application_required',
        'identity_author_application_adult_required', 'member_submission_default_review_required',
        'member_submission_author_reward_enabled', 'multi_asset_payment_enabled', 'reaction_enabled',
    ];
    $settings = sr_admin_form_draft_apply_settings($settings, (array) $adminFormDraft['payload'], $contentDraftBooleanKeys);
    $contentDraftPayload = (array) $adminFormDraft['payload'];
    $settings['layout_extra_menu_keys_json'] = sr_content_layout_extra_menu_items_from_pair_values(
        $contentDraftPayload['layout_extra_menu_area_keys'] ?? [],
        $contentDraftPayload['layout_extra_menu_labels'] ?? [],
        $contentDraftPayload['layout_extra_menu_keys'] ?? []
    );
}

include SR_ROOT . '/modules/content/views/admin-settings.php';
