<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/banner/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners/settings', 'view');

$errors = [];
$notice = '';
$bannerSettings = sr_banner_settings($pdo);
$bannerSkinOptions = sr_banner_skin_options();
$bannerSkinKey = sr_banner_skin_key($bannerSettings);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners/settings', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent !== 'save_settings') {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    $postedSkinKey = sr_post_string('banner_skin_key', 40);
    if ($errors === []) {
        if (!isset($bannerSkinOptions[$postedSkinKey])) {
            $errors[] = '배너 스킨 값이 올바르지 않습니다.';
        }
    }

    if ($errors === []) {
        sr_banner_save_skin_key($pdo, $postedSkinKey);
        $bannerSettings = sr_banner_settings($pdo);
        $bannerSkinKey = sr_banner_skin_key($bannerSettings);
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
            ],
        ]);
        $notice = '배너 설정을 저장했습니다.';
    }
}

include SR_ROOT . '/modules/banner/views/admin-banner-settings.php';
