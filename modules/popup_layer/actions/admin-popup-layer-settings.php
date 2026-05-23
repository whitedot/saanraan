<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/popup_layer/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/popup-layers/settings', 'view');

$errors = [];
$notice = '';
$popupLayerSettings = sr_popup_layer_settings($pdo);
$popupLayerSkinOptions = sr_popup_layer_skin_options();
$popupLayerSkinKey = sr_popup_layer_skin_key($popupLayerSettings);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/popup-layers/settings', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent !== 'save_settings') {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    $postedSkinKey = sr_post_string('popup_layer_skin_key', 40);
    if ($errors === []) {
        if (!isset($popupLayerSkinOptions[$postedSkinKey])) {
            $errors[] = '팝업레이어 스킨 값이 올바르지 않습니다.';
        }
    }

    if ($errors === []) {
        sr_popup_layer_save_skin_key($pdo, $postedSkinKey);
        $popupLayerSettings = sr_popup_layer_settings($pdo);
        $popupLayerSkinKey = sr_popup_layer_skin_key($popupLayerSettings);
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
            ],
        ]);
        $notice = '팝업레이어 설정을 저장했습니다.';
    }
}

include SR_ROOT . '/modules/popup_layer/views/admin-popup-layer-settings.php';
