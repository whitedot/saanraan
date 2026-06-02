<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/ckeditor/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/ckeditor/settings', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_ckeditor_settings($pdo);
$assetModeOptions = sr_ckeditor_asset_mode_options();
$toolbarPresets = sr_ckeditor_toolbar_presets();

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/ckeditor/settings', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent !== 'save_settings') {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    $postedSettings = [
        'asset_mode' => sr_post_string('asset_mode', 30),
        'cdn_version' => sr_post_string('cdn_version', 20),
        'license_key' => sr_post_string_without_truncation('license_key', 255),
        'toolbar_preset' => sr_post_string('toolbar_preset', 60),
    ];

    if (!isset($assetModeOptions[$postedSettings['asset_mode']])) {
        $errors[] = '에셋 로딩 방식이 올바르지 않습니다.';
    }
    if (sr_ckeditor_clean_version((string) $postedSettings['cdn_version']) !== (string) $postedSettings['cdn_version']) {
        $errors[] = 'CDN 버전은 48.1.0 형식으로 입력해 주세요.';
    }
    if (!isset($toolbarPresets[$postedSettings['toolbar_preset']])) {
        $errors[] = '툴바 구성이 올바르지 않습니다.';
    }
    if (
        (string) $postedSettings['asset_mode'] === 'cdn'
        && sr_ckeditor_clean_license_key((string) $postedSettings['license_key']) === 'GPL'
    ) {
        $errors[] = 'GPL 라이선스 키는 직접 호스팅 방식에서만 사용할 수 있습니다.';
    }

    if ($errors === []) {
        sr_ckeditor_save_settings($pdo, $postedSettings);
        $settings = sr_ckeditor_settings($pdo);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'ckeditor.settings.updated',
            'target_type' => 'module',
            'target_id' => 'ckeditor',
            'result' => 'success',
            'message' => 'CKEditor settings updated.',
            'metadata' => [
                'asset_mode' => (string) $settings['asset_mode'],
                'toolbar_preset' => (string) $settings['toolbar_preset'],
            ],
        ]);
        $notice = 'CKEditor 설정을 저장했습니다.';
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/ckeditor/settings');
}

include SR_ROOT . '/modules/ckeditor/views/admin-settings.php';
