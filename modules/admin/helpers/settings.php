<?php

declare(strict_types=1);

function toy_admin_settings_allowed_types(): array
{
    return ['string', 'int', 'bool', 'json'];
}

function toy_admin_reserved_site_setting_keys(): array
{
    return [
        'site.name' => true,
        'site.base_url' => true,
        'site.timezone' => true,
        'site.default_locale' => true,
        'site.supported_locales' => true,
        'site.status' => true,
        'public_layout_key' => true,
        'ui_color_scheme' => true,
    ];
}

function toy_admin_sensitive_site_setting_keys(): array
{
    return [
        'admin.module_sources_enabled' => true,
    ];
}

function toy_admin_site_setting_requires_reauth(string $settingKey): bool
{
    return isset(toy_admin_sensitive_site_setting_keys()[$settingKey]);
}

function toy_admin_site_setting_requires_bool(string $settingKey): bool
{
    return toy_admin_site_setting_requires_reauth($settingKey);
}

function toy_admin_setting_value_is_secret(string $settingKey): bool
{
    return preg_match(
        '/(?:^|[._-])(?:password|token|secret|credential|bearer|api[._-]?key|access[._-]?key|private[._-]?key|client[._-]?secret|app[._-]?key)(?:$|[._-])/',
        strtolower($settingKey)
    ) === 1;
}

function toy_admin_setting_display_value(array $setting): string
{
    $settingKey = (string) ($setting['setting_key'] ?? '');
    $settingValue = (string) ($setting['setting_value'] ?? '');

    if (toy_admin_setting_value_is_secret($settingKey)) {
        return $settingValue === '' ? '' : '[masked]';
    }

    return $settingValue;
}

function toy_admin_site_setting_value_is_secret(string $settingKey): bool
{
    return toy_admin_setting_value_is_secret($settingKey);
}

function toy_admin_site_setting_display_value(array $setting): string
{
    return toy_admin_setting_display_value($setting);
}

function toy_admin_module_setting_display_value(array $setting): string
{
    return toy_admin_setting_display_value($setting);
}

function toy_admin_setting_value_type_errors(string $settingValue, string $valueType): array
{
    if ($valueType === 'int' && preg_match('/\A-?\d+\z/', $settingValue) !== 1) {
        return ['int 설정값은 정수 문자열이어야 합니다.'];
    }

    if ($valueType === 'bool' && !in_array(strtolower($settingValue), ['0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'], true)) {
        return ['bool 설정값은 1/0, true/false, yes/no, on/off 중 하나여야 합니다.'];
    }

    if ($valueType === 'json' && json_decode($settingValue, true) === null && json_last_error() !== JSON_ERROR_NONE) {
        return ['JSON 설정값이 올바르지 않습니다.'];
    }

    return [];
}

function toy_admin_normalize_setting_value(string $settingValue, string $valueType): string
{
    if ($valueType === 'int') {
        return (string) (int) $settingValue;
    }

    if ($valueType === 'bool') {
        return in_array(strtolower($settingValue), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }

    return $settingValue;
}

function toy_admin_site_setting_values(?array $site): array
{
    return [
        'name' => (string) ($site['name'] ?? ''),
        'base_url' => (string) ($site['base_url'] ?? ''),
        'timezone' => (string) ($site['timezone'] ?? 'Asia/Seoul'),
        'default_locale' => (string) ($site['default_locale'] ?? 'ko'),
        'supported_locales' => (string) ($site['supported_locales'] ?? (string) ($site['default_locale'] ?? 'ko')),
        'status' => (string) ($site['status'] ?? 'active'),
        'public_layout_key' => toy_public_layout_key($site),
        'ui_color_scheme' => toy_color_scheme($site),
    ];
}

function toy_admin_previous_site_setting_values(?array $site): array
{
    return [
        'name' => (string) ($site['name'] ?? ''),
        'base_url' => (string) ($site['base_url'] ?? ''),
        'timezone' => (string) ($site['timezone'] ?? ''),
        'default_locale' => (string) ($site['default_locale'] ?? ''),
        'supported_locales' => (string) ($site['supported_locales'] ?? ''),
        'status' => (string) ($site['status'] ?? ''),
        'public_layout_key' => toy_public_layout_key($site),
        'ui_color_scheme' => toy_color_scheme($site),
    ];
}

function toy_admin_post_site_setting_values(): array
{
    return [
        'name' => toy_post_string('name', 120),
        'base_url' => toy_post_string('base_url', 255),
        'timezone' => toy_post_string('timezone', 80),
        'default_locale' => toy_post_string('default_locale', 20),
        'supported_locales' => toy_post_string('supported_locales', 255),
        'status' => toy_post_string('status', 30),
        'public_layout_key' => toy_post_string('public_layout_key', 60),
        'ui_color_scheme' => toy_post_string('ui_color_scheme', 20),
    ];
}

function toy_admin_validate_supported_locales(array &$values, array &$errors): void
{
    $supportedLocales = [];
    foreach (preg_split('/[\s,]+/', $values['supported_locales']) ?: [] as $locale) {
        if ($locale === '') {
            continue;
        }

        if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) !== 1) {
            $errors[] = '지원 locale 목록 값이 올바르지 않습니다.';
            return;
        }

        $supportedLocales[$locale] = $locale;
    }

    if (!isset($supportedLocales[$values['default_locale']])) {
        $supportedLocales[$values['default_locale']] = $values['default_locale'];
    }

    $values['supported_locales'] = implode(',', array_values($supportedLocales));
}

function toy_admin_handle_settings_post(
    PDO $pdo,
    array $account,
    ?array $site,
    bool $canManageAdvancedSettings,
    array $allowedSettingTypes,
    array $reservedSiteSettingKeys
): array {
    $errors = [];
    $notice = '';
    $values = toy_admin_site_setting_values($site);
    $intent = toy_post_string('intent', 40);

    if (!in_array($intent, ['site', 'site_setting', 'delete_site_setting'], true)) {
        $errors[] = '사이트 설정 작업 값이 올바르지 않습니다.';
    }

    if ($errors === [] && $intent === 'site_setting') {
        if (!$canManageAdvancedSettings) {
            $errors[] = '고급 사이트 설정은 owner 권한이 필요합니다.';
        }

        $settingKey = toy_post_string('setting_key', 120);
        $settingValue = toy_post_string('setting_value', 5000);
        $valueType = toy_post_string('value_type', 20);

        if (preg_match('/\A[a-z][a-z0-9_.-]{1,119}\z/', $settingKey) !== 1) {
            $errors[] = '설정 key 형식이 올바르지 않습니다.';
        }

        if (!in_array($valueType, $allowedSettingTypes, true)) {
            $errors[] = '설정 타입이 올바르지 않습니다.';
        }

        if (toy_admin_site_setting_requires_bool($settingKey) && $valueType !== 'bool') {
            $errors[] = '고위험 사이트 설정은 bool 타입으로만 저장할 수 있습니다.';
        }

        if (isset($reservedSiteSettingKeys[$settingKey])) {
            $errors[] = '기본 사이트 설정은 위의 전용 양식에서 수정하세요.';
        }

        foreach (toy_admin_setting_value_type_errors($settingValue, $valueType) as $valueError) {
            $errors[] = $valueError;
        }

        if ($errors === [] && toy_admin_site_setting_requires_reauth($settingKey)) {
            foreach (toy_admin_site_setting_reauth_errors($pdo, $account, $settingKey, 'save') as $reauthError) {
                $errors[] = $reauthError;
            }
        }

        if ($errors === []) {
            $settingValue = toy_admin_normalize_setting_value($settingValue, $valueType);
            toy_save_site_setting($pdo, $settingKey, $settingValue, $valueType);

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site.setting.saved',
                'target_type' => 'site_setting',
                'target_id' => $settingKey,
                'result' => 'success',
                'message' => 'Site setting saved.',
                'metadata' => [
                    'value_type' => $valueType,
                ],
            ]);

            $notice = '사이트 설정 항목을 저장했습니다.';
        }
    } elseif ($errors === [] && $intent === 'delete_site_setting') {
        if (!$canManageAdvancedSettings) {
            $errors[] = '고급 사이트 설정은 owner 권한이 필요합니다.';
        }

        $settingKey = toy_post_string('setting_key', 120);
        if (preg_match('/\A[a-z][a-z0-9_.-]{1,119}\z/', $settingKey) !== 1) {
            $errors[] = '설정 key 형식이 올바르지 않습니다.';
        }

        if (isset($reservedSiteSettingKeys[$settingKey])) {
            $errors[] = '기본 사이트 설정은 삭제할 수 없습니다.';
        }

        if ($errors === [] && toy_admin_site_setting_requires_reauth($settingKey)) {
            foreach (toy_admin_site_setting_reauth_errors($pdo, $account, $settingKey, 'delete') as $reauthError) {
                $errors[] = $reauthError;
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('DELETE FROM toy_site_settings WHERE setting_key = :setting_key');
            $stmt->execute(['setting_key' => $settingKey]);
            toy_clear_site_settings_cache();

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site.setting.deleted',
                'target_type' => 'site_setting',
                'target_id' => $settingKey,
                'result' => 'success',
                'message' => 'Site setting deleted.',
            ]);

            $notice = '사이트 설정 항목을 삭제했습니다.';
        }
    } elseif ($errors === [] && $intent === 'site') {
        $values = toy_admin_post_site_setting_values();

        if ($values['name'] === '') {
            $errors[] = '사이트 이름을 입력하세요.';
        }

        if ($values['base_url'] !== '' && !toy_is_site_base_url($values['base_url'])) {
            $errors[] = 'Base URL은 query, fragment, 사용자 정보를 제외한 http 또는 https URL이어야 합니다.';
        }

        if (!in_array($values['timezone'], timezone_identifiers_list(), true)) {
            $errors[] = 'timezone 값이 올바르지 않습니다.';
        }

        if (!preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $values['default_locale'])) {
            $errors[] = '기본 locale 값이 올바르지 않습니다.';
        }

        if ($errors === []) {
            toy_admin_validate_supported_locales($values, $errors);
        }

        if (!in_array($values['status'], ['active', 'maintenance'], true)) {
            $errors[] = '운영 상태 값이 올바르지 않습니다.';
        }

        if (!isset(toy_public_layout_options()[$values['public_layout_key']])) {
            $errors[] = '공통 레이아웃 값이 올바르지 않습니다.';
        }

        if (!isset(toy_color_scheme_options()[$values['ui_color_scheme']])) {
            $errors[] = 'UI 색상 모드 값이 올바르지 않습니다.';
        }

        if ($errors === []) {
            $previousValues = toy_admin_previous_site_setting_values($site);

            toy_save_site_settings($pdo, [
                'site.name' => ['value' => $values['name'], 'type' => 'string'],
                'site.base_url' => ['value' => $values['base_url'], 'type' => 'string'],
                'site.timezone' => ['value' => $values['timezone'], 'type' => 'string'],
                'site.default_locale' => ['value' => $values['default_locale'], 'type' => 'string'],
                'site.supported_locales' => ['value' => $values['supported_locales'], 'type' => 'string'],
                'site.status' => ['value' => $values['status'], 'type' => 'string'],
                'public_layout_key' => ['value' => $values['public_layout_key'], 'type' => 'string'],
                'ui_color_scheme' => ['value' => $values['ui_color_scheme'], 'type' => 'string'],
            ]);

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site.settings.updated',
                'target_type' => 'site_settings',
                'target_id' => 'site',
                'result' => 'success',
                'message' => 'Site settings updated.',
                'metadata' => [
                    'before' => $previousValues,
                    'after' => $values,
                ],
            ]);

            $site = toy_load_site($pdo);
            $values = toy_admin_site_setting_values(is_array($site) ? $site : null);
            $notice = '사이트 설정을 저장했습니다.';
        }
    }

    return [
        'errors' => $errors,
        'notice' => $notice,
        'values' => $values,
        'site' => $site,
    ];
}

function toy_admin_site_setting_reauth_errors(PDO $pdo, array $account, string $settingKey, string $action): array
{
    $password = toy_post_string('owner_password', 255);
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return ['owner 재인증 계정을 확인할 수 없습니다.'];
    }

    $throttle = toy_member_reauth_throttle_status($pdo, $accountId);
    if (!empty($throttle['limited'])) {
        toy_member_log_auth($pdo, $accountId, 'reauth_blocked', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'site.setting.reauth_blocked',
            'target_type' => 'site_setting',
            'target_id' => $settingKey,
            'result' => 'failure',
            'message' => 'Sensitive site setting reauthentication blocked by throttle.',
            'metadata' => [
                'action' => $action,
            ],
        ]);
        return ['재인증 시도가 많습니다. 잠시 후 다시 시도하세요.'];
    }

    if ($password === '' || !password_verify($password, (string) ($account['password_hash'] ?? ''))) {
        toy_member_log_auth($pdo, $accountId, 'site_setting_reauth', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'site.setting.reauth_failed',
            'target_type' => 'site_setting',
            'target_id' => $settingKey,
            'result' => 'failure',
            'message' => 'Sensitive site setting reauthentication failed.',
            'metadata' => [
                'action' => $action,
            ],
        ]);
        return ['고위험 사이트 설정 변경 전 owner 비밀번호를 다시 입력하세요.'];
    }

    toy_member_log_auth($pdo, $accountId, 'site_setting_reauth', 'success');
    return [];
}

function toy_admin_site_settings(PDO $pdo): array
{
    $siteSettings = [];
    $stmt = $pdo->query(
        "SELECT setting_key, setting_value, value_type, updated_at
         FROM toy_site_settings
         WHERE setting_key NOT IN ('site.name', 'site.base_url', 'site.timezone', 'site.default_locale', 'site.supported_locales', 'site.status', 'public_layout_key', 'ui_color_scheme')
         ORDER BY setting_key ASC"
    );
    foreach ($stmt->fetchAll() as $row) {
        $siteSettings[] = $row;
    }

    return $siteSettings;
}
