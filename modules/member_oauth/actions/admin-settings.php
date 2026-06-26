<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/member_oauth/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_owner($pdo, (int) $account['id']);
$settings = sr_member_oauth_settings($pdo);
$providers = sr_member_oauth_providers($pdo);
$memberSettings = sr_member_settings($pdo);
$profileExtraFieldDefinitions = sr_member_profile_extra_field_definitions($memberSettings);
$notice = '';
$errors = [];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    if ($intent !== 'save_settings') {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    $mockLabel = trim(sr_post_string('mock_label', 80));
    $stateTtl = sr_admin_post_int_in_range('state_ttl_seconds', 60, 3600);
    $completionTtl = sr_admin_post_int_in_range('completion_ttl_seconds', 60, 3600);
    $postedSettings = [
        'mock_enabled' => ($_POST['mock_enabled'] ?? '') === '1',
        'mock_label' => $mockLabel,
        'state_ttl_seconds' => $stateTtl ?? (int) $settings['state_ttl_seconds'],
        'completion_ttl_seconds' => $completionTtl ?? (int) $settings['completion_ttl_seconds'],
    ];
    if ($mockLabel === '') {
        $errors[] = 'Mock provider 라벨을 입력해 주세요.';
        $postedSettings['mock_label'] = (string) $settings['mock_label'];
    }
    if ($stateTtl === null || $completionTtl === null) {
        $errors[] = 'OAuth state 유효 시간을 확인해 주세요.';
    }

    $enabledProviders = [];
    foreach ($providers as $providerKey => $provider) {
        if (!empty($provider['mock'])) {
            continue;
        }
        $providerKey = sr_member_oauth_provider_key((string) $providerKey);
        if ($providerKey === '') {
            continue;
        }

        $enabledKey = sr_member_oauth_provider_setting_key($providerKey, 'enabled');
        $labelKey = sr_member_oauth_provider_setting_key($providerKey, 'label');
        $clientIdKey = sr_member_oauth_provider_setting_key($providerKey, 'client_id');
        $secretKey = sr_member_oauth_provider_setting_key($providerKey, 'client_secret');
        $scopeKey = sr_member_oauth_provider_setting_key($providerKey, 'scope');
        $profileSyncKey = sr_member_oauth_provider_setting_key($providerKey, 'profile_sync_json');
        $sortOrderKey = sr_member_oauth_provider_setting_key($providerKey, 'sort_order');
        $providerLabel = (string) ($provider['label'] ?? $providerKey);
        $label = trim(sr_post_string($labelKey, 80));
        $clientId = sr_post_string_without_truncation($clientIdKey, 255);
        $secret = sr_post_string_without_truncation($secretKey, 512);
        $scope = sr_member_oauth_scope_setting_value_with_required($_POST[$scopeKey] ?? [], $provider);
        $providerForProfileSync = array_merge($provider, ['scope' => $scope]);
        $profileSyncJson = sr_member_oauth_profile_sync_rules_json_from_input(
            $_POST[$profileSyncKey] ?? [],
            $profileExtraFieldDefinitions,
            $providerForProfileSync,
            $errors,
            $providerLabel
        );
        $sortOrder = sr_admin_post_int_in_range($sortOrderKey, -9999, 9999, 6);
        $enabled = ($_POST[$enabledKey] ?? '') === '1';

        if ($clientId === null || $secret === null || $sortOrder === null) {
            $errors[] = $providerLabel . ' provider 설정 값을 확인해 주세요.';
            $clientId = (string) ($provider['client_id'] ?? '');
            $scope = sr_member_oauth_scope_setting_value_with_required($provider['scope'] ?? ($provider['scopes'] ?? []), $provider);
            $sortOrder = (int) ($provider['sort_order'] ?? 0);
        }
        if (strlen($scope) > 1000) {
            $errors[] = $providerLabel . ' scope 항목은 전체 1000자 이하로 입력해 주세요.';
            $scope = sr_member_oauth_scope_setting_value_with_required($provider['scope'] ?? ($provider['scopes'] ?? []), $provider);
        }
        if ($enabled && $label === '') {
            $errors[] = $providerKey . ' provider 라벨을 입력해 주세요.';
        }
        if ($enabled && trim((string) $clientId) === '') {
            $errors[] = $providerKey . ' provider client id를 입력해 주세요.';
        }

        $postedSettings[$enabledKey] = $enabled;
        $postedSettings[$labelKey] = $label !== '' ? $label : (string) ($provider['label'] ?? $providerKey);
        $postedSettings[$clientIdKey] = trim((string) $clientId);
        $postedSettings[$scopeKey] = trim((string) $scope);
        $postedSettings[$profileSyncKey] = $profileSyncJson;
        $postedSettings[$sortOrderKey] = (int) $sortOrder;
        if (trim((string) $secret) !== '') {
            $postedSettings[$secretKey] = trim((string) $secret);
        }
        if ($enabled) {
            $enabledProviders[] = $providerKey;
        }
    }

    if ($errors === []) {
        sr_member_oauth_save_settings($pdo, $postedSettings);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'member_oauth.settings.updated',
            'target_type' => 'module',
            'target_id' => 'member_oauth',
            'result' => 'success',
            'message' => 'Member OAuth settings updated.',
            'metadata' => [
                'mock_enabled' => (bool) $postedSettings['mock_enabled'],
                'enabled_providers' => $enabledProviders,
            ],
        ]);
        $notice = 'OAuth 설정을 저장했습니다.';
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/member-oauth');
}

$flashResult = sr_admin_pop_flash_result();
if ($flashResult['errors'] !== []) {
    $errors = $flashResult['errors'];
}
if ((string) $flashResult['notice'] !== '') {
    $notice = (string) $flashResult['notice'];
}
$settings = sr_member_oauth_settings($pdo);
$providers = sr_member_oauth_providers($pdo);

include SR_ROOT . '/modules/member_oauth/views/admin-settings.php';
