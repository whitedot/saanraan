<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
$contentReactionAvailable = sr_module_enabled($pdo, 'reaction')
    && is_file(SR_ROOT . '/modules/reaction/helpers.php');
if ($contentReactionAvailable) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}
if (sr_module_enabled($pdo, 'identity_verification') && is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}
$contentIdentityVerificationAvailable = sr_module_enabled($pdo, 'identity_verification')
    && is_file(SR_ROOT . '/modules/identity_verification/helpers.php');

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/settings', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_content_settings($pdo);
$assetModuleOptions = sr_content_asset_module_options($pdo);
$editorOptions = sr_editor_options($pdo);
$toolbarPresetOptions = sr_content_toolbar_preset_options();
$reactionPresetOptions = $contentReactionAvailable && function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$publicLayoutOptions = sr_content_layout_options($pdo);
$publicThemeOptions = sr_content_theme_options();
$siteMenuOptions = [];
if (sr_module_enabled($pdo, 'site_menu') && is_file(SR_ROOT . '/modules/site_menu/helpers.php')) {
    require_once SR_ROOT . '/modules/site_menu/helpers.php';
    $siteMenuOptions = sr_site_menu_options($pdo);
}

if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/settings', 'edit');
    sr_require_csrf();

    $postedEditorInput = sr_post_string('editor', 30);
    $postedToolbarPresetInput = sr_post_string('editor_toolbar_preset', 80);
    $postedToolbarPreset = sr_content_toolbar_preset_key($postedToolbarPresetInput);
    $postedOnceHistoryPolicyInput = sr_post_string('once_history_policy', 40);
    $postedAuthorRewardEnabled = sr_post_string('member_submission_author_reward_enabled', 1) === '1';
    $postedAuthorRewardAssetModule = sr_content_clean_slug(sr_post_string('member_submission_author_reward_asset_module', 30));
    $postedAuthorRewardAmount = sr_admin_post_int_in_range('member_submission_author_reward_amount', 0, 999999999);
    $postedReactionEnabled = sr_post_string('reaction_enabled', 1) === '1';
    $postedReactionPresetInput = sr_post_string('reaction_preset_key', 80);
    $postedReactionCommentPresetInput = sr_post_string('reaction_comment_preset_key', 80);
    $postedThemeKey = sr_view_theme_post_key(sr_post_string('theme_key', 80));
    $postedSettings = [
        'editor' => sr_editor_effective_key($pdo, sr_editor_normalize_key($postedEditorInput)),
        'editor_toolbar_preset' => $postedToolbarPreset,
        'embed_enabled' => sr_post_string('embed_enabled', 1) === '1',
        'plain_text_auto_link_urls' => sr_post_string('plain_text_auto_link_urls', 1) === '1',
        'secret_comments_enabled' => sr_post_string('secret_comments_enabled', 1) === '1',
        'once_history_policy' => sr_content_once_history_policy($postedOnceHistoryPolicyInput),
        'layout_key' => sr_public_layout_normalize_key(sr_post_string('layout_key', 80)),
        'theme_key' => $postedThemeKey,
        'layout_primary_menu_key' => sr_content_clean_layout_menu_key(sr_post_string('layout_primary_menu_key', 60)),
        'layout_extra_menu_keys_json' => sr_content_layout_extra_menu_items_from_pair_values($_POST['layout_extra_menu_area_keys'] ?? [], $_POST['layout_extra_menu_labels'] ?? [], $_POST['layout_extra_menu_keys'] ?? []),
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

    if ($postedEditorInput !== (string) $postedSettings['editor'] || !array_key_exists((string) $postedSettings['editor'], $editorOptions)) {
        $errors[] = '본문 에디터 값이 올바르지 않습니다.';
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
    if ((string) $postedSettings['member_submission_author_reward_asset_module'] !== '') {
        if (!isset($assetModuleOptions[(string) $postedSettings['member_submission_author_reward_asset_module']])) {
            $errors[] = '작성자 리워드 포인트/금액 항목이 올바르지 않습니다.';
        }
        if ($postedAuthorRewardAmount === null || (int) $postedSettings['member_submission_author_reward_amount'] < 1) {
            $errors[] = '작성자 리워드 금액은 1 이상으로 입력하세요.';
        }
    } elseif (!empty($postedSettings['member_submission_author_reward_enabled'])) {
        $errors[] = '작성자 리워드 포인트/금액 항목을 선택하세요.';
    }
    if (!$contentIdentityVerificationAvailable) {
        if (
            !empty($postedSettings['identity_content_view_required'])
            || !empty($postedSettings['identity_content_view_adult_required'])
            || !empty($postedSettings['identity_author_application_required'])
            || !empty($postedSettings['identity_author_application_adult_required'])
        ) {
            $errors[] = '콘텐츠 본인확인 설정을 사용하려면 본인확인 모듈을 먼저 설치하고 활성화하세요.';
        }
    } elseif (function_exists('sr_identity_verification_adult_setting_errors')) {
        $errors = array_merge($errors, sr_identity_verification_adult_setting_errors($pdo, !empty($postedSettings['identity_content_view_adult_required']), '콘텐츠 열람 성인 본인확인'));
        $errors = array_merge($errors, sr_identity_verification_adult_setting_errors($pdo, !empty($postedSettings['identity_author_application_adult_required']), '작성자 신청 성인 본인확인'));
    } elseif (!empty($postedSettings['identity_content_view_adult_required']) || !empty($postedSettings['identity_author_application_adult_required'])) {
        $errors[] = '성인 본인확인을 사용하려면 본인확인 모듈을 활성화해야 합니다.';
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

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/content/settings');
}

include SR_ROOT . '/modules/content/views/admin-settings.php';
