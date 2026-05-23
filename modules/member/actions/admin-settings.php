<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/member-settings', 'view');

$errors = [];
$notice = '';
$settings = sr_member_settings($pdo);
$integerSettingKeys = sr_member_integer_setting_keys();

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/member-settings', 'edit');

    $settings['allow_registration'] = ($_POST['allow_registration'] ?? '') === '1';
    $settings['email_verification_enabled'] = ($_POST['email_verification_enabled'] ?? '') === '1';
    $memberSkinKey = sr_post_string('member_skin_key', 40);
    if (!isset(sr_member_skin_options()[$memberSkinKey])) {
        $errors[] = sr_t('member::action.admin_settings.skin_invalid');
    } else {
        $settings['member_skin_key'] = $memberSkinKey;
    }
    foreach (sr_member_profile_field_definitions() as $definition) {
        $enabledKey = (string) $definition['enabled_key'];
        $requiredKey = (string) $definition['required_key'];
        $settings[$enabledKey] = ($_POST[$enabledKey] ?? '') === '1';
        $settings[$requiredKey] = ($_POST[$requiredKey] ?? '') === '1';
        if ($settings[$requiredKey] && !$settings[$enabledKey]) {
            $errors[] = sr_t('member::action.admin_settings.required_needs_visible', ['label' => (string) $definition['label']]);
        }
    }

    foreach ($integerSettingKeys as $key => $limits) {
        $integerValue = sr_admin_post_int_in_range($key, (int) $limits['min'], (int) $limits['max']);
        if ($integerValue === null) {
            $errors[] = sr_t('member::action.admin_settings.integer_range', [
                'key' => $key,
                'min' => (string) $limits['min'],
                'max' => (string) $limits['max'],
            ]);
            continue;
        }

        $settings[$key] = $integerValue;
    }

    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'member' LIMIT 1");
    $stmt->execute();
    $memberModule = $stmt->fetch();
    if (!is_array($memberModule)) {
        $errors[] = sr_t('member::action.admin_settings.module_missing');
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_module_settings
                (module_id, setting_key, setting_value, value_type, created_at, updated_at)
             VALUES
                (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type),
                updated_at = VALUES(updated_at)'
        );

        $rows = [
            ['allow_registration', $settings['allow_registration'] ? '1' : '0', 'bool'],
            ['email_verification_enabled', $settings['email_verification_enabled'] ? '1' : '0', 'bool'],
            ['member_skin_key', (string) $settings['member_skin_key'], 'string'],
        ];
        foreach (sr_member_profile_field_definitions() as $definition) {
            $enabledKey = (string) $definition['enabled_key'];
            $requiredKey = (string) $definition['required_key'];
            $rows[] = [$enabledKey, !empty($settings[$enabledKey]) ? '1' : '0', 'bool'];
            $rows[] = [$requiredKey, !empty($settings[$requiredKey]) ? '1' : '0', 'bool'];
        }

        foreach ($integerSettingKeys as $key => $limits) {
            $rows[] = [$key, (string) $settings[$key], 'int'];
        }

        foreach ($rows as $row) {
            $stmt->execute([
                'module_id' => (int) $memberModule['id'],
                'setting_key' => $row[0],
                'setting_value' => $row[1],
                'value_type' => $row[2],
                'created_at' => sr_now(),
                'updated_at' => sr_now(),
            ]);
        }
        sr_clear_module_settings_cache('member');

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'member.settings.updated',
            'target_type' => 'module',
            'target_id' => 'member',
            'result' => 'success',
            'message' => 'Member settings updated.',
            'metadata' => [
                'allow_registration' => (bool) $settings['allow_registration'],
                'email_verification_enabled' => (bool) $settings['email_verification_enabled'],
                'login_identifier' => (string) $settings['login_identifier'],
                'member_skin_key' => (string) $settings['member_skin_key'],
                'profile_fields' => sr_member_profile_field_policies($settings),
            ],
        ]);

    $notice = sr_t('member::action.admin_settings.saved');
    }
}

include SR_ROOT . '/modules/member/views/admin-settings.php';
