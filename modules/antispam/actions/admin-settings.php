<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/antispam/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/antispam/settings', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_antispam_settings($pdo);
$modeOptions = sr_antispam_mode_options();
$providerOptions = sr_antispam_provider_options($pdo);
$challengeTypeOptions = sr_antispam_challenge_type_options($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/antispam/settings', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent !== 'save_settings') {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    $postedSettings = [
        'enabled' => ($_POST['enabled'] ?? '') === '1',
        'default_mode' => sr_antispam_mode(sr_post_string('default_mode', 20)),
        'challenge_type' => sr_antispam_challenge_type(sr_post_string('challenge_type', 30)),
        'ttl_seconds' => sr_admin_post_int_in_range('ttl_seconds', 60, 3600),
        'min_submit_seconds' => sr_admin_post_int_in_range('min_submit_seconds', 0, 60),
        'provider_timeout_seconds' => sr_admin_post_int_in_range('provider_timeout_seconds', 1, 10),
        'provider_failure_policy' => sr_post_string('provider_failure_policy', 30),
        'verify_remote_ip_enabled' => ($_POST['verify_remote_ip_enabled'] ?? '') === '1',
        'surface_member_register' => sr_antispam_mode(sr_post_string('surface_member_register', 20)),
        'surface_community_post_guest' => sr_antispam_mode(sr_post_string('surface_community_post_guest', 20)),
        'surface_community_comment_guest' => sr_antispam_mode(sr_post_string('surface_community_comment_guest', 20)),
    ];

    if (!array_key_exists((string) $postedSettings['challenge_type'], $challengeTypeOptions)) {
        $errors[] = '검증 방식이 올바르지 않습니다.';
        $postedSettings['challenge_type'] = 'math';
    }

    foreach (['ttl_seconds', 'min_submit_seconds', 'provider_timeout_seconds'] as $numericKey) {
        if ($postedSettings[$numericKey] === null) {
            $errors[] = '자동등록방지 숫자 설정을 확인해 주세요.';
            $postedSettings[$numericKey] = (int) $settings[$numericKey];
        }
    }
    if (!in_array((string) $postedSettings['provider_failure_policy'], ['fail_closed', 'fallback_math'], true)) {
        $errors[] = '외부 provider 실패 정책이 올바르지 않습니다.';
        $postedSettings['provider_failure_policy'] = 'fail_closed';
    }
    foreach ($providerOptions as $providerKey => $provider) {
        $siteKeySetting = (string) $provider['site_key_setting'];
        $secretKeySetting = (string) $provider['secret_key_setting'];
        $postedSettings[$siteKeySetting] = trim(sr_post_string($siteKeySetting, 255));
        $scoreSetting = (string) ($provider['score_setting'] ?? '');
        if ($scoreSetting !== '') {
            $postedSettings[$scoreSetting] = trim(sr_post_string($scoreSetting, 20));
            if (!is_numeric($postedSettings[$scoreSetting]) || (float) $postedSettings[$scoreSetting] < 0 || (float) $postedSettings[$scoreSetting] > 1) {
                $errors[] = (string) $provider['label'] . ' 최소 점수는 0에서 1 사이로 입력해 주세요.';
                $postedSettings[$scoreSetting] = (string) ($settings[$scoreSetting] ?? '0.5');
            }
        }
        $secretInput = sr_post_string_without_truncation($secretKeySetting, 255);
        if ($secretInput === null) {
            $errors[] = 'provider secret key는 255자 이내로 입력해 주세요.';
            $postedSettings[$secretKeySetting] = (string) ($settings[$secretKeySetting] ?? '');
            continue;
        }
        $postedSettings[$secretKeySetting] = trim($secretInput) !== ''
            ? trim($secretInput)
            : (string) ($settings[$secretKeySetting] ?? '');
    }

    if ($postedSettings['enabled'] && (string) $postedSettings['challenge_type'] !== 'math') {
        $provider = $providerOptions[(string) $postedSettings['challenge_type']] ?? null;
        if (is_array($provider)) {
            if ((string) $postedSettings[(string) $provider['site_key_setting']] === '' || (string) $postedSettings[(string) $provider['secret_key_setting']] === '') {
                $errors[] = '선택한 외부 provider의 site key와 secret key를 입력해 주세요.';
            }
        } else {
            $errors[] = '선택한 외부 provider 플러그인을 활성화해 주세요.';
        }
    }

    if ($errors === []) {
        foreach ($providerOptions as $provider) {
            $scoreSetting = (string) ($provider['score_setting'] ?? '');
            if ($scoreSetting !== '' && isset($postedSettings[$scoreSetting])) {
                $postedSettings[$scoreSetting] = (string) min(1, max(0, (float) $postedSettings[$scoreSetting]));
            }
        }
        sr_antispam_save_settings($pdo, sr_antispam_normalize_settings($postedSettings, $providerOptions));
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'antispam.settings.updated',
            'target_type' => 'module',
            'target_id' => 'antispam',
            'result' => 'success',
            'message' => 'Antispam settings updated.',
            'metadata' => [
                'enabled' => (bool) $postedSettings['enabled'],
                'challenge_type' => (string) $postedSettings['challenge_type'],
                'provider_failure_policy' => (string) $postedSettings['provider_failure_policy'],
            ],
        ]);
        $notice = '자동등록방지 설정을 저장했습니다.';
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/antispam/settings');
}

include SR_ROOT . '/modules/antispam/views/admin-settings.php';
