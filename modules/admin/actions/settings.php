<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/settings', 'view');

$errors = [];
$notice = '';
$values = sr_admin_site_setting_values($site ?? null, $pdo);
$adminSettings = sr_admin_settings($pdo);
$adminSkinOptions = sr_admin_skin_options();
$adminSkinKey = sr_admin_skin_key($adminSettings);
$adminColorScheme = sr_admin_color_scheme($adminSettings);
$timezoneOptions = timezone_identifiers_list();
$localeOptions = sr_available_locale_options($site ?? null);
$values = array_merge($values, [
    'admin_skin_key' => $adminSkinKey,
    'admin_color_scheme' => $adminColorScheme,
]);

if (sr_request_method() === 'POST' && sr_post_string('intent', 40) === 'site') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/settings', 'edit');
    sr_require_csrf();
    $postedSkinKey = sr_post_string('admin_skin_key', 40);
    $postedColorScheme = sr_post_string('admin_color_scheme', 20);
    if (!isset($adminSkinOptions[$postedSkinKey])) {
        $errors[] = '관리자 스킨 값이 올바르지 않습니다.';
    }

    if (!isset(sr_color_scheme_options()[$postedColorScheme])) {
        $errors[] = '관리자 색상 모드 값이 올바르지 않습니다.';
    }

    if ($errors !== []) {
        $values = array_merge(sr_admin_post_site_setting_values($site ?? null), [
            'admin_skin_key' => $postedSkinKey,
            'admin_color_scheme' => $postedColorScheme,
        ]);
        $adminSkinKey = $postedSkinKey;
        $adminColorScheme = $postedColorScheme;
    }

    if ($errors === []) {
        $postResult = sr_admin_handle_settings_post(
            $pdo,
            $account,
            $site ?? null
        );
        $errors = $postResult['errors'];
        $notice = (string) $postResult['notice'];
        $values = array_merge($postResult['values'], [
            'admin_skin_key' => $postedSkinKey,
            'admin_color_scheme' => $postedColorScheme,
        ]);
        $site = is_array($postResult['site']) ? $postResult['site'] : ($site ?? null);

        if ($errors === []) {
            $previousSkinKey = $adminSkinKey;
            $previousColorScheme = $adminColorScheme;
            $adminSettingsBefore = [
                'admin_skin_key' => $previousSkinKey,
                'admin_color_scheme' => $previousColorScheme,
            ];
            $adminSettingsAfter = [
                'admin_skin_key' => $postedSkinKey,
                'admin_color_scheme' => $postedColorScheme,
            ];

            if ($postedSkinKey !== $previousSkinKey) {
                sr_admin_save_skin_key($pdo, $postedSkinKey);
            }

            if ($postedColorScheme !== $previousColorScheme) {
                sr_admin_save_color_scheme($pdo, $postedColorScheme);
            }

            if ($adminSettingsAfter !== $adminSettingsBefore) {
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'admin.settings.updated',
                    'target_type' => 'module',
                    'target_id' => 'admin',
                    'result' => 'success',
                    'message' => 'Admin settings updated.',
                    'metadata' => [
                        'before' => $adminSettingsBefore,
                        'after' => $adminSettingsAfter,
                    ],
                ]);
            }

            $adminSettings = sr_admin_settings($pdo);
            $adminSkinKey = sr_admin_skin_key($adminSettings);
            $adminColorScheme = sr_admin_color_scheme($adminSettings);
            $values = array_merge($values, [
                'admin_skin_key' => $adminSkinKey,
                'admin_color_scheme' => $adminColorScheme,
            ]);
            $notice = '설정을 저장했습니다.';
        } else {
            $adminSkinKey = $postedSkinKey;
            $adminColorScheme = $postedColorScheme;
        }
    }
} elseif (sr_request_method() === 'POST') {
    sr_require_csrf();
    $errors[] = '사이트 설정 작업 값이 올바르지 않습니다.';
}

$localeOptions = sr_available_locale_options($site ?? null);
$homepageCandidates = sr_admin_homepage_candidate_options($pdo, (string) ($values['home_path'] ?? '/'));
$currentHomepageAvailable = sr_site_home_path_is_available($pdo, (string) ($values['home_path'] ?? '/'));

include SR_ROOT . '/modules/admin/views/settings.php';
