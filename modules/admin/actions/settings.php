<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
$canManageAdvancedSettings = sr_admin_has_role($pdo, (int) $account['id'], ['owner']);

$errors = [];
$notice = '';
$allowedSettingTypes = sr_admin_settings_allowed_types();
$reservedSiteSettingKeys = sr_admin_reserved_site_setting_keys();
$values = sr_admin_site_setting_values($site ?? null);
$adminSettings = sr_admin_settings($pdo);
$adminSkinOptions = sr_admin_skin_options();
$adminSkinKey = sr_admin_skin_key($adminSettings);

if (sr_request_method() === 'POST' && sr_post_string('intent', 40) === 'admin_skin') {
    sr_require_csrf();
    $postedSkinKey = sr_post_string('admin_skin_key', 40);
    if (!isset($adminSkinOptions[$postedSkinKey])) {
        $errors[] = '관리자 스킨 값이 올바르지 않습니다.';
    }

    if ($errors === []) {
        sr_admin_save_skin_key($pdo, $postedSkinKey);
        $adminSettings = sr_admin_settings($pdo);
        $adminSkinKey = sr_admin_skin_key($adminSettings);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'admin.settings.updated',
            'target_type' => 'module',
            'target_id' => 'admin',
            'result' => 'success',
            'message' => 'Admin settings updated.',
            'metadata' => [
                'admin_skin_key' => $adminSkinKey,
            ],
        ]);
        $notice = '관리자 설정을 저장했습니다.';
    }
} elseif (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_settings_post(
        $pdo,
        $account,
        $site ?? null,
        $canManageAdvancedSettings,
        $allowedSettingTypes,
        $reservedSiteSettingKeys
    );
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    $values = $postResult['values'];
    $site = is_array($postResult['site']) ? $postResult['site'] : ($site ?? null);
}

$siteSettings = sr_admin_site_settings($pdo);

include SR_ROOT . '/modules/admin/views/settings.php';
