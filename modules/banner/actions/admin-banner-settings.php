<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/banner/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners/settings', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$allowedStatuses = ['draft', 'enabled', 'disabled'];
$allowedMatchTypes = ['all', 'exact'];
$availableTargets = sr_banner_available_targets($pdo);
$bannerSettings = sr_banner_settings($pdo);
$bannerSkinOptions = sr_banner_skin_options();
$bannerSkinKey = sr_banner_skin_key($bannerSettings);
$bannerDefaultStatus = sr_banner_default_status($bannerSettings);
$bannerDefaultTargetOption = sr_banner_default_target_option($bannerSettings, $availableTargets);
$bannerDefaultMatchType = sr_banner_default_match_type($bannerSettings);
$bannerDefaultSortOrder = sr_banner_default_sort_order($bannerSettings);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners/settings', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent !== 'save_settings') {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    $postedSkinKey = sr_post_string('banner_skin_key', 40);
    $postedDefaultStatus = sr_post_string('banner_default_status', 30);
    $postedDefaultTargetResult = sr_banner_normalize_posted_target_option(
        $availableTargets,
        sr_post_string('banner_default_target_service_key', 120),
        sr_post_string('banner_default_target_detail_option', 300),
        sr_post_string('banner_default_target_option', 300)
    );
    $postedDefaultTargetOption = (string) $postedDefaultTargetResult['option'];
    $postedDefaultTarget = is_array($postedDefaultTargetResult['target']) ? $postedDefaultTargetResult['target'] : null;
    $postedDefaultMatchType = sr_post_string('banner_default_match_type', 20);
    $postedDefaultSortOrder = max(-100000, min(100000, (int) sr_post_string('banner_default_sort_order', 20)));
    if ($errors === []) {
        if (!isset($bannerSkinOptions[$postedSkinKey])) {
            $errors[] = '배너 스킨 값이 올바르지 않습니다.';
        }
        if (!in_array($postedDefaultStatus, $allowedStatuses, true)) {
            $errors[] = '배너 기본 상태 값이 올바르지 않습니다.';
        }
        if (!sr_banner_is_public_target_option($postedDefaultTargetOption) && $postedDefaultTarget === null) {
            $errors[] = (string) $postedDefaultTargetResult['error'] !== '' ? (string) $postedDefaultTargetResult['error'] : '배너 기본 노출 위치 값이 올바르지 않습니다.';
        }
        if (!in_array($postedDefaultMatchType, $allowedMatchTypes, true)) {
            $errors[] = '배너 기본 매칭 방식이 올바르지 않습니다.';
        }
        if (sr_banner_is_public_target_option($postedDefaultTargetOption)) {
            $postedDefaultMatchType = 'all';
        }
        if (($postedDefaultTarget !== null || sr_banner_is_public_target_option($postedDefaultTargetOption)) && !sr_banner_skin_supports($postedSkinKey, sr_banner_target_placement_kind($postedDefaultTarget, sr_banner_is_public_target_option($postedDefaultTargetOption)))) {
            $errors[] = '배너 기본 스킨은 기본 노출 위치와 호환되어야 합니다.';
        }
    }

    if ($errors === []) {
        sr_banner_save_settings($pdo, [
            'banner_skin_key' => $postedSkinKey,
            'banner_default_status' => $postedDefaultStatus,
            'banner_default_target_option' => $postedDefaultTargetOption,
            'banner_default_match_type' => $postedDefaultMatchType,
            'banner_default_sort_order' => $postedDefaultSortOrder,
        ]);
        $bannerSettings = sr_banner_settings($pdo);
        $bannerSkinKey = sr_banner_skin_key($bannerSettings);
        $bannerDefaultStatus = sr_banner_default_status($bannerSettings);
        $bannerDefaultTargetOption = sr_banner_default_target_option($bannerSettings, $availableTargets);
        $bannerDefaultMatchType = sr_banner_default_match_type($bannerSettings);
        $bannerDefaultSortOrder = sr_banner_default_sort_order($bannerSettings);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'banner.settings.updated',
            'target_type' => 'module',
            'target_id' => 'banner',
            'result' => 'success',
            'message' => 'Banner settings updated.',
            'metadata' => [
                'banner_skin_key' => $bannerSkinKey,
                'banner_default_status' => $bannerDefaultStatus,
                'banner_default_target_option' => $bannerDefaultTargetOption,
                'banner_default_match_type' => $bannerDefaultMatchType,
                'banner_default_sort_order' => $bannerDefaultSortOrder,
            ],
        ]);
        $notice = '배너 설정을 저장했습니다.';
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/banners/settings');
}

include SR_ROOT . '/modules/banner/views/admin-banner-settings.php';
