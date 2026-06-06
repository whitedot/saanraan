<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

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
$publicLayoutOptions = sr_public_layout_options($pdo);
$siteMenuOptions = [];
if (sr_module_enabled($pdo, 'site_menu') && is_file(SR_ROOT . '/modules/site_menu/helpers.php')) {
    require_once SR_ROOT . '/modules/site_menu/helpers.php';
    $siteMenuOptions = sr_site_menu_options($pdo);
}

if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/settings', 'edit');
    sr_require_csrf();

    $postedEditorInput = sr_post_string('editor', 30);
    $postedOnceHistoryPolicyInput = sr_post_string('once_history_policy', 40);
    $postedAuthorRewardEnabled = sr_post_string('member_submission_author_reward_enabled', 1) === '1';
    $postedAuthorRewardAmount = sr_admin_post_int_in_range('member_submission_author_reward_amount', 0, 999999999);
    $postedSettings = [
        'editor' => sr_editor_normalize_key($postedEditorInput),
        'once_history_policy' => sr_content_once_history_policy($postedOnceHistoryPolicyInput),
        'layout_key' => sr_public_layout_normalize_key(sr_post_string('layout_key', 80)),
        'layout_primary_menu_key' => sr_content_clean_layout_menu_key(sr_post_string('layout_primary_menu_key', 60)),
        'layout_secondary_menu_key' => sr_content_clean_layout_menu_key(sr_post_string('layout_secondary_menu_key', 60)),
        'layout_tertiary_menu_key' => sr_content_clean_layout_menu_key(sr_post_string('layout_tertiary_menu_key', 60)),
        'layout_quaternary_menu_key' => sr_content_clean_layout_menu_key(sr_post_string('layout_quaternary_menu_key', 60)),
        'layout_quinary_menu_key' => sr_content_clean_layout_menu_key(sr_post_string('layout_quinary_menu_key', 60)),
        'member_submission_enabled' => sr_post_string('member_submission_enabled', 1) === '1',
        'member_submission_default_review_required' => sr_post_string('member_submission_default_review_required', 1) === '1',
        'member_submission_author_reward_enabled' => $postedAuthorRewardEnabled,
        'member_submission_author_reward_asset_module' => sr_content_clean_slug(sr_post_string('member_submission_author_reward_asset_module', 30)),
        'member_submission_author_reward_amount' => $postedAuthorRewardAmount ?? 0,
    ];

    if ($postedEditorInput !== (string) $postedSettings['editor'] || !array_key_exists((string) $postedSettings['editor'], $editorOptions)) {
        $errors[] = '본문 에디터 값이 올바르지 않습니다.';
    }
    if ($postedOnceHistoryPolicyInput !== (string) $postedSettings['once_history_policy']) {
        $errors[] = '기존 이용자 재결제 기준 값이 올바르지 않습니다.';
    }
    if (!isset($publicLayoutOptions[(string) $postedSettings['layout_key']])) {
        $errors[] = '기본 콘텐츠 레이아웃 값이 올바르지 않습니다.';
    }
    foreach (sr_content_layout_menu_slots() as $menuSettingKey) {
        $menuKey = (string) $postedSettings[$menuSettingKey];
        if ($menuKey !== '' && !isset($siteMenuOptions[$menuKey])) {
            $errors[] = '레이아웃 사이트 메뉴 값이 올바르지 않습니다.';
            break;
        }
    }
    if (!empty($postedSettings['member_submission_author_reward_enabled'])) {
        if ((string) $postedSettings['member_submission_author_reward_asset_module'] === '' || !isset($assetModuleOptions[(string) $postedSettings['member_submission_author_reward_asset_module']])) {
            $errors[] = '작성자 리워드 자산 항목이 올바르지 않습니다.';
        }
        if ($postedAuthorRewardAmount === null || (int) $postedSettings['member_submission_author_reward_amount'] < 1) {
            $errors[] = '작성자 리워드 금액은 1 이상으로 입력하세요.';
        }
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
