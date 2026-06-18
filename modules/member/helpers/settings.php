<?php

declare(strict_types=1);

function sr_member_account_select_columns(): string
{
    return 'id, account_identifier_hash, login_id_hash, email, email_hash, password_hash, display_name, locale, status, email_verified_at, last_login_at, created_at, updated_at';
}

function sr_member_default_settings(): array
{
    $metadata = sr_module_metadata('member');
    $settings = isset($metadata['settings']) && is_array($metadata['settings']) ? $metadata['settings'] : [];

    return [
        'allow_registration' => (bool) ($settings['allow_registration'] ?? true),
        'email_verification_enabled' => (bool) ($settings['email_verification_enabled'] ?? true),
        'login_identifier' => sr_member_normalize_login_identifier_setting($settings['login_identifier'] ?? 'both'),
        'login_throttle_window_seconds' => (int) ($settings['login_throttle_window_seconds'] ?? 900),
        'login_throttle_account_limit' => (int) ($settings['login_throttle_account_limit'] ?? 5),
        'login_throttle_ip_limit' => (int) ($settings['login_throttle_ip_limit'] ?? 20),
        'password_reset_throttle_window_seconds' => (int) ($settings['password_reset_throttle_window_seconds'] ?? 900),
        'password_reset_throttle_account_limit' => (int) ($settings['password_reset_throttle_account_limit'] ?? 3),
        'password_reset_throttle_ip_limit' => (int) ($settings['password_reset_throttle_ip_limit'] ?? 10),
        'email_verification_throttle_window_seconds' => (int) ($settings['email_verification_throttle_window_seconds'] ?? 900),
        'email_verification_throttle_account_limit' => (int) ($settings['email_verification_throttle_account_limit'] ?? 3),
        'email_verification_throttle_ip_limit' => (int) ($settings['email_verification_throttle_ip_limit'] ?? 20),
        'register_throttle_window_seconds' => (int) ($settings['register_throttle_window_seconds'] ?? 900),
        'register_throttle_ip_limit' => (int) ($settings['register_throttle_ip_limit'] ?? 10),
        'member_skin_key' => is_string($settings['member_skin_key'] ?? null) ? (string) $settings['member_skin_key'] : 'basic',
        'profile_phone_enabled' => (bool) ($settings['profile_phone_enabled'] ?? true),
        'profile_phone_required' => (bool) ($settings['profile_phone_required'] ?? false),
        'profile_birth_date_enabled' => (bool) ($settings['profile_birth_date_enabled'] ?? true),
        'profile_birth_date_required' => (bool) ($settings['profile_birth_date_required'] ?? false),
        'profile_avatar_enabled' => (bool) ($settings['profile_avatar_enabled'] ?? true),
        'profile_avatar_required' => (bool) ($settings['profile_avatar_required'] ?? false),
        'profile_text_enabled' => (bool) ($settings['profile_text_enabled'] ?? true),
        'profile_text_required' => (bool) ($settings['profile_text_required'] ?? false),
        'nickname_enabled' => (bool) ($settings['nickname_enabled'] ?? true),
        'nickname_required' => (bool) ($settings['nickname_enabled'] ?? true),
    ];
}

function sr_member_settings(PDO $pdo): array
{
    $settings = array_merge(sr_member_default_settings(), sr_module_settings($pdo, 'member'));

    $settings['allow_registration'] = (bool) $settings['allow_registration'];
    $settings['email_verification_enabled'] = (bool) $settings['email_verification_enabled'];
    $settings['nickname_enabled'] = (bool) ($settings['nickname_enabled'] ?? true);
    $settings['nickname_required'] = $settings['nickname_enabled'];
    $settings['login_identifier'] = sr_member_normalize_login_identifier_setting($settings['login_identifier'] ?? 'both');
    $settings['member_skin_key'] = sr_member_skin_key($settings);
    foreach (sr_member_profile_field_definitions() as $definition) {
        $enabledKey = (string) $definition['enabled_key'];
        $requiredKey = (string) $definition['required_key'];
        $settings[$enabledKey] = (bool) ($settings[$enabledKey] ?? false);
        $settings[$requiredKey] = $settings[$enabledKey] && (bool) ($settings[$requiredKey] ?? false);
    }

    foreach (sr_member_integer_setting_keys() as $key => $limits) {
        $settings[$key] = sr_member_clamp_int((int) ($settings[$key] ?? $limits['default']), $limits['min'], $limits['max']);
    }

    return $settings;
}

function sr_member_login_identifier_options(): array
{
    return [
        'both' => sr_t('member::settings.login_identifier.both'),
    ];
}

function sr_member_normalize_login_identifier_setting($value): string
{
    return 'both';
}

function sr_member_email_login_enabled(array $settings): bool
{
    return true;
}

function sr_member_login_id_required(array $settings): bool
{
    return false;
}

function sr_member_skin_options(): array
{
    return sr_filter_view_options([
        'basic' => [
            'label' => sr_t('member::settings.skin.basic'),
            'stylesheets' => [
                '/modules/member/skins/basic/skin.css',
            ],
            'views' => [
                'login' => SR_ROOT . '/modules/member/skins/basic/login.php',
                'register' => SR_ROOT . '/modules/member/skins/basic/register.php',
                'account' => SR_ROOT . '/modules/member/skins/basic/account.php',
                'password-reset-request' => SR_ROOT . '/modules/member/skins/basic/password-reset-request.php',
                'password-reset' => SR_ROOT . '/modules/member/skins/basic/password-reset.php',
                'privacy-requests' => SR_ROOT . '/modules/member/skins/basic/privacy-requests.php',
                'withdraw' => SR_ROOT . '/modules/member/skins/basic/withdraw.php',
                'email-verified' => SR_ROOT . '/modules/member/skins/basic/email-verified.php',
            ],
        ],
    ], sr_member_required_skin_view_keys(), 'member skin');
}

function sr_member_skin_stylesheets(string $skinKey): array
{
    $options = sr_member_skin_options();
    $skinKey = isset($options[$skinKey]) ? $skinKey : 'basic';
    $stylesheets = isset($options[$skinKey]['stylesheets']) && is_array($options[$skinKey]['stylesheets'])
        ? $options[$skinKey]['stylesheets']
        : [];
    $validStylesheets = [];

    foreach ($stylesheets as $stylesheet) {
        if (!is_string($stylesheet) || !sr_is_safe_relative_url($stylesheet)) {
            continue;
        }
        if (str_starts_with($stylesheet, '/') && !is_file(SR_ROOT . $stylesheet)) {
            continue;
        }
        $validStylesheets[] = $stylesheet;
    }

    return $validStylesheets;
}

function sr_member_required_skin_view_keys(): array
{
    return [
        'login',
        'register',
        'account',
        'password-reset-request',
        'password-reset',
        'privacy-requests',
        'withdraw',
        'email-verified',
    ];
}

function sr_member_skin_key(array $settings): string
{
    $skinKey = (string) ($settings['member_skin_key'] ?? 'basic');

    return isset(sr_member_skin_options()[$skinKey]) ? $skinKey : 'basic';
}

function sr_member_skin_view(string $skinKey, string $viewKey): string
{
    $options = sr_member_skin_options();
    $view = (string) ($options[$skinKey]['views'][$viewKey] ?? $options['basic']['views'][$viewKey] ?? '');

    if (is_file($view)) {
        return $view;
    }

    $fallback = (string) ($options['basic']['views'][$viewKey] ?? '');
    if (is_file($fallback)) {
        return $fallback;
    }

    throw new RuntimeException(sr_t('member::settings.skin.default_missing'));
}

function sr_member_integer_setting_keys(): array
{
    return [
        'login_throttle_window_seconds' => ['default' => 900, 'min' => 0, 'max' => 86400],
        'login_throttle_account_limit' => ['default' => 5, 'min' => 0, 'max' => 1000],
        'login_throttle_ip_limit' => ['default' => 20, 'min' => 0, 'max' => 1000],
        'password_reset_throttle_window_seconds' => ['default' => 900, 'min' => 0, 'max' => 86400],
        'password_reset_throttle_account_limit' => ['default' => 3, 'min' => 0, 'max' => 1000],
        'password_reset_throttle_ip_limit' => ['default' => 10, 'min' => 0, 'max' => 1000],
        'email_verification_throttle_window_seconds' => ['default' => 900, 'min' => 0, 'max' => 86400],
        'email_verification_throttle_account_limit' => ['default' => 3, 'min' => 0, 'max' => 1000],
        'email_verification_throttle_ip_limit' => ['default' => 20, 'min' => 0, 'max' => 1000],
        'register_throttle_window_seconds' => ['default' => 900, 'min' => 0, 'max' => 86400],
        'register_throttle_ip_limit' => ['default' => 10, 'min' => 0, 'max' => 1000],
    ];
}

function sr_member_clamp_int(int $value, int $min, int $max): int
{
    return max($min, min($max, $value));
}

function sr_member_profile_field_setting_keys(): array
{
    $keys = [];
    foreach (sr_member_profile_field_definitions() as $definition) {
        $keys[(string) $definition['enabled_key']] = (string) $definition['label'];
    }

    return $keys;
}

function sr_member_profile_field_required_setting_keys(): array
{
    $keys = [];
    foreach (sr_member_profile_field_definitions() as $definition) {
        $keys[(string) $definition['required_key']] = (string) $definition['label'];
    }

    return $keys;
}

function sr_member_profile_field_definitions(): array
{
    return [
        'phone' => [
            'label' => sr_t('member::settings.profile.phone'),
            'enabled_key' => 'profile_phone_enabled',
            'required_key' => 'profile_phone_required',
        ],
        'birth_date' => [
            'label' => sr_t('member::settings.profile.birth_date'),
            'enabled_key' => 'profile_birth_date_enabled',
            'required_key' => 'profile_birth_date_required',
        ],
        'avatar_path' => [
            'label' => sr_t('member::settings.profile.avatar'),
            'enabled_key' => 'profile_avatar_enabled',
            'required_key' => 'profile_avatar_required',
        ],
        'profile_text' => [
            'label' => sr_t('member::settings.profile.profile_text'),
            'enabled_key' => 'profile_text_enabled',
            'required_key' => 'profile_text_required',
        ],
    ];
}

function sr_member_profile_field_settings(array $settings): array
{
    $visible = [];
    foreach (sr_member_profile_field_policies($settings) as $field => $policy) {
        $visible[$field] = !empty($policy['visible']);
    }

    return $visible;
}

function sr_member_profile_field_required_settings(array $settings): array
{
    $required = [];
    foreach (sr_member_profile_field_policies($settings) as $field => $policy) {
        $required[$field] = !empty($policy['required']);
    }

    return $required;
}

function sr_member_profile_field_policies(array $settings): array
{
    $policies = [];
    foreach (sr_member_profile_field_definitions() as $field => $definition) {
        $enabledKey = (string) $definition['enabled_key'];
        $requiredKey = (string) $definition['required_key'];
        $visible = !empty($settings[$enabledKey]);
        $policies[$field] = [
            'label' => (string) $definition['label'],
            'visible' => $visible,
            'required' => $visible && !empty($settings[$requiredKey]),
        ];
    }

    return $policies;
}

function sr_member_profile_has_visible_fields(array $policies): bool
{
    foreach ($policies as $policy) {
        if (!empty($policy['visible'])) {
            return true;
        }
    }

    return false;
}
