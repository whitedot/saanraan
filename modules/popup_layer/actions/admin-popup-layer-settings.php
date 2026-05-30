<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/popup_layer/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/popup-layers/settings', 'view');

$errors = [];
$notice = '';
$allowedStatuses = ['draft', 'enabled', 'disabled'];
$allowedMatchTypes = ['all', 'exact'];
$availableTargets = sr_popup_layer_available_targets($pdo);
$popupLayerSettings = sr_popup_layer_settings($pdo);
$popupLayerSkinOptions = sr_popup_layer_skin_options();
$popupLayerSkinKey = sr_popup_layer_skin_key($popupLayerSettings);
$popupLayerDefaultStatus = sr_popup_layer_default_status($popupLayerSettings);
$popupLayerDefaultTargetOption = sr_popup_layer_default_target_option($popupLayerSettings, $availableTargets);
$popupLayerDefaultMatchType = sr_popup_layer_default_match_type($popupLayerSettings);
$popupLayerDefaultDismissCookieDays = sr_popup_layer_default_dismiss_cookie_days($popupLayerSettings);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/popup-layers/settings', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent !== 'save_settings') {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    $postedSkinKey = sr_post_string('popup_layer_skin_key', 40);
    $postedDefaultStatus = sr_post_string('popup_layer_default_status', 30);
    $postedDefaultTargetOption = sr_post_string('popup_layer_default_target_option', 300);
    $postedDefaultMatchType = sr_post_string('popup_layer_default_match_type', 20);
    $postedDefaultDismissCookieDays = max(0, min(365, (int) sr_post_string('popup_layer_default_dismiss_cookie_days', 5)));
    if ($errors === []) {
        if (!isset($popupLayerSkinOptions[$postedSkinKey])) {
            $errors[] = '팝업레이어 스킨 값이 올바르지 않습니다.';
        }
        if (!in_array($postedDefaultStatus, $allowedStatuses, true)) {
            $errors[] = '팝업레이어 기본 상태 값이 올바르지 않습니다.';
        }
        if (!sr_popup_layer_is_public_target_option($postedDefaultTargetOption) && sr_popup_layer_find_target($availableTargets, $postedDefaultTargetOption) === null) {
            $errors[] = '팝업레이어 기본 노출 대상 값이 올바르지 않습니다.';
        }
        if (!in_array($postedDefaultMatchType, $allowedMatchTypes, true)) {
            $errors[] = '팝업레이어 기본 매칭 방식이 올바르지 않습니다.';
        }
    }

    if ($errors === []) {
        sr_popup_layer_save_settings($pdo, [
            'popup_layer_skin_key' => $postedSkinKey,
            'popup_layer_default_status' => $postedDefaultStatus,
            'popup_layer_default_target_option' => $postedDefaultTargetOption,
            'popup_layer_default_match_type' => $postedDefaultMatchType,
            'popup_layer_default_dismiss_cookie_days' => $postedDefaultDismissCookieDays,
        ]);
        $popupLayerSettings = sr_popup_layer_settings($pdo);
        $popupLayerSkinKey = sr_popup_layer_skin_key($popupLayerSettings);
        $popupLayerDefaultStatus = sr_popup_layer_default_status($popupLayerSettings);
        $popupLayerDefaultTargetOption = sr_popup_layer_default_target_option($popupLayerSettings, $availableTargets);
        $popupLayerDefaultMatchType = sr_popup_layer_default_match_type($popupLayerSettings);
        $popupLayerDefaultDismissCookieDays = sr_popup_layer_default_dismiss_cookie_days($popupLayerSettings);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'popup_layer.settings.updated',
            'target_type' => 'module',
            'target_id' => 'popup_layer',
            'result' => 'success',
            'message' => 'Popup layer settings updated.',
            'metadata' => [
                'popup_layer_skin_key' => $popupLayerSkinKey,
                'popup_layer_default_status' => $popupLayerDefaultStatus,
                'popup_layer_default_target_option' => $popupLayerDefaultTargetOption,
                'popup_layer_default_match_type' => $popupLayerDefaultMatchType,
                'popup_layer_default_dismiss_cookie_days' => $popupLayerDefaultDismissCookieDays,
            ],
        ]);
        $notice = '팝업레이어 설정을 저장했습니다.';
    }
}

include SR_ROOT . '/modules/popup_layer/views/admin-popup-layer-settings.php';
