<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/identity_verification/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/identity-providers', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_identity_verification_settings($pdo);
$providers = sr_identity_verification_providers($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/identity-providers', 'edit');

    $enabled = ($_POST['enabled'] ?? '') === '1';
    $attemptTtl = sr_admin_post_int_in_range('attempt_ttl_seconds', 60, 3600);
    $resultValidDays = sr_admin_post_int_in_range('result_valid_days', 0, 3650);
    $defaultProviderKey = sr_identity_verification_provider_key(sr_post_string('default_provider_key', 60));
    $postedSettings = [
        'enabled' => $enabled,
        'default_provider_key' => $defaultProviderKey,
        'attempt_ttl_seconds' => $attemptTtl ?? (int) $settings['attempt_ttl_seconds'],
        'result_valid_days' => $resultValidDays ?? (int) $settings['result_valid_days'],
        'require_https' => ($_POST['require_https'] ?? '') === '1',
    ];
    if ($attemptTtl === null || $resultValidDays === null) {
        $errors[] = '본인확인 유효 시간 설정을 확인해 주세요.';
    }
    if ($defaultProviderKey !== '' && !isset($providers[$defaultProviderKey])) {
        $errors[] = '기본 제공자를 확인해 주세요.';
        $postedSettings['default_provider_key'] = '';
    }

    $enabledProviders = [];
    foreach ($providers as $providerKey => $provider) {
        $providerLabel = (string) ($provider['display_name'] ?? $providerKey);
        $enabledKey = sr_identity_verification_setting_key((string) $providerKey, 'enabled');
        $environmentKey = sr_identity_verification_setting_key((string) $providerKey, 'environment');
        $sortOrderKey = sr_identity_verification_setting_key((string) $providerKey, 'sort_order');
        $providerEnabled = ($_POST[$enabledKey] ?? '') === '1';
        $environment = sr_post_string($environmentKey, 20);
        $sortOrder = sr_admin_post_int_in_range($sortOrderKey, -9999, 9999, 6);

        $postedSettings[$enabledKey] = $providerEnabled;
        $postedSettings[$environmentKey] = in_array($environment, ['test', 'production'], true) ? $environment : 'test';
        $postedSettings[$sortOrderKey] = $sortOrder ?? (int) ($provider['sort_order'] ?? 0);
        if ($sortOrder === null) {
            $errors[] = $providerLabel . ' 정렬값을 확인해 주세요.';
        }
        if ($providerEnabled) {
            $enabledProviders[] = (string) $providerKey;
        }

        foreach ((array) ($provider['settings_schema'] ?? []) as $settingKey => $definition) {
            if (!is_string($settingKey) || !is_array($definition)) {
                continue;
            }
            $storedKey = sr_identity_verification_setting_key((string) $providerKey, $settingKey);
            if ($storedKey === '') {
                continue;
            }
            $currentValue = sr_identity_verification_provider_setting($provider, $settingKey);
            $postedValue = sr_post_string_without_truncation($storedKey, 2000);
            if ($postedValue === null) {
                $errors[] = $providerLabel . ' 설정값을 확인해 주세요.';
                $postedValue = $currentValue;
            }
            $postedValue = trim((string) $postedValue);
            $isSecret = !empty($definition['secret']);
            $required = !empty($definition['required']);
            if ($isSecret && $postedValue === '') {
                $postedValue = $currentValue;
            }
            if ($providerEnabled && $required && $postedValue === '') {
                $errors[] = $providerLabel . '의 ' . (string) ($definition['label'] ?? $settingKey) . ' 값을 입력해 주세요.';
            }
            if (!$isSecret || $postedValue !== $currentValue) {
                $postedSettings[$storedKey] = $postedValue;
            }
        }
    }

    if ($enabled && $enabledProviders === []) {
        $errors[] = '본인확인을 사용하려면 하나 이상의 제공자를 활성화해야 합니다.';
    }

    if ($errors === []) {
        sr_identity_verification_save_settings($pdo, $postedSettings);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'identity_verification.settings.updated',
            'target_type' => 'module',
            'target_id' => 'identity_verification',
            'result' => 'success',
            'message' => 'Identity verification settings updated.',
            'metadata' => [
                'enabled' => $enabled,
                'enabled_providers' => $enabledProviders,
                'default_provider_key' => $postedSettings['default_provider_key'],
            ],
        ]);
        $notice = '본인확인 제공자 설정을 저장했습니다.';
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/identity-providers');
}

$settings = sr_identity_verification_settings($pdo);
$providers = sr_identity_verification_providers($pdo);

include SR_ROOT . '/modules/identity_verification/views/admin-providers.php';
