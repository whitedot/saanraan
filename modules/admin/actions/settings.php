<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$errors = [];
$notice = '';
$values = sr_admin_site_setting_values($site ?? null);
$adminSettings = sr_admin_settings($pdo);
$adminSkinOptions = sr_admin_skin_options();
$adminSkinKey = sr_admin_skin_key($adminSettings);
$values = array_merge($values, ['admin_skin_key' => $adminSkinKey]);

if (sr_request_method() === 'POST' && sr_post_string('intent', 40) === 'site') {
    sr_require_csrf();
    $postedSkinKey = sr_post_string('admin_skin_key', 40);
    if (!isset($adminSkinOptions[$postedSkinKey])) {
        $errors[] = '관리자 스킨 값이 올바르지 않습니다.';
        $values = sr_admin_post_site_setting_values($site ?? null);
    }

    if ($errors === []) {
        $postResult = sr_admin_handle_settings_post(
            $pdo,
            $account,
            $site ?? null
        );
        $errors = $postResult['errors'];
        $notice = (string) $postResult['notice'];
        $values = $postResult['values'];
        $site = is_array($postResult['site']) ? $postResult['site'] : ($site ?? null);

        if ($errors === []) {
            $previousSkinKey = $adminSkinKey;
            if ($postedSkinKey !== $previousSkinKey) {
                sr_admin_save_skin_key($pdo, $postedSkinKey);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'admin.settings.updated',
                    'target_type' => 'module',
                    'target_id' => 'admin',
                    'result' => 'success',
                    'message' => 'Admin settings updated.',
                    'metadata' => [
                        'before' => [
                            'admin_skin_key' => $previousSkinKey,
                        ],
                        'after' => [
                            'admin_skin_key' => $postedSkinKey,
                        ],
                    ],
                ]);
            }

            $adminSettings = sr_admin_settings($pdo);
            $adminSkinKey = sr_admin_skin_key($adminSettings);
            $notice = '설정을 저장했습니다.';
        } else {
            $adminSkinKey = $postedSkinKey;
        }
    }
} elseif (sr_request_method() === 'POST') {
    sr_require_csrf();
    $errors[] = '사이트 설정 작업 값이 올바르지 않습니다.';
}

include SR_ROOT . '/modules/admin/views/settings.php';
